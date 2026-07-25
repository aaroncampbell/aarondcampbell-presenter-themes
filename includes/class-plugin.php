<?php
/**
 * Companion plugin integration.
 *
 * @package AaronCampbellPresenterThemes
 */

namespace AaronCampbell\PresenterThemes;

use Presenter\Theme;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Aaron's private Presenter theme and site customizations.
 */
final class Plugin {
	/** Stable theme identifier shared with Presenter. */
	private const THEME_ID = 'aaron-purple';

	/** Human-readable theme label. */
	private const THEME_LABEL = 'Aaron Purple';

	/** Theme stylesheet relative to this plugin. */
	private const THEME_STYLESHEET = 'aaron-purple/aaron-purple.css';

	/** Legacy Reveal plugin script handle. */
	private const CHART_SCRIPT_HANDLE = 'RevealChartjs';

	/** Legacy Reveal plugin script version. */
	private const CHART_SCRIPT_VERSION = '1.1.0';

	/**
	 * Companion plugin entry file.
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Whether WordPress hooks have already been registered.
	 *
	 * @var bool
	 */
	private bool $registered = false;

	/**
	 * Create the plugin integration.
	 *
	 * @param string $plugin_file Absolute companion plugin entry file.
	 */
	public function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
	}

	/**
	 * Register WordPress hooks exactly once.
	 */
	public function register_hooks(): void {
		if ( $this->registered ) {
			return;
		}

		add_filter( 'presenter-theme-directories', array( $this, 'add_theme_location' ), 10, 2 );
		add_action( 'presenter-reveal-footer', array( $this, 'presenter_reveal_footer' ), 10, 0 );
		add_filter( 'presenter-default-theme', array( $this, 'presenter_default_theme' ), 10, 1 );
		add_filter( 'presenter-theme', array( $this, 'presenter_theme' ), 10, 1 );
		add_filter( 'presenter_theme_registry', array( $this, 'presenter_theme_registry' ), 10, 1 );
		add_filter( 'presenter_default_theme_id', array( $this, 'presenter_default_theme_id' ), 10, 2 );
		add_filter( 'presenter-init-object', array( $this, 'presenter_init_object' ), 10, 1 );
		add_filter( 'presenter-reveal-js-dependencies', array( $this, 'presenter_reveal_js_dependencies' ), 10, 1 );
		add_action( 'pre_get_posts', array( $this, 'hide_password_protected_slideshows' ), 10, 1 );

		$this->registered = true;
	}

	/**
	 * Add this plugin to Presenter 1.x's theme discovery roots.
	 *
	 * @param array<int, string> $theme_directories Existing theme directories.
	 * @return array<int, string> Filtered theme directories.
	 */
	public function add_theme_location( array $theme_directories ): array {
		$plugin_directory = dirname( $this->plugin_file );

		if ( ! in_array( $plugin_directory, $theme_directories, true ) ) {
			$theme_directories[] = $plugin_directory;
		}

		return $theme_directories;
	}

	/**
	 * Select Aaron Purple as Presenter 1.x's default stylesheet.
	 *
	 * @param string $theme Existing default stylesheet path.
	 * @return string Aaron Purple's wp-content-relative stylesheet path.
	 */
	public function presenter_default_theme( string $theme ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- The filter intentionally replaces Presenter's default.
		return str_replace(
			WP_CONTENT_DIR,
			'',
			plugin_dir_path( $this->plugin_file ) . self::THEME_STYLESHEET
		);
	}

	/**
	 * Register Aaron Purple with Presenter 2.0's stable theme registry.
	 *
	 * @param array<string, object> $themes Presenter themes keyed by stable ID.
	 * @return array<string, object> Filtered Presenter themes.
	 */
	public function presenter_theme_registry( array $themes ): array {
		if ( ! class_exists( Theme::class ) ) {
			return $themes;
		}

		$theme = new Theme(
			self::THEME_ID,
			self::THEME_LABEL,
			plugins_url( self::THEME_STYLESHEET, $this->plugin_file ),
			array(
				'/plugins/aarondcampbell-presenter-themes/aaron-purple/aaron-purple.css',
				'/themes/aarondcampbell/presenter/aaron-purple/aaron-purple.css',
			)
		);

		$themes[ $theme->id() ] = $theme;

		return $themes;
	}

	/**
	 * Select Aaron Purple as Presenter 2.0's site default when registered.
	 *
	 * @param string                $theme_id Current default theme ID.
	 * @param array<string, object> $themes   Presenter themes keyed by stable ID.
	 * @return string Filtered default theme ID.
	 */
	public function presenter_default_theme_id( string $theme_id, array $themes ): string {
		return isset( $themes[ self::THEME_ID ] ) ? self::THEME_ID : $theme_id;
	}

	/**
	 * Retain the legacy Chart plugin dependency and remove Reveal Math.
	 *
	 * @param array<int, string> $reveal_js_dependencies Reveal script handles.
	 * @return array<int, string> Filtered Reveal script handles.
	 */
	public function presenter_reveal_js_dependencies( array $reveal_js_dependencies ): array {
		wp_register_script(
			self::CHART_SCRIPT_HANDLE,
			plugins_url( 'js/chartjs-plugin.js', $this->plugin_file ),
			array(),
			self::CHART_SCRIPT_VERSION,
			true
		);
		return array_merge(
			array_values(
				array_filter(
					$reveal_js_dependencies,
					static fn( string $handle ): bool => ! in_array( $handle, array( 'RevealMath', self::CHART_SCRIPT_HANDLE ), true )
				)
			),
			array( self::CHART_SCRIPT_HANDLE )
		);
	}

	/**
	 * Rewrite only Aaron Purple's historical theme URL to its plugin location.
	 *
	 * @param string $theme Current theme URL.
	 * @return string Filtered theme URL.
	 */
	public function presenter_theme( string $theme ): string {
		$historical_url = content_url( '/themes/aarondcampbell/presenter/aaron-purple/aaron-purple.css' );

		return $historical_url === $theme
			? plugins_url( self::THEME_STYLESHEET, $this->plugin_file )
			: $theme;
	}

	/**
	 * Render Aaron's persistent presentation footer.
	 */
	public function presenter_reveal_footer(): void {
		?>
		<p class="persistent-twitter-link">
			<a href="https://twitter.com/aaroncampbell/" class="social-icon-link">
				<svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 20 20">
					<title>Twitter</title>
					<path d="M18.94 4.46c-0.49 0.73-1.11 1.38-1.83 1.9 0.010 0.15 0.010 0.31 0.010 0.47 0 4.85-3.69 10.44-10.43 10.44-2.070 0-4-0.61-5.63-1.65 0.29 0.030 0.58 0.050 0.88 0.050 1.72 0 3.3-0.59 4.55-1.57-1.6-0.030-2.95-1.090-3.42-2.55 0.22 0.040 0.45 0.070 0.69 0.070 0.33 0 0.66-0.050 0.96-0.13-1.67-0.34-2.94-1.82-2.94-3.6v-0.040c0.5 0.27 1.060 0.44 1.66 0.46-0.98-0.66-1.63-1.78-1.63-3.060 0-0.67 0.18-1.3 0.5-1.84 1.81 2.22 4.51 3.68 7.56 3.83-0.060-0.27-0.1-0.55-0.1-0.84 0-2.020 1.65-3.66 3.67-3.66 1.060 0 2.010 0.44 2.68 1.16 0.83-0.17 1.62-0.47 2.33-0.89-0.28 0.85-0.86 1.57-1.62 2.020 0.75-0.080 1.45-0.28 2.11-0.57z"/>
				</svg> @AaronCampbell
			</a>
		</p>
		<?php
	}

	/**
	 * Disable foreground and background transitions by default.
	 *
	 * @param object $reveal_initialize_object Reveal initialization settings.
	 * @return object Filtered Reveal initialization settings.
	 */
	public function presenter_init_object( object $reveal_initialize_object ): object {
		$reveal_initialize_object->transition = 'none';
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Reveal.js owns this public configuration key.
		$reveal_initialize_object->backgroundTransition = 'none';

		return $reveal_initialize_object;
	}

	/**
	 * Hide password-protected decks from the main slideshow archive for users
	 * without site-management access.
	 *
	 * @param WP_Query $query Query about to run.
	 */
	public function hide_password_protected_slideshows( WP_Query $query ): void {
		if (
			is_admin() ||
			current_user_can( 'manage_options' ) ||
			! $query->is_main_query() ||
			! $query->is_post_type_archive( 'slideshow' )
		) {
			return;
		}

		$query->set( 'has_password', false );
	}
}
