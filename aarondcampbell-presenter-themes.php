<?php
/**
 * Plugin Name: Aaron D. Campbell - Presenter Themes
 * Plugin URI: http://aarondcampbell.com/wordpress-plugins/presenter/
 * Description: Aaron's themes for Presenter
 * Version: 1.1.0
 * Author: Aaron D. Campbell
 * Author URI: http://aarondcampbell.com/
 * Text Domain: presenter
 */

class aaronDCampbellPresenterThemes {
	/**
	 * @var aaronDCampbellPresenterThemes - Static property to hold our singleton instance
	 */
	static $instance = false;

	/**
	 * This is our constructor, which is private to force the use of get_instance()
	 * @return void
	 */
	protected function __construct() {
		add_filter( 'presenter-theme-directories', array( $this, 'add_theme_location' ), null, 2 );
		add_filter( 'presenter-reveal-footer', array( $this, 'presenter_reveal_footer' ) );
		add_filter( 'presenter-default-theme', array( $this, 'presenter_default_theme' ) );
		add_filter( 'presenter-theme', array( $this, 'presenter_theme' ) );
		add_filter( 'presenter-init-object', array( $this, 'presenter_init_object' ) );
	}

	public function add_theme_location( $presenter_theme_directories ) {
		$presenter_theme_directories[] = __DIR__;
		return $presenter_theme_directories;
	}

	public function presenter_default_theme( string $theme ) {
		return str_replace( WP_CONTENT_DIR, '', plugin_dir_path( __FILE__ ) . 'aaron-purple/aaron-purple.css' );
	}

	// Filter theme
	public function presenter_theme( string $theme ) {
		// If the theme isn't ours in the old location, just pass it through
		if ( $theme != content_url( '/themes/aarondcampbell/presenter/aaron-purple/aaron-purple.css' ) ) {
			return $theme;
		}
		// New location of theme
		return plugin_dir_url( __file__ ) . 'aaron-purple/aaron-purple.css';
	}

	public function presenter_reveal_footer( $slide ) {

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
	 * Filters the object passed to Reveal.initialize
	 */
	public function presenter_init_object( $reveal_initialize_object ) {
		// Use no transition as the default
		$reveal_initialize_object->transition = 'none';
		$reveal_initialize_object->backgroundTransition = 'none';
		return $reveal_initialize_object;
	}

	/**
	 * Function to instantiate our class and make it a singleton
	 */
	public static function get_instance() {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}
}

// Instantiate our class
$presenter = aaronDCampbellPresenterThemes::get_instance();
