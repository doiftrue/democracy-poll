<?php

namespace DemocracyPoll;

use Mockery;
use WP_Mock;

class Options__Test extends DemocTestCase {

	public function setUp(): void {
		parent::setUp();
		$_POST = [];
	}

	public function tearDown(): void {
		$_POST = [];
		parent::tearDown();
	}

	/**
	 * @covers Options::__construct()
	 * @covers Options::get_default_options()
	 */
	public function test__default_options_are_grouped_by_type(): void {
		$defaults = ( new Options() )->get_default_options();

		$this->assertSame( 1, $defaults['main']['keep_logs'] );
		$this->assertSame( 'alternate.css', $defaults['design']['css_file_name'] );
	}

	/**
	 * @covers Options::__get()
	 * @covers Options::__isset()
	 * @covers Options::__set()
	 * @covers Options::set_opt()
	 * @covers Options::prepare_option()
	 */
	public function test__set_opt_loads_stored_values_and_appends_defaults(): void {
		WP_Mock::userFunction( 'get_option' )
			->once()
			->with( Options::OPT_NAME, [] )
			->andReturn( [
				'keep_logs'       => 0,
				'cookie_days'     => 30,
				'answs_max_height' => '0',
			] );

		$options = new Options();
		$options->set_opt();

		$this->assertSame( 0, $options->keep_logs );
		$this->assertSame( 30, $options->cookie_days );
		$this->assertSame( '', $options->answs_max_height );
		$this->assertSame( 'alternate.css', $options->css_file_name );
		$this->assertFalse( isset( $options->keep_logs ) );
		$this->assertTrue( isset( $options->cookie_days ) );
		$this->assertNull( $options->unknown_option );

		$options->cookie_days = 10;
		$this->assertSame( 30, $options->cookie_days );
	}

	/**
	 * @covers Options::set_opt()
	 * @covers Options::reset_options()
	 * @covers Options::prepare_option()
	 */
	public function test__set_opt_persists_defaults_when_stored_options_are_empty(): void {
		WP_Mock::userFunction( 'get_option' )->once()->andReturn( [] );
		WP_Mock::userFunction( 'update_option' )
			->once()
			->with( Options::OPT_NAME, Mockery::on(
				fn( $options ) => ( 365 === $options['cookie_days'] ) && ( 'alternate.css' === $options['css_file_name'] )
			) )
			->andReturn( true );

		$options = new Options();
		$options->set_opt();

		$this->assertSame( 365, $options->cookie_days );
		$this->assertSame( 'alternate.css', $options->css_file_name );
	}

	/**
	 * @covers Options::update_single_option()
	 * @covers Options::is_option_exists()
	 */
	public function test__update_single_option_updates_only_known_option(): void {
		WP_Mock::userFunction( 'get_option' )->once()->andReturn( [ 'cookie_days' => 365 ] );
		WP_Mock::userFunction( 'update_option' )
			->once()
			->with( Options::OPT_NAME, Mockery::on( fn( $options ) => ( 14 === $options['cookie_days'] ) ) )
			->andReturn( true );

		$options = new Options();
		$options->set_opt();

		$this->assertTrue( $options->update_single_option( 'cookie_days', 14 ) );
		$this->assertFalse( $options->update_single_option( 'unknown_option', 'value' ) );
	}

	/**
	 * @covers Options::reset_options()
	 */
	public function test__reset_main_options_preserves_design_values(): void {
		WP_Mock::userFunction( 'get_option' )->once()->andReturn( [
			'cookie_days'  => 7,
			'css_file_name' => 'custom.css',
		] );
		WP_Mock::userFunction( 'update_option' )
			->once()
			->with( Options::OPT_NAME, Mockery::on(
				fn( $options ) => ( 365 === $options['cookie_days'] ) && ( 'custom.css' === $options['css_file_name'] )
			) )
			->andReturn( true );

		$options = new Options();
		$options->set_opt();

		$this->assertTrue( $options->reset_options( 'main' ) );
		$this->assertSame( 365, $options->cookie_days );
		$this->assertSame( 'custom.css', $options->css_file_name );
	}

	/**
	 * @covers Options::update_options()
	 * @covers Options::sanitize_request_options()
	 */
	public function test__update_main_options_sanitizes_request_and_missing_checkboxes(): void {
		WP_Mock::userFunction( 'get_option' )->once()->andReturn(
			[ 'access_roles' => [ 'editor' ] ]
		);
		WP_Mock::userFunction( 'DemocracyPoll\plugin' )->once()->andReturn(
			(object) [ 'super_access' => true ]
		);
		WP_Mock::userFunction( 'update_option' )->once()
			->with( Options::OPT_NAME, Mockery::on(
				static function( array $options ): bool {
					return '<strong>Title</strong>alert(1)' === $options['before_title']
						&& '30 days' === $options['cookie_days']
						&& [ 'editor', 'badrole' ] === $options['access_roles']
						&& '0' === $options['keep_logs'];
				}
			) )
			->andReturn( true );

		$_POST = [
			'dem' => [
				'before_title' => '<strong>Title</strong><script>alert(1)</script>',
				'cookie_days'  => ' 30 days ',
				'access_roles' => [ 'Editor', 'Bad Role!' ],
			],
		];

		$options = new Options();
		$options->set_opt();

		$this->assertTrue( $options->update_options( 'main' ) );
		$this->assertSame( '<strong>Title</strong>alert(1)', $options->before_title );
		$this->assertSame( [ 'editor', 'badrole' ], $options->access_roles );
		$this->assertSame( '0', $options->keep_logs );
	}

	/**
	 * @covers Options::update_options()
	 * @covers Options::sanitize_request_options()
	 */
	public function test__update_main_options_preserves_access_roles_without_super_access(): void {
		WP_Mock::userFunction( 'get_option' )->once()->andReturn( [
			'access_roles' => [ 'administrator' ],
		] );
		WP_Mock::userFunction( 'DemocracyPoll\plugin' )->once()
			->andReturn( (object) [ 'super_access' => false ] );
		WP_Mock::userFunction( 'update_option' )->once()
			->with( Options::OPT_NAME, Mockery::on(
				fn( $options ) => ( [ 'administrator' ] === $options['access_roles'] )
			) )
			->andReturn( true );

		$_POST = [
			'dem' => [
				'access_roles' => [ 'subscriber' ],
			],
		];

		$options = new Options();
		$options->set_opt();

		$this->assertTrue( $options->update_options( 'main' ) );
		$this->assertSame( [ 'administrator' ], $options->access_roles );
	}

}
