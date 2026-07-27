<?php
/**
 * Safe parser for the small JavaScript-literal grammar used by legacy charts.
 *
 * @package AaronCampbellPresenterThemes
 */

namespace AaronCampbell\PresenterThemes;

use RuntimeException;

/** Parse inert legacy literals without evaluating JavaScript. */
final class Legacy_Javascript_Literal_Parser {
	/**
	 * Current byte offset.
	 *
	 * @var int
	 */
	private int $offset = 0;

	/**
	 * Create a parser.
	 *
	 * @param string $source Complete literal expression.
	 */
	public function __construct( private string $source ) {}

	/**
	 * Parse one complete literal expression without executing JavaScript.
	 *
	 * @return mixed Parsed value.
	 * @throws RuntimeException For unsupported syntax.
	 */
	public function parse(): mixed {
		$value = $this->expression();
		$this->whitespace();
		if ( strlen( $this->source ) !== $this->offset ) {
			throw new RuntimeException( 'Unexpected JavaScript after literal.' );
		}
		return $value;
	}

	/**
	 * Parse multiplication of literal primary values.
	 *
	 * @return mixed Parsed value.
	 * @throws RuntimeException For nonnumeric multiplication.
	 */
	private function expression(): mixed {
		$value = $this->primary();
		$this->whitespace();
		while ( $this->consume( '*' ) ) {
			$right = $this->primary();
			if ( ( ! is_int( $value ) && ! is_float( $value ) ) || ( ! is_int( $right ) && ! is_float( $right ) ) ) {
				throw new RuntimeException( 'Only numeric multiplication is supported.' );
			}
			$value *= $right;
			$this->whitespace();
		}
		return $value;
	}

	/**
	 * Parse one primary literal.
	 *
	 * @return mixed Parsed value.
	 * @throws RuntimeException For unsupported syntax.
	 */
	private function primary(): mixed {
		$this->whitespace();
		$character = $this->source[ $this->offset ] ?? '';
		if ( '[' === $character ) {
			return $this->array_value();
		}
		if ( '{' === $character ) {
			return $this->object_value();
		}
		if ( '"' === $character || "'" === $character ) {
			return $this->string_value();
		}
		if ( preg_match( '/\G-?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?/', $this->source, $match, 0, $this->offset ) ) {
			$this->offset += strlen( $match[0] );
			return str_contains( $match[0], '.' ) || strpbrk( $match[0], 'eE' ) ? (float) $match[0] : (int) $match[0];
		}
		if ( $this->consume_word( 'new' ) ) {
			$this->require_word( 'Date' );
			$this->require( '(' );
			$parts = array();
			do {
				$parts[] = $this->expression();
			} while ( $this->consume( ',' ) );
			$this->require( ')' );
			if ( count( $parts ) < 2 || count( $parts ) > 3 || array_filter( $parts, static fn( mixed $part ): bool => ! is_int( $part ) ) ) {
				throw new RuntimeException( 'Unsupported Date constructor.' );
			}
			return sprintf( '%04d-%02d-%02d', $parts[0], $parts[1] + 1, $parts[2] ?? 1 );
		}
		foreach ( array(
			'true'  => true,
			'false' => false,
			'null'  => null,
		) as $word => $value ) {
			if ( $this->consume_word( $word ) ) {
				return $value;
			}
		}
		throw new RuntimeException( 'Unsupported JavaScript expression.' );
	}

	/** Parse an array literal. */
	private function array_value(): array {
		$this->require( '[' );
		$result = array();
		if ( $this->consume( ']' ) ) {
			return $result;
		}
		do {
			$result[] = $this->expression();
		} while ( $this->consume( ',' ) && ! $this->peek( ']' ) );
		$this->require( ']' );
		return $result;
	}

	/** Parse an object literal. */
	private function object_value(): array {
		$this->require( '{' );
		$result = array();
		if ( $this->consume( '}' ) ) {
			return $result;
		}
		do {
			$this->whitespace();
			$key = in_array( $this->source[ $this->offset ] ?? '', array( '"', "'" ), true ) ? $this->string_value() : $this->identifier();
			$this->require( ':' );
			$result[ $key ] = $this->expression();
		} while ( $this->consume( ',' ) && ! $this->peek( '}' ) );
		$this->require( '}' );
		return $result;
	}

	/**
	 * Parse a quoted string literal.
	 *
	 * @return string Parsed string.
	 * @throws RuntimeException For an unterminated string.
	 */
	private function string_value(): string {
		$quote  = $this->source[ $this->offset++ ];
		$value  = '';
		$length = strlen( $this->source );
		while ( $this->offset < $length ) {
			$character = $this->source[ $this->offset++ ];
			if ( $character === $quote ) {
				return $value;
			}
			if ( '\\' === $character ) {
				$escaped = $this->source[ $this->offset++ ] ?? '';
				$value  .= array(
					'n' => "\n",
					'r' => "\r",
					't' => "\t",
				)[ $escaped ] ?? $escaped;
			} else {
				$value .= $character;
			}
		}
		throw new RuntimeException( 'Unterminated JavaScript string.' );
	}

	/**
	 * Parse a JavaScript identifier used as an object key.
	 *
	 * @return string Parsed identifier.
	 * @throws RuntimeException When no identifier is present.
	 */
	private function identifier(): string {
		$this->whitespace();
		if ( ! preg_match( '/\G[A-Za-z_$][A-Za-z0-9_$]*/', $this->source, $match, 0, $this->offset ) ) {
			throw new RuntimeException( 'Expected object key.' );
		}
		$this->offset += strlen( $match[0] );
		return $match[0];
	}

	/** Advance over insignificant whitespace. */
	private function whitespace(): void {
		while ( isset( $this->source[ $this->offset ] ) && preg_match( '/\s/', $this->source[ $this->offset ] ) ) {
			++$this->offset;
		}
	}

	/**
	 * Check for a token at the current offset.
	 *
	 * @param string $token Token to inspect.
	 * @return bool Whether the token is present.
	 */
	private function peek( string $token ): bool {
		$this->whitespace();
		return substr( $this->source, $this->offset, strlen( $token ) ) === $token;
	}

	/**
	 * Consume a token when present.
	 *
	 * @param string $token Token to consume.
	 * @return bool Whether the token was consumed.
	 */
	private function consume( string $token ): bool {
		if ( ! $this->peek( $token ) ) {
			return false;
		}
		$this->offset += strlen( $token );
		return true;
	}

	/**
	 * Require a punctuation token.
	 *
	 * @param string $token Required token.
	 * @throws RuntimeException When the token is absent.
	 */
	private function require( string $token ): void {
		if ( ! $this->consume( $token ) ) {
			throw new RuntimeException( 'Expected JavaScript token.' );
		}
	}

	/**
	 * Consume a complete identifier token when present.
	 *
	 * @param string $word Identifier to consume.
	 * @return bool Whether the identifier was consumed.
	 */
	private function consume_word( string $word ): bool {
		$this->whitespace();
		if ( ! preg_match( '/\G' . preg_quote( $word, '/' ) . '(?![A-Za-z0-9_$])/', $this->source, $match, 0, $this->offset ) ) {
			return false;
		}
		$this->offset += strlen( $match[0] );
		return true;
	}

	/**
	 * Require a complete identifier token.
	 *
	 * @param string $word Required identifier.
	 * @throws RuntimeException When the identifier is absent.
	 */
	private function require_word( string $word ): void {
		if ( ! $this->consume_word( $word ) ) {
			throw new RuntimeException( 'Expected JavaScript identifier.' );
		}
	}
}
