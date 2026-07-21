<?php

namespace DemocracyPoll;

use WP_Mock;

class Plugin_Lifecycle_Wpdb__Double {

	public string $prefix = 'wp_';
	public string $democracy_q = '';
	public string $democracy_a = '';
	public string $democracy_log = '';

}

class Plugin_Lifecycle__Test extends DemocTestCase {

	/**
	 * @coversNothing
	 */
	public function test__container_autowires_plugin_initor(): void {
		$initor = container()->get( System\Plugin_Initor::class );

		$this->assertInstanceOf( System\Plugin_Initor::class, $initor );
		$this->assertSame( $initor, container()->get( System\Plugin_Initor::class ) );
	}

	/**
	 * @coversNothing
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__after_setup_theme_runs_the_full_plugin_initialization_flow(): void {
		$GLOBALS['wpdb'] = new Plugin_Lifecycle_Wpdb__Double();
		WP_Mock::userFunction( 'get_option' )
			->with( Options::OPT_NAME, [] )
			->andReturn( [ 'use_widget' => 0, 'toolbar_menu' => 0 ] );
		WP_Mock::userFunction( 'is_multisite' )->andReturn( false );
		WP_Mock::userFunction( 'current_user_can' )->andReturn( false );
		WP_Mock::userFunction( 'wp_get_current_user' )->andReturn( (object) [ 'roles' => [ 'subscriber' ] ] );
		WP_Mock::userFunction( 'load_plugin_textdomain' )->andReturn( true );

		do_action( 'after_setup_theme' );

		$this->assertInstanceOf( System\Plugin_Initor::class, container()->get( System\Plugin_Initor::class ) );
		$this->assertSame( 'wp_democracy_q', $GLOBALS['wpdb']->democracy_q );
		$this->assertSame( 'wp_democracy_a', $GLOBALS['wpdb']->democracy_a );
		$this->assertSame( 'wp_democracy_log', $GLOBALS['wpdb']->democracy_log );
		$this->assertTrue( shortcode_exists( 'democracy' ) );
		$this->assertSame(
			10,
			has_action( 'wp_ajax_dem_ajax', [ container()->get( Poll_Ajax::class ), 'ajax_request_handler' ] )
		);
	}

	/**
	 * @coversNothing
	 */
	public function test__plugin_bootstrap_registers_its_activator(): void {
		$hook = 'activate_' . plugin_basename( THIS_PLUG_ROOT_DIR . '/democracy.php' );

		$this->assertSame( 10, has_action( $hook, [ System\Activator::class, 'activate' ] ) );
	}

}
