<?php
/**
 * Companion plugin integration tests.
 *
 * @package AaronCampbell_PresenterThemes
 */

use AaronCampbell\PresenterThemes\Plugin;
use Presenter\Theme;

/**
 * Verify the complete WordPress-facing companion contract.
 */
final class Plugin_Test extends Companion_Test_Case {
	/**
	 * Hooks have exact priorities and argument counts and are registered once.
	 */
	public function test_register_hooks_is_exact_and_idempotent(): void {
		global $wp_filter;

		$this->plugin->register_hooks();
		$this->plugin->register_hooks();

		foreach ( $this->hook_contract() as $hook => $contract ) {
			$callbacks = $wp_filter[ $hook ]->callbacks[ $contract['priority'] ] ?? array();
			$matches   = array_filter(
				$callbacks,
				fn( array $registered ): bool => is_array( $registered['function'] )
					&& $registered['function'][0] === $this->plugin
					&& $registered['function'][1] === $contract['method']
			);

			$this->assertCount( 1, $matches, $hook );
			$this->assertSame(
				$contract['accepted_args'],
				reset( $matches )['accepted_args'],
				$hook
			);
		}
	}

	/**
	 * The compatibility facade exposes the namespaced shared service.
	 */
	public function test_legacy_facade_returns_the_namespaced_plugin(): void {
		$this->assertInstanceOf( Plugin::class, aaronDCampbellPresenterThemes::get_instance() );
		$this->assertSame(
			aaronDCampbellPresenterThemes::get_instance(),
			aaronDCampbellPresenterThemes::get_instance()
		);
	}

	/**
	 * Both legacy theme discovery contracts resolve to this plugin's stylesheet.
	 */
	public function test_adds_one_theme_directory_and_selects_its_default_stylesheet(): void {
		$plugin_root = dirname( $this->plugin_file );
		$directories = $this->plugin->add_theme_location( array( '/existing', $plugin_root ) );

		$this->assertSame( array( '/existing', $plugin_root ), $directories );
		$this->assertSame(
			'/plugins/' . basename( $plugin_root ) . '/aaron-purple/aaron-purple.css',
			wp_normalize_path( $this->plugin->presenter_default_theme( '/ignored.css' ) )
		);
	}

	/**
	 * Public filters recover when an earlier callback violates their value contract.
	 */
	public function test_public_filters_recover_from_invalid_earlier_values(): void {
		$this->plugin->register_hooks();
		$invalid = static fn(): int => 42;

		// phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores -- Presenter 1.x public hooks.
		add_filter( 'presenter-theme-directories', $invalid, 9 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Presenter 1.x public hook.
		add_filter( 'presenter-default-theme', $invalid, 9 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Presenter 1.x public hook.
		add_filter( 'presenter-theme', $invalid, 9 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Presenter 1.x public hook.
		add_filter( 'presenter_theme_registry', $invalid, 9 );
		add_filter( 'presenter_default_theme_id', $invalid, 9 );
		add_filter( 'presenter-init-object', $invalid, 9 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Presenter 1.x public hook.
		add_filter( 'presenter-reveal-js-dependencies', $invalid, 9 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Presenter 1.x public hook.

		try {
			$this->assertContains( dirname( $this->plugin_file ), apply_filters( 'presenter-theme-directories', array() ) );
			$this->assertIsString( apply_filters( 'presenter-default-theme', '' ) );
			$this->assertIsString( apply_filters( 'presenter-theme', '' ) );
			$this->assertIsArray( apply_filters( 'presenter_theme_registry', array() ) );
			$this->assertSame( 'black', apply_filters( 'presenter_default_theme_id', 'black', array() ) );
			$this->assertIsObject( apply_filters( 'presenter-init-object', (object) array() ) );
			$this->assertSame(
				array( 'RevealChartjs' ),
				apply_filters( 'presenter-reveal-js-dependencies', array() )
			);
		} finally {
			remove_filter( 'presenter-theme-directories', $invalid, 9 );
			remove_filter( 'presenter-default-theme', $invalid, 9 );
			remove_filter( 'presenter-theme', $invalid, 9 );
			remove_filter( 'presenter_theme_registry', $invalid, 9 );
			remove_filter( 'presenter_default_theme_id', $invalid, 9 );
			remove_filter( 'presenter-init-object', $invalid, 9 );
			remove_filter( 'presenter-reveal-js-dependencies', $invalid, 9 );
		}
		// phpcs:enable WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Both themes are registered, with Aaron Purple retaining historical aliases and the default.
	 */
	public function test_registers_stable_theme_default_and_aliases(): void {
		if ( ! class_exists( Theme::class ) ) {
			$this->markTestSkipped( 'The optional sibling Presenter plugin is not available.' );
		}

		$existing = new Theme( 'existing', 'Existing', 'https://example.test/existing.css' );
		$themes   = $this->plugin->presenter_theme_registry( array( 'existing' => $existing ) );

		$this->assertSame( $existing, $themes['existing'] );
		$this->assertArrayHasKey( 'aaron-purple', $themes );
		$this->assertSame( 'aaron-purple', $themes['aaron-purple']->id() );
		$this->assertSame( 'Aaron Purple', $themes['aaron-purple']->label() );
		$this->assertSame(
			plugins_url( 'aaron-purple/aaron-purple.css', $this->plugin_file ),
			$themes['aaron-purple']->stylesheet_url()
		);
		$this->assertSame(
			array(
				'/plugins/aarondcampbell-presenter-themes/aaron-purple/aaron-purple.css',
				'/themes/aarondcampbell/presenter/aaron-purple/aaron-purple.css',
			),
			$themes['aaron-purple']->legacy_aliases()
		);
		$this->assertArrayHasKey( 'aaron-brand', $themes );
		$this->assertSame( 'aaron-brand', $themes['aaron-brand']->id() );
		$this->assertSame( 'Aaron Brand', $themes['aaron-brand']->label() );
		$this->assertSame(
			plugins_url( 'aaron-brand/aaron-brand.css', $this->plugin_file ),
			$themes['aaron-brand']->stylesheet_url()
		);
		$this->assertSame( array(), $themes['aaron-brand']->legacy_aliases() );
		$this->assertSame(
			'aaron-purple',
			$this->plugin->presenter_default_theme_id( 'black', $themes )
		);
		$this->assertSame(
			'black',
			$this->plugin->presenter_default_theme_id( 'black', array( 'existing' => $existing ) )
		);
	}

	/**
	 * Only the companion's historical stylesheet URL is rewritten.
	 */
	public function test_rewrites_only_the_historical_theme_url(): void {
		$historical = content_url( '/themes/aarondcampbell/presenter/aaron-purple/aaron-purple.css' );
		$expected   = plugins_url( 'aaron-purple/aaron-purple.css', $this->plugin_file );

		$this->assertSame( $expected, $this->plugin->presenter_theme( $historical ) );
		$this->assertSame(
			'https://themes.example.test/unrelated.css',
			$this->plugin->presenter_theme( 'https://themes.example.test/unrelated.css' )
		);
	}

	/**
	 * Presenter 1.x receives the Chart global and no optional Math dependency.
	 */
	public function test_registers_the_legacy_chart_handle_and_filters_dependencies(): void {
		$post = self::factory()->post->create_and_get(
			array( 'post_type' => 'slideshow' )
		);
		$this->set_current_post( $post );
		$this->plugin->enqueue_presentation_scripts();

		$dependencies = $this->plugin->presenter_reveal_js_dependencies(
			array( 'RevealMarkdown', 'RevealMath', 'RevealChartjs', 'RevealNotes', 'RevealMath' )
		);
		$repeated     = $this->plugin->presenter_reveal_js_dependencies( $dependencies );
		$script = wp_scripts()->query( 'RevealChartjs', 'registered' );

		$this->assertSame(
			array( 'RevealMarkdown', 'RevealNotes', 'RevealChartjs' ),
			array_values( $dependencies )
		);
		$this->assertSame( $dependencies, $repeated );
		$this->assertInstanceOf( _WP_Dependency::class, $script );
		$this->assertSame( plugins_url( 'js/chartjs-plugin.js', $this->plugin_file ), $script->src );
		$this->assertSame( array(), $script->deps );
		$this->assertSame( '1.5.0', $script->ver );
		$this->assertSame( 1, $script->extra['group'] );
	}

	/**
	 * Plain Presenter 2.0 decks do not receive the compatibility bridge.
	 */
	public function test_omits_the_native_chart_bridge_from_plain_slideshows(): void {
		$post = self::factory()->post->create_and_get(
			array( 'post_type' => 'slideshow' )
		);

		$plugins = $this->plugin->presenter_reveal_plugins(
			array( 'markdown', 'math', 'chartjs', 'notes', 'math', 'chartjs' ),
			$post
		);
		$this->set_current_post( $post );
		$this->plugin->enqueue_presentation_scripts();

		$this->assertSame( array( 'markdown', 'notes' ), $plugins );
		$this->assertTrue( wp_script_is( 'RevealChartjs', 'registered' ) );
		$this->assertFalse( wp_script_is( 'aaron-presenter-chartjs', 'registered' ) );
		$this->assertFalse( wp_script_is( 'aaron-presenter-chartjs', 'enqueued' ) );
	}

	/**
	 * Presenter 2.0 chart fragments receive one plugin ID and a deferred bridge.
	 */
	public function test_registers_the_native_chart_bridge_for_chart_fragments(): void {
		$post = self::factory()->post->create_and_get(
			array(
				'post_content' => '<p class="fragment" data-fragment-graph="marketShareChart" data-fragment-graph-dataset="projectedSeries">Show projection</p>',
				'post_type'    => 'slideshow',
			)
		);
		$this->set_current_post( $post );
		$this->plugin->enqueue_presentation_scripts();

		$plugins  = $this->plugin->presenter_reveal_plugins(
			array( 'markdown', 'math', 'chartjs', 'notes', 'math', 'chartjs' ),
			$post
		);
		$repeated = $this->plugin->presenter_reveal_plugins( $plugins, $post );
		$script = wp_scripts()->query( 'aaron-presenter-chartjs', 'registered' );

		$this->assertSame( array( 'markdown', 'notes', 'chartjs' ), $plugins );
		$this->assertSame( $plugins, $repeated );
		$this->assertInstanceOf( _WP_Dependency::class, $script );
		$this->assertSame( plugins_url( 'js/chartjs-plugin.js', $this->plugin_file ), $script->src );
		$this->assertSame( array( 'presenter-frontend' ), $script->deps );
		$this->assertSame( '1.5.0', $script->ver );
		$this->assertSame( 1, $script->extra['group'] );
		$this->assertSame( 'defer', $script->extra['strategy'] );
		$this->assertTrue( wp_script_is( 'aaron-presenter-chartjs', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'RevealChartjs', 'registered' ) );
	}

	/**
	 * An incomplete fragment contract does not load a bridge that cannot act.
	 */
	public function test_native_chart_bridge_requires_graph_and_dataset_attributes(): void {
		$post = self::factory()->post->create_and_get(
			array(
				'post_content' => '<p class="fragment" data-fragment-graph="marketShareChart">Show projection</p>',
				'post_type'    => 'slideshow',
			)
		);

		$this->assertSame(
			array( 'notes' ),
			$this->plugin->presenter_reveal_plugins( array( 'notes', 'chartjs' ), $post )
		);
		$this->assertFalse( wp_script_is( 'aaron-presenter-chartjs', 'registered' ) );
		$this->assertFalse( wp_script_is( 'aaron-presenter-chartjs', 'enqueued' ) );
	}

	/**
	 * Attribute-like text and attributes split across elements do not load the bridge.
	 */
	public function test_native_chart_bridge_ignores_false_positive_attribute_text(): void {
		$post = self::factory()->post->create_and_get(
			array(
				'post_content' => '<pre>&lt;p data-fragment-graph="demo" data-fragment-graph-dataset="series"&gt;</pre><p data-fragment-graph="demo">One</p><p data-fragment-graph-dataset="series">Two</p>',
				'post_type'    => 'slideshow',
			)
		);

		$this->assertSame(
			array( 'notes' ),
			$this->plugin->presenter_reveal_plugins( array( 'notes' ), $post )
		);
		$this->assertFalse( wp_script_is( 'aaron-presenter-chartjs', 'registered' ) );
	}

	/**
	 * The native extension filter has no effects outside slideshow routing.
	 */
	public function test_native_chart_bridge_ignores_non_slideshow_posts(): void {
		$post    = self::factory()->post->create_and_get(
			array( 'post_type' => 'post' )
		);
		$plugins = array( 'notes', 'chartjs', 'chartjs' );

		$this->assertSame( $plugins, $this->plugin->presenter_reveal_plugins( $plugins, $post ) );
		$this->assertFalse( wp_script_is( 'aaron-presenter-chartjs', 'registered' ) );
		$this->assertFalse( wp_script_is( 'aaron-presenter-chartjs', 'enqueued' ) );
	}

	/**
	 * Site defaults replace only the two transition settings.
	 */
	public function test_disables_transitions_without_losing_other_settings(): void {
		$settings = (object) array(
			'controls'             => true,
			'transition'           => 'slide',
			'backgroundTransition' => 'fade',
		);

		$filtered = $this->plugin->presenter_init_object( $settings );

		$this->assertSame( $settings, $filtered );
		$this->assertTrue( $filtered->controls );
		$this->assertSame( 'none', $filtered->transition );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Reveal.js owns this public configuration key.
		$this->assertSame( 'none', $filtered->backgroundTransition );
	}

	/**
	 * Persistent footer markup retains its visual and accessible semantics.
	 */
	public function test_outputs_the_accessible_persistent_footer(): void {
		ob_start();
		$this->plugin->presenter_reveal_footer();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'class="persistent-twitter-link"', $output );
		$this->assertStringContainsString( 'href="https://twitter.com/aaroncampbell/"', $output );
		$this->assertStringContainsString( 'class="social-icon-link"', $output );
		$this->assertStringContainsString( '<title>Twitter</title>', $output );
		$this->assertStringContainsString( '@AaronCampbell', $output );
	}

	/**
	 * Main archives are filtered only for users without site-management access.
	 */
	public function test_filters_only_main_archive_for_users_without_management_access(): void {
		global $wp_the_query;

		$previous_main_query = $wp_the_query;
		$main                = $this->slideshow_archive_query();
		$secondary           = $this->slideshow_archive_query();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WP_Query::is_main_query() requires the test fixture to own the main-query global.
		$wp_the_query = $main;

		try {
			$this->plugin->hide_password_protected_slideshows( $main );
			$this->plugin->hide_password_protected_slideshows( $secondary );

			$this->assertFalse( $main->get( 'has_password' ) );
			$this->assertSame( '', $secondary->get( 'has_password' ) );

			$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
			wp_set_current_user( $subscriber_id );
			$subscriber = $this->slideshow_archive_query();
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WP_Query::is_main_query() requires the test fixture to own the main-query global.
			$wp_the_query = $subscriber;
			$this->plugin->hide_password_protected_slideshows( $subscriber );
			$this->assertFalse( $subscriber->get( 'has_password' ) );

			$administrator_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
			wp_set_current_user( $administrator_id );
			$administrator = $this->slideshow_archive_query();
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WP_Query::is_main_query() requires the test fixture to own the main-query global.
			$wp_the_query = $administrator;
			$this->plugin->hide_password_protected_slideshows( $administrator );
			$this->assertSame( '', $administrator->get( 'has_password' ) );

			wp_set_current_user( 0 );
			set_current_screen( 'edit-slideshow' );
			$admin = $this->slideshow_archive_query();
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WP_Query::is_main_query() requires the test fixture to own the main-query global.
			$wp_the_query = $admin;
			$this->plugin->hide_password_protected_slideshows( $admin );
			$this->assertSame( '', $admin->get( 'has_password' ) );
		} finally {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the WordPress test suite's main query.
			$wp_the_query = $previous_main_query;
		}
	}

	/**
	 * Public integration callbacks do not persist options or authored content.
	 */
	public function test_public_integration_callbacks_do_not_write_stored_state(): void {
		global $wpdb, $wp_the_query;

		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_content' => 'Stored-state sentinel',
			)
		);
		add_post_meta( $post_id, 'stored-state-sentinel', 'unchanged' );
		$slideshow = self::factory()->post->create_and_get(
			array( 'post_type' => 'slideshow' )
		);
		$before    = $this->stored_state_snapshot();

		$this->assertNotEmpty( $this->plugin->add_theme_location( array( '/existing' ) ) );
		$this->assertNotEmpty( $this->plugin->presenter_default_theme( '/default.css' ) );
		$this->plugin->presenter_theme( 'https://themes.example.test/unrelated.css' );
		$this->plugin->presenter_theme_registry( array() );
		$this->assertSame( 'black', $this->plugin->presenter_default_theme_id( 'black', array() ) );
		$this->plugin->presenter_reveal_js_dependencies( array( 'RevealNotes' ) );
		$this->plugin->presenter_reveal_plugins(
			array( 'notes' ),
			$slideshow
		);
		$this->plugin->presenter_init_object( (object) array( 'controls' => true ) );
		ob_start();
		$this->plugin->presenter_reveal_footer();
		ob_end_clean();

		$previous_main_query = $wp_the_query;
		$query               = $this->slideshow_archive_query();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WP_Query::is_main_query() requires the test fixture to own the main-query global.
		$wp_the_query = $query;
		try {
			$this->plugin->hide_password_protected_slideshows( $query );
		} finally {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the WordPress test suite's main query.
			$wp_the_query = $previous_main_query;
		}

		$this->assertSame( $before, $this->stored_state_snapshot() );
		$this->assertSame( 'Stored-state sentinel', get_post_field( 'post_content', $post_id ) );
		$this->assertSame( 'unchanged', get_post_meta( $post_id, 'stored-state-sentinel', true ) );
	}

	/**
	 * Build a query object with slideshow-archive conditional state.
	 */
	private function slideshow_archive_query(): WP_Query {
		$query                          = new WP_Query();
		$query->is_post_type_archive    = true;
		$query->query_vars['post_type'] = 'slideshow';

		return $query;
	}

	/** Make one post authoritative for front-end enqueue tests. */
	private function set_current_post( WP_Post $post ): void {
		$GLOBALS['post'] = $post;
	}

	/**
	 * Capture tables that contain plugin options and authored post state.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private function stored_state_snapshot(): array {
		global $wpdb;

		return array(
			'options'  => $wpdb->get_results( "SELECT * FROM {$wpdb->options} ORDER BY option_id", ARRAY_A ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core table name is trusted.
			'posts'    => $wpdb->get_results( "SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core table name is trusted.
			'postmeta' => $wpdb->get_results( "SELECT * FROM {$wpdb->postmeta} ORDER BY meta_id", ARRAY_A ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core table name is trusted.
		);
	}
}
