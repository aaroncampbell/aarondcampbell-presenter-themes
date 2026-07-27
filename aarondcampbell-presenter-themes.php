<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Main plugin filename and public compatibility facade are both stable.
/**
 * Plugin Name: Aaron D. Campbell - Presenter Themes
 * Plugin URI: https://aarondcampbell.com/wordpress-plugins/presenter/
 * Description: Aaron's private themes and presentation customizations for Presenter.
 * Version: 1.3.0
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Requires Plugins: presenter
 * Author: Aaron D. Campbell
 * Author URI: https://aarondcampbell.com/
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: aarondcampbell-presenter-themes
 *
 * @package AaronCampbellPresenterThemes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-plugin.php';
require_once __DIR__ . '/includes/class-legacy-javascript-literal-parser.php';
require_once __DIR__ . '/includes/class-legacy-google-chart-converter.php';

// phpcs:disable PEAR.NamingConventions.ValidClassName.StartWithCapital -- Public compatibility facade retained for Presenter integrations.
/**
 * Preserve the historical integration entry point while consumers migrate.
 *
 * The facade owns the only shared instance. The namespaced Plugin remains an
 * ordinary object whose behavior can be tested without global singleton state.
 *
 * @deprecated 2.0.0 Use AaronCampbell\PresenterThemes\Plugin directly.
 */
final class aaronDCampbellPresenterThemes {
	/**
	 * Shared compatibility instance.
	 *
	 * @var \AaronCampbell\PresenterThemes\Plugin|null
	 */
	private static ?\AaronCampbell\PresenterThemes\Plugin $instance = null;

	/**
	 * Get the shared plugin instance used by legacy integrations.
	 *
	 * @return \AaronCampbell\PresenterThemes\Plugin Plugin instance.
	 */
	public static function get_instance(): \AaronCampbell\PresenterThemes\Plugin {
		if ( null === self::$instance ) {
			self::$instance = new \AaronCampbell\PresenterThemes\Plugin( __FILE__ );
		}

		return self::$instance;
	}
}
// phpcs:enable PEAR.NamingConventions.ValidClassName.StartWithCapital

aaronDCampbellPresenterThemes::get_instance()->register_hooks();
