<?php
/**
 * Bootstrap the companion-plugin WordPress integration tests.
 *
 * @package AaronCampbell_PresenterThemes
 */

$presenter_themes_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $presenter_themes_tests_dir ) {
	$presenter_themes_tests_dir = '/tmp/wordpress-tests-lib';
}

if ( ! file_exists( $presenter_themes_tests_dir . '/includes/functions.php' ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- WordPress is not loaded yet.
	fwrite( STDERR, "WordPress test library not found. Set WP_TESTS_DIR.\n" );
	exit( 1 );
}

require_once $presenter_themes_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		$plugin_root    = dirname( __DIR__, 2 );
		$presenter_file = dirname( $plugin_root ) . '/presenter/presenter.php';

		if ( is_readable( $presenter_file ) ) {
			require_once $presenter_file;
		}

		require_once $plugin_root . '/aarondcampbell-presenter-themes.php';
	}
);

require $presenter_themes_tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/Companion_Test_Case.php';
