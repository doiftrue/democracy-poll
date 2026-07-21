<?php

namespace DemocracyPoll;

use DemocracyPoll\System\Upgrader;
use Mockery;
use RuntimeException;
use WP_Mock;

class Upgrader_Wpdb__Double {

	public string $democracy_q = 'wp_democracy_q';
	public string $democracy_a = 'wp_democracy_a';
	public string $democracy_log = 'wp_democracy_log';

	public function get_results( string $query ): array {
		if( str_contains( $query, $this->democracy_q ) ){
			return [
				'end'  => (object) [],
				'note' => (object) [],
			];
		}

		if( str_contains( $query, $this->democracy_log ) ){
			return [
				'aids'        => (object) [],
				'fingerprint' => (object) [],
			];
		}

		return [];
	}

}

class Upgrader__Test extends DemocTestCase {

	private Plugin $plugin;

	public function setUp(): void {
		parent::setUp();

		$this->plugin = Mockery::mock( Plugin::class );
		$this->plugin->ver = '2.0';
		$GLOBALS['wpdb'] = new Upgrader_Wpdb__Double();
	}

	public function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/**
	 * @covers Upgrader::upgrade()
	 */
	public function test__upgrade_writes_current_version_after_migrations(): void {
		$this->mock_version_checks( '1.0' );
		$this->mock_new_lock();
		$this->mock_migration_options();
		WP_Mock::userFunction( 'update_option' )
			->with( Upgrader::VER_OPT_NAME, '2.0' )
			->once();
		WP_Mock::userFunction( 'delete_option' )
			->with( 'democracy_upgrade_lock' )
			->once();

		$css = Mockery::mock( Options_CSS::class );
		$css->shouldReceive( 'regenerate_democracy_css' )->once()->with( null );

		$this->upgrader( $css )->upgrade();
		$this->addToAssertionCount( 1 );
	}

	/**
	 * @covers Upgrader::upgrade_force()
	 * @covers Upgrader::upgrade()
	 */
	public function test__force_upgrade_runs_migrations_when_version_is_current(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( Upgrader::VER_OPT_NAME )
			->andReturn( '2.0', '0.1' );
		$this->mock_new_lock();
		$this->mock_migration_options();
		WP_Mock::userFunction( 'update_option' )
			->with( Upgrader::VER_OPT_NAME, '0.1' )
			->once()
			->ordered();
		WP_Mock::userFunction( 'update_option' )
			->with( Upgrader::VER_OPT_NAME, '2.0' )
			->once()
			->ordered();
		WP_Mock::userFunction( 'delete_option' )
			->with( 'democracy_upgrade_lock' )
			->once();

		$css = Mockery::mock( Options_CSS::class );
		$css->shouldReceive( 'regenerate_democracy_css' )->once()->with( null );

		$this->upgrader( $css )->upgrade_force();
		$this->addToAssertionCount( 1 );
	}

	/**
	 * @covers Upgrader::upgrade()
	 */
	public function test__upgrade_does_not_run_when_another_request_holds_the_lock(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( Upgrader::VER_OPT_NAME )
			->andReturn( '1.0' );
		WP_Mock::userFunction( 'add_option' )
			->with( 'democracy_upgrade_lock', Mockery::type( 'int' ), '', false )
			->andReturn( false );
		WP_Mock::userFunction( 'get_option' )
			->with( 'democracy_upgrade_lock' )
			->andReturn( PHP_INT_MAX );

		$css = Mockery::mock( Options_CSS::class );
		$css->shouldNotReceive( 'regenerate_democracy_css' );

		$this->upgrader( $css )->upgrade();
		$this->addToAssertionCount( 1 );
	}

	/**
	 * @covers Upgrader::upgrade()
	 */
	public function test__upgrade_replaces_a_stale_lock(): void {
		$this->mock_version_checks( '1.0' );
		WP_Mock::userFunction( 'add_option' )
			->with( 'democracy_upgrade_lock', Mockery::type( 'int' ), '', false )
			->andReturn( false, true );
		WP_Mock::userFunction( 'get_option' )
			->with( 'democracy_upgrade_lock' )
			->andReturn( 0 );
		$this->mock_migration_options();
		WP_Mock::userFunction( 'delete_option' )
			->with( 'democracy_upgrade_lock' )
			->twice();
		WP_Mock::userFunction( 'update_option' )
			->with( Upgrader::VER_OPT_NAME, '2.0' )
			->once();

		$css = Mockery::mock( Options_CSS::class );
		$css->shouldReceive( 'regenerate_democracy_css' )->once()->with( null );

		$this->upgrader( $css )->upgrade();
		$this->addToAssertionCount( 1 );
	}

	/**
	 * @covers Upgrader::upgrade()
	 */
	public function test__upgrade_does_not_mark_version_current_when_migration_fails(): void {
		$this->mock_version_checks( '1.0' );
		$this->mock_new_lock();
		WP_Mock::userFunction( 'update_option' )
			->with( Upgrader::VER_OPT_NAME, '2.0' )
			->never();
		WP_Mock::userFunction( 'delete_option' )
			->with( 'democracy_upgrade_lock' )
			->once();

		$css = Mockery::mock( Options_CSS::class );
		$css->shouldReceive( 'regenerate_democracy_css' )
			->once()
			->with( null )
			->andThrow( new RuntimeException( 'CSS generation failed' ) );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'CSS generation failed' );

		$this->upgrader( $css )->upgrade();
		$this->addToAssertionCount( 1 );
	}

	private function upgrader( Options_CSS $css ): Upgrader {
		return new Upgrader( $this->plugin, $css );
	}

	private function mock_version_checks( string $old_version ): void {
		WP_Mock::userFunction( 'get_option' )
			->with( Upgrader::VER_OPT_NAME )
			->andReturn( $old_version );
	}

	private function mock_new_lock(): void {
		WP_Mock::userFunction( 'add_option' )
			->with( 'democracy_upgrade_lock', Mockery::type( 'int' ), '', false )
			->andReturn( true );
	}

	private function mock_migration_options(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( Options::OPT_NAME, [] )
			->andReturn( [] );
	}

}
