<?php

namespace DemocracyPoll;

use DemocracyPoll\Helpers\Messages;
use DemocracyPoll\Mocks\Testable_Plugin;
use WP_Mock;

class Plugin__Test extends DemocTestCase {

	public function tearDown(): void {
		remove_all_filters( 'dem_super_access' );
		remove_all_filters( 'dem_cachegear_status' );

		parent::tearDown();
	}

	/**
	 * @covers Plugin::__construct()
	 */
	public function test__constructor_sets_plugin_data_and_dependencies(): void {
		$main_file = THIS_PLUG_ROOT_DIR . '/democracy.php';
		$admin_page_url = WP_ROOT_URL . '/wp-admin/options-general.php?page=democracy-poll';

		WP_Mock::userFunction( 'admin_url' )->once()
			->with( 'options-general.php?page=democracy-poll' )
			->andReturn( $admin_page_url );

		$plugin = new Plugin( $main_file );

		$this->assertSame( get_file_data( $main_file, [ 'ver' => 'Version' ] )['ver'], $plugin->ver );
		$this->assertSame( THIS_PLUG_ROOT_DIR, $plugin->dir );
		$this->assertSame( plugins_url( '', $main_file ), $plugin->url );
		$this->assertSame( $admin_page_url, $plugin->admin_page_url );
		$this->assertInstanceOf( Options::class, $plugin->opt );
		$this->assertInstanceOf( Messages::class, $plugin->msg );
		$this->assertInstanceOf( Plugin_Initor::class, $plugin->initor );
	}

	/**
	 * @covers Plugin::set_access_caps()
	 */
	public function test__set_access_caps_grants_both_access_levels_to_administrator(): void {
		$plugin = new Testable_Plugin( [ 'access_roles' => [ 'editor' ] ] );

		WP_Mock::userFunction( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( true );
		add_filter( 'dem_super_access', '__return_false' );

		$plugin->set_access_caps();

		$this->assertTrue( $plugin->admin_access );
		$this->assertTrue( $plugin->super_access );
	}

	/**
	 * @covers Plugin::set_access_caps()
	 */
	public function test__set_access_caps_grants_admin_access_to_configured_role(): void {
		$plugin = new Testable_Plugin( [ 'access_roles' => [ 'editor' ] ] );

		WP_Mock::userFunction( 'current_user_can' )->once()->andReturn( false );
		WP_Mock::userFunction( 'wp_get_current_user' )->once()
			->andReturn( (object) [ 'roles' => [ 'author', 'editor' ] ] );

		add_filter( 'dem_super_access', '__return_false' );

		$plugin->set_access_caps();

		$this->assertTrue( $plugin->admin_access );
		$this->assertTrue( $plugin->super_access );
	}

	/**
	 * @covers Plugin::set_access_caps()
	 */
	public function test__set_access_caps_filter_can_grant_only_super_access(): void {
		$plugin = new Testable_Plugin( [ 'access_roles' => [ 'editor' ] ] );

		WP_Mock::userFunction( 'current_user_can' )->once()->andReturn( false );
		WP_Mock::userFunction( 'wp_get_current_user' )->once()
			->andReturn( (object) [ 'roles' => [ 'subscriber' ] ] );

		add_filter( 'dem_super_access', '__return_true' );

		$plugin->set_access_caps();

		$this->assertFalse( $plugin->admin_access );
		$this->assertTrue( $plugin->super_access );
	}

	/**
	 * @covers Plugin::set_is_cachegear_on()
	 */
	public function test__set_is_cachegear_on_honors_forced_option(): void {
		$plugin = new Testable_Plugin( [ 'force_cachegear' => 1 ] );

		$plugin->set_is_cachegear_on();

		$this->assertTrue( $plugin->is_cachegear_on );
	}

	/**
	 * @covers Plugin::set_is_cachegear_on()
	 */
	public function test__set_is_cachegear_on_uses_filtered_status(): void {
		$plugin = new Testable_Plugin();

		add_filter( 'dem_cachegear_status', '__return_false' );

		$plugin->set_is_cachegear_on();

		$this->assertFalse( $plugin->is_cachegear_on );
	}

	/**
	 * @covers Plugin::set_is_cachegear_on()
	 * @dataProvider cachegear_status_provider
	 */
	public function test__set_is_cachegear_on_casts_filtered_status( $status, bool $expected ): void {
		$plugin = new Testable_Plugin();

		add_filter( 'dem_cachegear_status', static fn() => $status );

		$plugin->set_is_cachegear_on();

		$this->assertSame( $expected, $plugin->is_cachegear_on );
	}

	public static function cachegear_status_provider(): array {
		return [
			'truthy value' => [ 1, true ],
			'falsy value'  => [ 0, false ],
		];
	}

}
