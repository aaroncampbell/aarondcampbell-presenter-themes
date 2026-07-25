<?php
/**
 * Shared companion-plugin integration test case.
 *
 * @package AaronCampbell_PresenterThemes
 */

use AaronCampbell\PresenterThemes\Plugin;

/**
 * Base class for companion-plugin integration tests.
 */
abstract class Companion_Test_Case extends WP_UnitTestCase {
	/**
	 * Companion plugin entry file.
	 *
	 * @var string
	 */
	protected string $plugin_file;

	/**
	 * Fresh plugin service under test.
	 *
	 * @var Plugin
	 */
	protected Plugin $plugin;

	/**
	 * Create an isolated service without registering its hooks.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->plugin_file = dirname( __DIR__, 2 ) . '/aarondcampbell-presenter-themes.php';
		$this->remove_plugin_hooks( aaronDCampbellPresenterThemes::get_instance() );
		$this->plugin = new Plugin( $this->plugin_file );
	}

	/**
	 * Remove request-global state installed by a test.
	 */
	public function tear_down(): void {
		$this->remove_plugin_hooks( $this->plugin );
		wp_dequeue_script( 'RevealChartjs' );
		wp_deregister_script( 'RevealChartjs' );
		wp_set_current_user( 0 );

		$GLOBALS['current_screen'] = null;

		parent::tear_down();
	}

	/**
	 * Remove every public hook owned by one plugin service.
	 *
	 * @param Plugin $plugin Plugin service.
	 */
	protected function remove_plugin_hooks( Plugin $plugin ): void {
		foreach ( $this->hook_contract() as $hook => $contract ) {
			remove_filter( $hook, array( $plugin, $contract['method'] ), $contract['priority'] );
		}
	}

	/**
	 * Expected public hook contract.
	 *
	 * @return array<string, array{method: string, priority: int, accepted_args: int}>
	 */
	protected function hook_contract(): array {
		return array(
			'presenter-theme-directories'      => array(
				'method'        => 'add_theme_location',
				'priority'      => 10,
				'accepted_args' => 2,
			),
			'presenter-reveal-footer'          => array(
				'method'        => 'presenter_reveal_footer',
				'priority'      => 10,
				'accepted_args' => 0,
			),
			'presenter-default-theme'          => array(
				'method'        => 'presenter_default_theme',
				'priority'      => 10,
				'accepted_args' => 1,
			),
			'presenter-theme'                  => array(
				'method'        => 'presenter_theme',
				'priority'      => 10,
				'accepted_args' => 1,
			),
			'presenter_theme_registry'         => array(
				'method'        => 'presenter_theme_registry',
				'priority'      => 10,
				'accepted_args' => 1,
			),
			'presenter_default_theme_id'       => array(
				'method'        => 'presenter_default_theme_id',
				'priority'      => 10,
				'accepted_args' => 2,
			),
			'presenter-init-object'            => array(
				'method'        => 'presenter_init_object',
				'priority'      => 10,
				'accepted_args' => 1,
			),
			'presenter-reveal-js-dependencies' => array(
				'method'        => 'presenter_reveal_js_dependencies',
				'priority'      => 10,
				'accepted_args' => 1,
			),
			'pre_get_posts'                    => array(
				'method'        => 'hide_password_protected_slideshows',
				'priority'      => 10,
				'accepted_args' => 1,
			),
		);
	}
}
