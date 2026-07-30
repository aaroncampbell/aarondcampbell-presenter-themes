<?php
/**
 * Companion plugin integration.
 *
 * @package AaronCampbellPresenterThemes
 */

namespace AaronCampbell\PresenterThemes;

use Presenter\Theme;
use WP_Post;
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

	/** Stable Aaron Brand theme identifier shared with Presenter. */
	private const BRAND_THEME_ID = 'aaron-brand';

	/** Human-readable Aaron Brand theme label. */
	private const BRAND_THEME_LABEL = 'Aaron Brand';

	/** Aaron Brand stylesheet relative to this plugin. */
	private const BRAND_THEME_STYLESHEET = 'aaron-brand/aaron-brand.css';

	/** Legacy Reveal plugin script handle. */
	private const CHART_SCRIPT_HANDLE = 'RevealChartjs';

	/** Native Presenter script handle for the Chart plugin bridge. */
	private const NATIVE_CHART_SCRIPT_HANDLE = 'aaron-presenter-chartjs';

	/** Reveal plugin identifier shared with the browser bridge. */
	private const CHART_PLUGIN_ID = 'chartjs';

	/** Companion release and shared Chart bridge asset version. */
	private const VERSION = '1.5.0';

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

		add_filter( 'presenter-theme-directories', array( $this, 'add_theme_location' ), 10, 1 );
		add_action( 'presenter-reveal-footer', array( $this, 'presenter_reveal_footer' ), 10, 0 );
		add_action( 'presenter_editor_preview_footer', array( $this, 'presenter_reveal_footer' ), 10, 0 );
		add_filter( 'presenter-default-theme', array( $this, 'presenter_default_theme' ), 10, 1 );
		add_filter( 'presenter-theme', array( $this, 'presenter_theme' ), 10, 1 );
		add_filter( 'presenter_theme_registry', array( $this, 'presenter_theme_registry' ), 10, 1 );
		add_filter( 'presenter_default_theme_id', array( $this, 'presenter_default_theme_id' ), 10, 2 );
		add_filter( 'presenter-init-object', array( $this, 'presenter_init_object' ), 10, 1 );
		add_filter( 'presenter-reveal-js-dependencies', array( $this, 'presenter_reveal_js_dependencies' ), 10, 1 );
		add_filter( 'presenter_reveal_plugins', array( $this, 'presenter_reveal_plugins' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_presentation_scripts' ), 20, 0 );
		add_filter( 'presenter_migration_slide_blocks', array( $this, 'convert_legacy_google_charts' ), 10, 2 );
		add_filter( 'presenter_migration_slide_blocks', array( $this, 'convert_legacy_chartjs' ), 20, 2 );
		add_action( 'pre_get_posts', array( $this, 'hide_password_protected_slideshows' ), 10, 1 );

		$this->registered = true;
	}

	/**
	 * Convert complete, recognized Google Chart slides during Presenter migration.
	 *
	 * @param mixed  $blocks  Earlier converter result.
	 * @param string $content Complete legacy slide HTML.
	 * @return mixed Converted blocks or the preceding value.
	 */
	public function convert_legacy_google_charts( mixed $blocks, string $content ): mixed {
		return ( new Legacy_Google_Chart_Converter() )->convert( $blocks, $content );
	}

	/**
	 * Convert complete, recognized inline Chart.js slides during migration.
	 *
	 * @param mixed  $blocks  Earlier converter result.
	 * @param string $content Complete legacy slide HTML.
	 * @return mixed Converted blocks or the preceding value.
	 */
	public function convert_legacy_chartjs( mixed $blocks, string $content ): mixed {
		return ( new Legacy_Chartjs_Converter() )->convert( $blocks, $content );
	}

	/**
	 * Add this plugin to Presenter 1.x's theme discovery roots.
	 *
	 * @param mixed $theme_directories Existing theme directories.
	 * @return array<int, string> Filtered theme directories.
	 */
	public function add_theme_location( mixed $theme_directories ): array {
		$theme_directories = is_array( $theme_directories )
			? array_values( array_filter( $theme_directories, 'is_string' ) )
			: array();
		$plugin_directory  = dirname( $this->plugin_file );

		if ( ! in_array( $plugin_directory, $theme_directories, true ) ) {
			$theme_directories[] = $plugin_directory;
		}

		return $theme_directories;
	}

	/**
	 * Select Aaron Purple as Presenter 1.x's default stylesheet.
	 *
	 * @param mixed $theme Existing default stylesheet path.
	 * @return string Aaron Purple's wp-content-relative stylesheet path.
	 */
	public function presenter_default_theme( mixed $theme ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- The filter intentionally replaces Presenter's default.
		return str_replace(
			WP_CONTENT_DIR,
			'',
			plugin_dir_path( $this->plugin_file ) . self::THEME_STYLESHEET
		);
	}

	/**
	 * Register Aaron's themes with Presenter 2.0's stable theme registry.
	 *
	 * @param mixed $themes Presenter themes keyed by stable ID.
	 * @return array<string, object> Filtered Presenter themes.
	 */
	public function presenter_theme_registry( mixed $themes ): array {
		$themes = is_array( $themes ) ? $themes : array();

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

		$brand_theme = new Theme(
			self::BRAND_THEME_ID,
			self::BRAND_THEME_LABEL,
			plugins_url( self::BRAND_THEME_STYLESHEET, $this->plugin_file )
		);

		$themes[ $brand_theme->id() ] = $brand_theme;

		return $themes;
	}

	/**
	 * Select Aaron Purple as Presenter 2.0's site default when registered.
	 *
	 * @param mixed $theme_id Current default theme ID.
	 * @param mixed $themes   Presenter themes keyed by stable ID.
	 * @return string Filtered default theme ID.
	 */
	public function presenter_default_theme_id( mixed $theme_id, mixed $themes ): string {
		$theme_id = is_string( $theme_id ) && '' !== $theme_id ? $theme_id : 'black';
		$themes   = is_array( $themes ) ? $themes : array();

		return isset( $themes[ self::THEME_ID ] ) ? self::THEME_ID : $theme_id;
	}

	/**
	 * Retain the legacy Chart plugin dependency and remove Reveal Math.
	 *
	 * @param mixed $reveal_js_dependencies Reveal script handles.
	 * @return array<int, string> Filtered Reveal script handles.
	 */
	public function presenter_reveal_js_dependencies( mixed $reveal_js_dependencies ): array {
		$reveal_js_dependencies          = is_array( $reveal_js_dependencies )
			? array_values( array_filter( $reveal_js_dependencies, 'is_string' ) )
			: array();
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
	 * Register and configure the Chart bridge for a native Presenter deck.
	 *
	 * The native handle is distinct from Presenter 1.x's dependency handle so
	 * each runtime retains the loading contract it requires. Both scripts point
	 * to the same compatibility asset.
	 *
	 * @param mixed $plugins Reveal plugin IDs.
	 * @param mixed $post    Native presentation post.
	 * @return array<int, string> Filtered Reveal plugin IDs.
	 */
	public function presenter_reveal_plugins( mixed $plugins, mixed $post ): array {
		$plugins = is_array( $plugins )
			? array_values( array_filter( $plugins, 'is_string' ) )
			: array();

		if ( ! $post instanceof WP_Post ) {
			return $plugins;
		}

		if ( 'slideshow' !== $post->post_type ) {
			return $plugins;
		}

		$plugins = array_values(
			array_filter(
				$plugins,
				static fn( string $plugin ): bool => ! in_array( $plugin, array( 'math', self::CHART_PLUGIN_ID ), true )
			)
		);

		if ( ! $this->native_chart_bridge_required( $post ) ) {
			return $plugins;
		}

		return array_merge( $plugins, array( self::CHART_PLUGIN_ID ) );
	}

	/** Register legacy assets and enqueue a required native bridge on an action. */
	public function enqueue_presentation_scripts(): void {
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			$post = get_post();
		}

		if ( ! $post instanceof WP_Post || 'slideshow' !== $post->post_type ) {
			return;
		}

		wp_register_script(
			self::CHART_SCRIPT_HANDLE,
			plugins_url( 'js/chartjs-plugin.js', $this->plugin_file ),
			array(),
			self::VERSION,
			true
		);

		if ( ! $this->native_chart_bridge_required( $post ) ) {
			return;
		}

		wp_register_script(
			self::NATIVE_CHART_SCRIPT_HANDLE,
			plugins_url( 'js/chartjs-plugin.js', $this->plugin_file ),
			array( 'presenter-frontend' ),
			self::VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_enqueue_script( self::NATIVE_CHART_SCRIPT_HANDLE );
	}

	/**
	 * Check whether a native deck contains the complete historical fragment contract.
	 *
	 * @param WP_Post $post Native presentation post.
	 * @return bool Whether the compatibility bridge is required.
	 */
	private function native_chart_bridge_required( WP_Post $post ): bool {
		$processor = new \WP_HTML_Tag_Processor( $post->post_content );

		while ( $processor->next_tag() ) {
			if (
				null !== $processor->get_attribute( 'data-fragment-graph' )
				&& null !== $processor->get_attribute( 'data-fragment-graph-dataset' )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Rewrite only Aaron Purple's historical theme URL to its plugin location.
	 *
	 * @param mixed $theme Current theme URL.
	 * @return string Filtered theme URL.
	 */
	public function presenter_theme( mixed $theme ): string {
		$historical_url = content_url( '/themes/aarondcampbell/presenter/aaron-purple/aaron-purple.css' );
		$stylesheet_url = plugins_url( self::THEME_STYLESHEET, $this->plugin_file );

		if ( ! is_string( $theme ) || '' === $theme ) {
			return $stylesheet_url;
		}

		return $historical_url === $theme
			? $stylesheet_url
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
	 * @param mixed $reveal_initialize_object Reveal initialization settings.
	 * @return object Filtered Reveal initialization settings.
	 */
	public function presenter_init_object( mixed $reveal_initialize_object ): object {
		$reveal_initialize_object = is_object( $reveal_initialize_object )
			? $reveal_initialize_object
			: (object) array();

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
