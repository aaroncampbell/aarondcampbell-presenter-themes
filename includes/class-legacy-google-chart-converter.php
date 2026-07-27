<?php
/**
 * Convert characterized legacy Google line charts into Presenter Chart blocks.
 *
 * @package AaronCampbellPresenterThemes
 */

namespace AaronCampbell\PresenterThemes;

use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;

/** Safely converts a complete recognized chart slide. */
final class Legacy_Google_Chart_Converter {
	/**
	 * Convert a whole slide, or return the preceding converter result unchanged.
	 *
	 * @param mixed  $blocks  Earlier converter result.
	 * @param string $content Complete legacy slide HTML.
	 * @return mixed Converted blocks or the preceding value.
	 */
	public function convert( mixed $blocks, string $content ): mixed {
		if ( null !== $blocks || ! str_contains( $content, 'google.visualization' ) ) {
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
				$src = $script->getAttribute( 'src' );
				if ( ! str_contains( $src, 'www.gstatic.com/charts/loader.js' ) ) {
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
			if ( $node instanceof DOMElement && isset( $charts[ $node->getAttribute( 'id' ) ] ) ) {
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
	 * Parse one recognized line-chart program.
	 *
	 * @param string $script Inline JavaScript source.
	 * @return array{target: string, columns: array<int, string>, rows: array<int, array<int, mixed>>, options: array<string, mixed>} Chart descriptor.
	 * @throws RuntimeException For unsupported JavaScript.
	 */
	private function parse_chart_script( string $script ): array {
		if ( ! preg_match( '/document\.getElementById\(\s*(["\'])([A-Za-z][A-Za-z0-9_-]*)\1\s*\)/', $script, $target_match ) ) {
			throw new RuntimeException( 'Chart target is not a literal ID.' );
		}
		if ( ! preg_match( '/new\s+google\.visualization\.LineChart\s*\(/', $script ) || ! preg_match( '/\.draw\s*\(\s*data\s*,\s*options\s*\)/', $script ) ) {
			throw new RuntimeException( 'Unsupported Google chart program.' );
		}

		$data_literal    = $this->call_argument( $script, 'google.visualization.arrayToDataTable' );
		$options_literal = $this->assigned_object( $script, 'options' );
		$data            = ( new Legacy_Javascript_Literal_Parser( $data_literal ) )->parse();
		$options         = ( new Legacy_Javascript_Literal_Parser( $options_literal ) )->parse();
		if ( ! is_array( $data ) || count( $data ) < 2 || ! is_array( $data[0] ) || ! is_array( $options ) ) {
			throw new RuntimeException( 'Invalid chart literals.' );
		}

		$columns = array_map(
			static function ( mixed $column ): string {
				if ( is_array( $column ) && is_string( $column['label'] ?? null ) ) {
					return $column['label'];
				}
				if ( is_string( $column ) ) {
					return $column;
				}
				throw new RuntimeException( 'Unsupported chart column.' );
			},
			$data[0]
		);
		$rows    = array_values( array_slice( $data, 1 ) );
		if ( count( $columns ) < 2 || array_filter( $rows, static fn( mixed $row ): bool => ! is_array( $row ) || count( $row ) !== count( $columns ) ) ) {
			throw new RuntimeException( 'Inconsistent chart rows.' );
		}

		return array(
			'target'  => $target_match[2],
			'columns' => $columns,
			'rows'    => $rows,
			'options' => $options,
		);
	}

	/**
	 * Extract the balanced argument of a known function call.
	 *
	 * @param string $source Complete script.
	 * @param string $callee Required function name.
	 * @return string Argument expression.
	 * @throws RuntimeException When the call is absent or unbalanced.
	 */
	private function call_argument( string $source, string $callee ): string {
		$offset = strpos( $source, $callee );
		if ( false === $offset ) {
			throw new RuntimeException( 'Missing chart data call.' );
		}
		$opening = strpos( $source, '(', $offset + strlen( $callee ) );
		return $this->balanced( $source, $opening, '(', ')' );
	}

	/**
	 * Extract a known variable's object literal.
	 *
	 * @param string $source   Complete script.
	 * @param string $variable Required variable name.
	 * @return string Object literal.
	 * @throws RuntimeException When the assignment is absent or unbalanced.
	 */
	private function assigned_object( string $source, string $variable ): string {
		if ( ! preg_match( '/\b(?:var|let|const)\s+' . preg_quote( $variable, '/' ) . '\s*=\s*/', $source, $match, PREG_OFFSET_CAPTURE ) ) {
			throw new RuntimeException( 'Missing chart options.' );
		}
		$start = $match[0][1] + strlen( $match[0][0] );
		while ( isset( $source[ $start ] ) && ctype_space( $source[ $start ] ) ) {
			++$start;
		}
		return '{' . $this->balanced( $source, $start, '{', '}' ) . '}';
	}

	/**
	 * Extract text enclosed by balanced delimiters while respecting strings.
	 *
	 * @param string    $source  Complete source.
	 * @param int|false $opening Opening delimiter offset.
	 * @param string    $open    Opening delimiter.
	 * @param string    $close   Closing delimiter.
	 * @return string Enclosed text without delimiters.
	 * @throws RuntimeException When delimiters are missing or unbalanced.
	 */
	private function balanced( string $source, int|false $opening, string $open, string $close ): string {
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
				} elseif ( $character === $quote ) {
					$quote = '';
				}
				continue;
			}
			if ( '"' === $character || "'" === $character ) {
				$quote = $character;
			} elseif ( $open === $character ) {
				++$depth;
			} elseif ( $close === $character && 0 === --$depth ) {
				return substr( $source, $opening + 1, $index - $opening - 1 );
			}
		}
		throw new RuntimeException( 'Unbalanced chart literal.' );
	}

	/**
	 * Create one dynamic Presenter Chart parsed-block value.
	 *
	 * @param array<string, mixed> $chart     Parsed chart descriptor.
	 * @param DOMElement           $container Legacy chart target element.
	 * @return array<string, mixed> Parsed block.
	 */
	private function chart_block( array $chart, DOMElement $container ): array {
		$attributes = array(
			'chartType' => 'line',
			'columns'   => $chart['columns'],
			'rows'      => $chart['rows'],
			'options'   => $this->semantic_options( $chart['options'] ),
			'width'     => $this->style_dimension( $container->getAttribute( 'style' ), 'width', 800 ),
			'height'    => $this->style_dimension( $container->getAttribute( 'style' ), 'height', 400 ),
		);
		$attributes = array_merge( $attributes, $this->fragment_attributes( $container ) );
		return $this->block( 'presenter/chart', $attributes, '' );
	}

	/**
	 * Preserve behavior while dropping hard-coded legacy theme colors.
	 *
	 * @param array<string, mixed> $options Legacy options.
	 * @return array<string, mixed> Theme-neutral options.
	 */
	private function semantic_options( array $options ): array {
		$remove_colors = static function ( mixed $value ) use ( &$remove_colors ): mixed {
			if ( ! is_array( $value ) ) {
				return $value;
			}
			foreach ( array_keys( $value ) as $key ) {
				if ( in_array( $key, array( 'color', 'colors', 'baselineColor', 'gridlineColor', 'textStyle' ), true ) ) {
					unset( $value[ $key ] );
				} else {
					$value[ $key ] = $remove_colors( $value[ $key ] );
				}
			}
			return $value;
		};
		return $remove_colors( $options );
	}

	/**
	 * Convert ordinary top-level content or preserve it as Custom HTML.
	 *
	 * @param DOMDocument $document Parsed document.
	 * @param DOMNode     $node     Top-level content node.
	 * @return array<string, mixed> Parsed block.
	 */
	private function content_block( DOMDocument $document, DOMNode $node ): array {
		if ( ! $node instanceof DOMElement ) {
			$html = $document->saveHTML( $node );
			return $this->block( 'core/html', array(), $html );
		}
		$tag = strtolower( $node->tagName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM owns the API.
		if ( preg_match( '/^h([1-6])$/', $tag, $heading ) ) {
			$fragment_attributes = $this->fragment_attributes( $node );
			if ( array() !== $fragment_attributes ) {
				$node->removeAttribute( 'class' );
				$node->removeAttribute( 'data-fragment-index' );
			}
			$html       = $document->saveHTML( $node );
			$attributes = 2 === (int) $heading[1] ? array() : array( 'level' => (int) $heading[1] );
			return $this->block( 'core/heading', array_merge( $attributes, $fragment_attributes ), $html );
		}
		if ( 'p' === $tag ) {
			$fragment_attributes = $this->fragment_attributes( $node );
			if ( array() !== $fragment_attributes ) {
				$node->removeAttribute( 'class' );
				$node->removeAttribute( 'data-fragment-index' );
			}
			return $this->block( 'core/paragraph', $fragment_attributes, $document->saveHTML( $node ) );
		}
		$html = $document->saveHTML( $node );
		return $this->block( 'core/html', array(), $html );
	}

	/**
	 * Map legacy Reveal fragment attributes to Presenter's block contract.
	 *
	 * @param DOMElement $element Legacy element.
	 * @return array<string, mixed> Presenter fragment attributes.
	 */
	private function fragment_attributes( DOMElement $element ): array {
		$split   = preg_split( '/\s+/', trim( $element->getAttribute( 'class' ) ) );
		$classes = false === $split ? array() : $split;
		if ( ! in_array( 'fragment', $classes, true ) ) {
			return array();
		}
		$custom = array_values( array_diff( $classes, array( 'fragment' ) ) );
		$attrs  = array( 'presenterFragment' => true );
		if ( array() !== $custom ) {
			$attrs['presenterFragmentEffect']        = 'custom';
			$attrs['presenterFragmentCustomClasses'] = implode( ' ', $custom );
		}
		$index = $element->getAttribute( 'data-fragment-index' );
		if ( '' !== $index && ctype_digit( $index ) ) {
			$attrs['presenterFragmentIndex'] = (int) $index;
		}
		return $attrs;
	}

	/**
	 * Read a safe pixel dimension from a legacy style attribute.
	 *
	 * @param string $style    Legacy inline style.
	 * @param string $property Dimension property.
	 * @param int    $fallback Default dimension.
	 * @return int Pixel dimension.
	 */
	private function style_dimension( string $style, string $property, int $fallback ): int {
		return preg_match( '/(?:^|;)\s*' . preg_quote( $property, '/' ) . '\s*:\s*(\d+)px\b/i', $style, $match ) ? (int) $match[1] : $fallback;
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
