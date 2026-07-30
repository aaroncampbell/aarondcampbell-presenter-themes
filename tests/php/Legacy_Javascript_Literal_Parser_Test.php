<?php
/**
 * Legacy JavaScript literal parser tests.
 *
 * @package AaronCampbell_PresenterThemes
 */

use AaronCampbell\PresenterThemes\Legacy_Javascript_Literal_Parser;

/** Verify the inert parser rejects unbounded and malformed literals. */
final class Legacy_Javascript_Literal_Parser_Test extends Companion_Test_Case {
	/** Known JavaScript escapes and valid calendar dates remain supported. */
	public function test_parses_supported_escapes_and_valid_dates(): void {
		$value = ( new Legacy_Javascript_Literal_Parser( '["A\\nB\\u0021\\x20", new Date(2024, 1, 29)]' ) )->parse();

		$this->assertSame( array( "A\nB! ", '2024-02-29' ), $value );
	}

	/** Unknown escape syntax is rejected instead of silently changing content. */
	public function test_rejects_unknown_escape_sequences(): void {
		$this->expectException( RuntimeException::class );
		( new Legacy_Javascript_Literal_Parser( '"bad\\qescape"' ) )->parse();
	}

	/** Invalid month/day combinations cannot become malformed ISO dates. */
	public function test_rejects_invalid_dates(): void {
		$this->expectException( RuntimeException::class );
		( new Legacy_Javascript_Literal_Parser( 'new Date(2024, 99, 1)' ) )->parse();
	}

	/** Recursive containers stop at the documented finite depth. */
	public function test_rejects_excessive_nesting(): void {
		$this->expectException( RuntimeException::class );
		$source = str_repeat( '[', 65 ) . '0' . str_repeat( ']', 65 );
		( new Legacy_Javascript_Literal_Parser( $source ) )->parse();
	}

	/** Very large authored literals are rejected before parsing begins. */
	public function test_rejects_excessive_source_length(): void {
		$this->expectException( RuntimeException::class );
		( new Legacy_Javascript_Literal_Parser( '"' . str_repeat( 'x', 1000000 ) . '"' ) )->parse();
	}
}
