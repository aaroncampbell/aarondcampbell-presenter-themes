<?php
/**
 * Plugin Name: Aaron D. Campbell - Presenter Themes
 * Plugin URI: http://aarondcampbell.com/wordpress-plugins/presenter/
 * Description: Aaron's themes for Presenter
 * Version: 1.0.0
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
	}

	public function add_theme_location( $presenter_theme_directories ) {
		$presenter_theme_directories[] = __DIR__;
		return $presenter_theme_directories;
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
