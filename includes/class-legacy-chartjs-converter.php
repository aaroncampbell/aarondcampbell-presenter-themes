<?php
/**
 * Convert characterized inline Chart.js slides into Presenter Chart blocks.
 *
 * @package AaronCampbellPresenterThemes
 */

namespace AaronCampbell\PresenterThemes;

use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;

/** Safely converts a complete recognized Chart.js slide. */
final class Legacy_Chartjs_Converter {
	/** Historical Chart.js runtime URL used by the characterized slides. */
	private const SCRIPT_URL = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.5.1/chart.min.js';

	/**
	 * Convert a whole slide, or return the preceding converter result unchanged.
	 *
	 * @param mixed  $blocks  Earlier converter result.
	 * @param string $content Complete legacy slide HTML.
	 * @return mixed Converted blocks or the preceding value.
	 */
	public function convert( mixed $blocks, string $content ): mixed {
		if ( null !== $blocks || ! str_contains( $content, 'new Chart' ) ) {
			return $blocks;
		}

		try {
			return $this->convert_slide( $content );
		} catch ( RuntimeException ) {
			return null;
		}
	}

	/**
	 * Convert one complete legacy slide.
	 *
	 * @param string $content Complete slide HTML.
	 * @return array<int, array<string, mixed>> Converted blocks.
	 * @throws RuntimeException When any chart relationship is ambiguous.
	 */
	private function convert_slide( string $content ): array {
		$document = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded   = $document->loadHTML( '<div id="presenter-legacy-slide-root">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded ) {
			throw new RuntimeException( 'Invalid legacy HTML.' );
		}

		$root = $document->getElementById( 'presenter-legacy-slide-root' );
		if ( ! $root instanceof DOMElement ) {
			throw new RuntimeException( 'Missing legacy slide root.' );
		}

		$charts = array();
		foreach ( iterator_to_array( $root->getElementsByTagName( 'script' ) ) as $script ) {
			$source = trim( $script->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM owns the API.
			if ( '' === $source ) {
				if ( self::SCRIPT_URL !== $script->getAttribute( 'src' ) ) {
					throw new RuntimeException( 'Unrecognized external script.' );
				}
				continue;
			}

			$chart = $this->parse_chart_script( $source );
			if ( isset( $charts[ $chart['target'] ] ) ) {
				throw new RuntimeException( 'Duplicate chart target.' );
			}
			$charts[ $chart['target'] ] = $chart;
		}
		if ( array() === $charts ) {
			throw new RuntimeException( 'No charts found.' );
		}

		$result       = array();
		$used_targets = array();
		foreach ( iterator_to_array( $root->childNodes ) as $node ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM owns the API.
			if ( ( XML_TEXT_NODE === $node->nodeType && '' === trim( $node->textContent ) ) || XML_COMMENT_NODE === $node->nodeType ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM owns the API.
				continue;
			}
			if ( $node instanceof DOMElement && 'script' === strtolower( $node->tagName ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM owns the API.
				continue;
			}
			if ( $node instanceof DOMElement && 'canvas' === strtolower( $node->tagName ) && isset( $charts[ $node->getAttribute( 'id' ) ] ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM owns the API.
				$target                  = $node->getAttribute( 'id' );
				$result[]                = $this->chart_block( $charts[ $target ], $node );
				$used_targets[ $target ] = true;
				continue;
			}
			$result[] = $this->content_block( $document, $node );
		}

		if ( array_keys( $charts ) !== array_keys( $used_targets ) ) {
			throw new RuntimeException( 'A chart target is missing.' );
		}
		return $result;
	}

	/**
	 * Parse one characterized Chart.js program without executing it.
	 *
	 * @param string $script Inline JavaScript source.
	 * @return array{target: string, chartType: string, columns: array<int, string>, rows: array<int, array<int, mixed>>, options: array<string, mixed>} Chart descriptor.
	 * @throws RuntimeException For unsupported JavaScript.
	 */
	private function parse_chart_script( string $script ): array {
		if ( 1 !== preg_match_all( '/\bnew\s+Chart\s*\(/', $script, $matches, PREG_OFFSET_CAPTURE ) ) {
			throw new RuntimeException( 'Expected exactly one Chart constructor.' );
		}

		$constructor = $matches[0][0][0];
		$offset      = $matches[0][0][1];
		$opening     = strpos( $script, '(', $offset + strlen( $constructor ) - 1 );
		$closing     = 0;
		$arguments   = $this->balanced( $script, $opening, '(', ')', $closing );
		$parts       = $this->split_top_level( $arguments, ',' );
		if ( 2 !== count( $parts ) || ! preg_match( '/^\s*;\s*$/', substr( $script, $closing + 1 ) ) ) {
			throw new RuntimeException( 'Unsupported Chart constructor program.' );
		}

		$prefix = substr( $script, 0, $offset );
		$target = $this->chart_target( trim( $parts[0] ), $prefix );
		$config = $this->object_properties( trim( $parts[1] ) );
		$this->assert_keys( $config, array( 'type', 'data', 'options' ) );
		if ( ! isset( $config['type'], $config['data'], $config['options'] ) ) {
			throw new RuntimeException( 'Incomplete Chart configuration.' );
		}

		$chart_type = $this->literal( $config['type'] );
		if ( ! in_array( $chart_type, array( 'line', 'bar' ), true ) ) {
			throw new RuntimeException( 'Unsupported Chart type.' );
		}

		$data = $this->object_properties( $config['data'] );
		$this->assert_keys( $data, array( 'labels', 'datasets' ) );
		if ( ! isset( $data['labels'], $data['datasets'] ) ) {
			throw new RuntimeException( 'Incomplete Chart data.' );
		}
		$labels   = $this->literal( $data['labels'] );
		$datasets = $this->literal( $data['datasets'] );
		if ( ! is_array( $labels ) || array() === $labels || ! is_array( $datasets ) || array() === $datasets ) {
			throw new RuntimeException( 'Invalid Chart data.' );
		}

		$columns = array( 'Label' );
		$series  = array();
		foreach ( $datasets as $dataset ) {
			if ( ! is_array( $dataset ) ) {
				throw new RuntimeException( 'Invalid Chart dataset.' );
			}
			$this->assert_keys( $dataset, array( 'label', 'data', 'fill', 'borderColor', 'backgroundColor', 'borderWidth' ) );
			if ( ! is_string( $dataset['label'] ?? null ) || ! is_array( $dataset['data'] ?? null ) || count( $dataset['data'] ) !== count( $labels ) ) {
				throw new RuntimeException( 'Invalid Chart series.' );
			}
			$columns[] = $dataset['label'];
			$series[]  = array_values( $dataset['data'] );
		}

		$rows = array();
		foreach ( array_values( $labels ) as $index => $label ) {
			$row = array( is_scalar( $label ) ? (string) $label : '' );
			if ( '' === $row[0] ) {
				throw new RuntimeException( 'Invalid Chart label.' );
			}
			foreach ( $series as $values ) {
				$value = $values[ $index ];
				if ( ! is_int( $value ) && ! is_float( $value ) && ! is_string( $value ) && null !== $value ) {
					throw new RuntimeException( 'Invalid Chart value.' );
				}
				$row[] = $value;
			}
			$rows[] = $row;
		}

		return array(
			'target'    => $target,
			'chartType' => $chart_type,
			'columns'   => $columns,
			'rows'      => $rows,
			'options'   => $this->semantic_options( $config['options'] ),
		);
	}

	/**
	 * Resolve and validate the Chart constructor target and program prefix.
	 *
	 * @param string $expression Constructor target expression.
	 * @param string $prefix     Source before the constructor.
	 * @return string Canvas element ID.
	 * @throws RuntimeException For unsupported executable statements or targets.
	 */
	private function chart_target( string $expression, string $prefix ): string {
		$assignment = '(?:var|let|const)\s+[A-Za-z_$][A-Za-z0-9_$]*\s*=\s*';
		if ( preg_match( '/^(["\'])([A-Za-z][A-Za-z0-9_-]*)\1$/', $expression, $literal ) ) {
			if ( ! preg_match( '/^\s*' . $assignment . '$/', $prefix ) ) {
				throw new RuntimeException( 'Unsupported Chart program prefix.' );
			}
			return $literal[2];
		}
		if ( preg_match( '/^document\.getElementById\(\s*(["\'])([A-Za-z][A-Za-z0-9_-]*)\1\s*\)$/', $expression, $direct ) ) {
			if ( ! preg_match( '/^\s*' . $assignment . '$/', $prefix ) ) {
				throw new RuntimeException( 'Unsupported Chart program prefix.' );
			}
			return $direct[2];
		}
		if ( ! preg_match( '/^[A-Za-z_$][A-Za-z0-9_$]*$/', $expression ) ) {
			throw new RuntimeException( 'Unsupported Chart target.' );
		}

		$pattern = '/^\s*(?:var|let|const)\s+' . preg_quote( $expression, '/' ) . '\s*=\s*document\.getElementById\(\s*(["\'])([A-Za-z][A-Za-z0-9_-]*)\1\s*\)\s*;\s*' . $assignment . '$/';
		if ( ! preg_match( $pattern, $prefix, $variable ) ) {
			throw new RuntimeException( 'Unsupported Chart target assignment.' );
		}
		return $variable[2];
	}

	/**
	 * Convert the characterized options into Presenter's semantic subset.
	 *
	 * @param string $source Options object literal.
	 * @return array<string, mixed> Canonical Presenter chart options.
	 * @throws RuntimeException For unknown behavior or malformed options.
	 */
	private function semantic_options( string $source ): array {
		$value_suffix = '';
		$sanitized    = $this->replace_callbacks( $source, $value_suffix );
		$options      = $this->literal( $sanitized );
		if ( ! is_array( $options ) ) {
			throw new RuntimeException( 'Invalid Chart options.' );
		}
		$this->assert_keys( $options, array( 'scales', 'elements', 'plugins' ) );

		$result = array();
		$scales = $options['scales'] ?? array();
		if ( ! is_array( $scales ) ) {
			throw new RuntimeException( 'Invalid Chart scales.' );
		}
		$this->assert_keys( $scales, array( 'x', 'y' ) );
		foreach ( array( 'x', 'y' ) as $axis ) {
			$settings = $scales[ $axis ] ?? array();
			if ( ! is_array( $settings ) ) {
				throw new RuntimeException( 'Invalid Chart axis.' );
			}
			$this->assert_keys( $settings, 'x' === $axis ? array( 'grid' ) : array( 'grid', 'beginAtZero', 'ticks' ) );
			if ( isset( $settings['grid'] ) ) {
				if ( ! is_array( $settings['grid'] ) ) {
					throw new RuntimeException( 'Invalid Chart grid.' );
				}
				$this->assert_keys( $settings['grid'], array( 'color' ) );
			}
			if ( isset( $settings['ticks'] ) ) {
				if ( ! is_array( $settings['ticks'] ) ) {
					throw new RuntimeException( 'Invalid Chart ticks.' );
				}
				$this->assert_keys( $settings['ticks'], array( 'callback' ) );
			}
		}
		if ( true === ( $scales['y']['beginAtZero'] ?? false ) ) {
			$result['vAxis'] = array( 'minValue' => 0 );
		}

		$elements = $options['elements'] ?? array();
		if ( ! is_array( $elements ) ) {
			throw new RuntimeException( 'Invalid Chart elements.' );
		}
		$this->assert_keys( $elements, array( 'point' ) );
		if ( isset( $elements['point'] ) ) {
			if ( ! is_array( $elements['point'] ) ) {
				throw new RuntimeException( 'Invalid Chart point options.' );
			}
			$this->assert_keys( $elements['point'], array( 'hitRadius' ) );
		}

		$plugins = $options['plugins'] ?? array();
		if ( ! is_array( $plugins ) ) {
			throw new RuntimeException( 'Invalid Chart plugins.' );
		}
		$this->assert_keys( $plugins, array( 'tooltip' ) );
		if ( isset( $plugins['tooltip'] ) ) {
			$tooltip = $plugins['tooltip'];
			if ( ! is_array( $tooltip ) || ! is_array( $tooltip['callbacks'] ?? null ) ) {
				throw new RuntimeException( 'Invalid Chart tooltip.' );
			}
			$this->assert_keys( $tooltip, array( 'callbacks' ) );
			$this->assert_keys( $tooltip['callbacks'], array( 'label' ) );
		}

		if ( '' !== $value_suffix ) {
			$result['valueSuffix'] = $value_suffix;
		}
		return $result;
	}

	/**
	 * Replace only the two characterized percentage callback functions.
	 *
	 * @param string $source       Options object literal.
	 * @param string $value_suffix Extracted semantic suffix.
	 * @return string Options literal containing only inert values.
	 * @throws RuntimeException For any unrecognized callback.
	 */
	private function replace_callbacks( string $source, string &$value_suffix ): string {
		while ( preg_match( '/\bfunction\s*\(/', $source, $match, PREG_OFFSET_CAPTURE ) ) {
			$start        = $match[0][1];
			$params_open  = strpos( $source, '(', $start );
			$params_close = 0;
			$params       = $this->balanced( $source, $params_open, '(', ')', $params_close );
			$body_open    = $params_close + 1;
			while ( isset( $source[ $body_open ] ) && ctype_space( $source[ $body_open ] ) ) {
				++$body_open;
			}
			$body_close = 0;
			$body       = $this->balanced( $source, $body_open, '{', '}', $body_close );
			$signature  = preg_replace( '/\s+/', '', $params );
			$normalized = preg_replace( array( '!//[^\r\n]*!', '!/\*.*?\*/!s', '/\s+/' ), '', $body );

			$is_tick    = 'value,index,values' === $signature && preg_match( '/^returnvalue\+(["\'])%\1;$/', $normalized );
			$is_tooltip = 'context' === $signature && preg_match( '/^console\.log\(context\);if\(context\.dataset\.label&&context\.formattedValue\)\{returncontext\.dataset\.label\+(["\']):\1\+context\.formattedValue\+(["\'])%\2;\}return(["\'])\3;$/', $normalized );
			if ( ! $is_tick && ! $is_tooltip ) {
				throw new RuntimeException( 'Unsupported Chart callback.' );
			}
			$value_suffix = '%';
			$source       = substr_replace( $source, 'null', $start, $body_close - $start + 1 );
		}
		return $source;
	}

	/**
	 * Parse a safe inert JavaScript literal.
	 *
	 * @param string $source Literal source.
	 * @return mixed Parsed literal value.
	 */
	private function literal( string $source ): mixed {
		return ( new Legacy_Javascript_Literal_Parser( trim( $source ) ) )->parse();
	}

	/**
	 * Parse the raw values of a JavaScript object literal.
	 *
	 * @param string $source Object literal source.
	 * @return array<string, string> Raw property values.
	 * @throws RuntimeException For malformed or duplicate members.
	 */
	private function object_properties( string $source ): array {
		$source = trim( $source );
		if ( ! str_starts_with( $source, '{' ) ) {
			throw new RuntimeException( 'Expected object literal.' );
		}
		$closing = 0;
		$inside  = $this->balanced( $source, 0, '{', '}', $closing );
		if ( '' !== trim( substr( $source, $closing + 1 ) ) ) {
			throw new RuntimeException( 'Unexpected object suffix.' );
		}

		$result = array();
		foreach ( $this->split_top_level( $inside, ',' ) as $member ) {
			if ( '' === trim( $member ) ) {
				continue;
			}
			$pair = $this->split_top_level( $member, ':' );
			if ( 2 !== count( $pair ) ) {
				throw new RuntimeException( 'Invalid object member.' );
			}
			$key_source = trim( $pair[0] );
			$key        = preg_match( '/^[A-Za-z_$][A-Za-z0-9_$]*$/', $key_source ) ? $key_source : $this->literal( $key_source );
			if ( ! is_string( $key ) || isset( $result[ $key ] ) ) {
				throw new RuntimeException( 'Invalid object key.' );
			}
			$result[ $key ] = trim( $pair[1] );
		}
		return $result;
	}

	/**
	 * Split source at a delimiter that is not nested or quoted.
	 *
	 * @param string $source    JavaScript source.
	 * @param string $delimiter Single-byte delimiter.
	 * @return array<int, string> Source parts.
	 * @throws RuntimeException For unbalanced source.
	 */
	private function split_top_level( string $source, string $delimiter ): array {
		$parts  = array();
		$start  = 0;
		$stack  = array();
		$quote  = '';
		$length = strlen( $source );
		$pairs  = array(
			'(' => ')',
			'[' => ']',
			'{' => '}',
		);
		for ( $index = 0; $index < $length; ++$index ) {
			$character = $source[ $index ];
			if ( '' !== $quote ) {
				if ( '\\' === $character ) {
					++$index;
				} elseif ( $quote === $character ) {
					$quote = '';
				}
				continue;
			}
			if ( '"' === $character || "'" === $character ) {
				$quote = $character;
				continue;
			}
			if ( isset( $pairs[ $character ] ) ) {
				$stack[] = $pairs[ $character ];
				continue;
			}
			if ( array() !== $stack && end( $stack ) === $character ) {
				array_pop( $stack );
				continue;
			}
			if ( array() === $stack && $delimiter === $character ) {
				$parts[] = substr( $source, $start, $index - $start );
				$start   = $index + 1;
			}
		}
		if ( '' !== $quote || array() !== $stack ) {
			throw new RuntimeException( 'Unbalanced JavaScript expression.' );
		}
		$parts[] = substr( $source, $start );
		return $parts;
	}

	/**
	 * Extract text enclosed by balanced delimiters while respecting strings.
	 *
	 * @param string    $source  JavaScript source.
	 * @param int|false $opening Opening delimiter offset.
	 * @param string    $open    Opening delimiter.
	 * @param string    $close   Closing delimiter.
	 * @param int       $closing Closing delimiter offset.
	 * @return string Enclosed source without delimiters.
	 * @throws RuntimeException For missing or unbalanced delimiters.
	 */
	private function balanced( string $source, int|false $opening, string $open, string $close, int &$closing ): string {
		if ( false === $opening || ( $source[ $opening ] ?? '' ) !== $open ) {
			throw new RuntimeException( 'Missing literal delimiter.' );
		}
		$depth  = 0;
		$quote  = '';
		$length = strlen( $source );
		for ( $index = $opening; $index < $length; ++$index ) {
			$character = $source[ $index ];
			if ( '' !== $quote ) {
				if ( '\\' === $character ) {
					++$index;
				} elseif ( $quote === $character ) {
					$quote = '';
				}
				continue;
			}
			if ( '"' === $character || "'" === $character ) {
				$quote = $character;
			} elseif ( $open === $character ) {
				++$depth;
			} elseif ( $close === $character && 0 === --$depth ) {
				$closing = $index;
				return substr( $source, $opening + 1, $index - $opening - 1 );
			}
		}
		throw new RuntimeException( 'Unbalanced JavaScript literal.' );
	}

	/**
	 * Reject unknown keys instead of silently changing chart behavior.
	 *
	 * @param array<mixed>       $value   Parsed object.
	 * @param array<int, string> $allowed Allowed keys.
	 * @throws RuntimeException When an unknown key is present.
	 */
	private function assert_keys( array $value, array $allowed ): void {
		if ( array_diff( array_keys( $value ), $allowed ) ) {
			throw new RuntimeException( 'Unsupported Chart option.' );
		}
	}

	/**
	 * Create one dynamic Presenter Chart parsed-block value.
	 *
	 * @param array<string, mixed> $chart  Parsed chart descriptor.
	 * @param DOMElement           $canvas Legacy canvas element.
	 * @return array<string, mixed> Parsed block.
	 */
	private function chart_block( array $chart, DOMElement $canvas ): array {
		return $this->block(
			'presenter/chart',
			array(
				'chartType' => $chart['chartType'],
				'columns'   => $chart['columns'],
				'rows'      => $chart['rows'],
				'options'   => $chart['options'],
				'width'     => $this->dimension( $canvas, 'width', 800 ),
				'height'    => $this->dimension( $canvas, 'height', 400 ),
			),
			''
		);
	}

	/**
	 * Read a safe canvas attribute or pixel style dimension.
	 *
	 * @param DOMElement $canvas   Legacy canvas element.
	 * @param string     $property Dimension name.
	 * @param int        $fallback Default pixels.
	 * @return int Pixel dimension.
	 */
	private function dimension( DOMElement $canvas, string $property, int $fallback ): int {
		$value = $canvas->getAttribute( $property );
		if ( '' !== $value && ctype_digit( $value ) ) {
			return (int) $value;
		}
		$style = $canvas->getAttribute( 'style' );
		return preg_match( '/(?:^|;)\s*' . preg_quote( $property, '/' ) . '\s*:\s*(\d+)px\b/i', $style, $match ) ? (int) $match[1] : $fallback;
	}

	/**
	 * Convert ordinary top-level content or preserve it as Custom HTML.
	 *
	 * @param DOMDocument $document Parsed document.
	 * @param DOMNode     $node     Top-level content node.
	 * @return array<string, mixed> Parsed block.
	 */
	private function content_block( DOMDocument $document, DOMNode $node ): array {
		if ( $node instanceof DOMElement && preg_match( '/^h([1-6])$/', strtolower( $node->tagName ), $heading ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM owns the API.
			$attributes = 2 === (int) $heading[1] ? array() : array( 'level' => (int) $heading[1] );
			return $this->block( 'core/heading', $attributes, $document->saveHTML( $node ) );
		}
		if ( $node instanceof DOMElement && 'p' === strtolower( $node->tagName ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM owns the API.
			return $this->block( 'core/paragraph', array(), $document->saveHTML( $node ) );
		}
		return $this->block( 'core/html', array(), $document->saveHTML( $node ) );
	}

	/**
	 * Build the parsed-block shape expected by WordPress serialization.
	 *
	 * @param string               $name       Block name.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $html       Static block HTML.
	 * @return array<string, mixed> Parsed block.
	 */
	private function block( string $name, array $attributes, string $html ): array {
		return array(
			'blockName'    => $name,
			'attrs'        => $attributes,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => '' === $html ? array() : array( $html ),
		);
	}
}
