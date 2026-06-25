<?php

namespace DemocracyPoll;

use Mockery;
use WP_Mock;

class Poll_Utils__Test extends DemocTestCase {

	/**
	 * @covers Poll_Utils::edit_poll_url()
	 */
	public function test__edit_poll_url_appends_integer_poll_id(): void {
		WP_Mock::userFunction( 'DemocracyPoll\plugin' )->once()->andReturn( (object) [
			'admin_page_url' => 'https://test.com/wp-admin/options-general.php?page=democracy-poll',
		] );

		$this->assertSame(
			'https://test.com/wp-admin/options-general.php?page=democracy-poll&edit_poll=15',
			Poll_Utils::edit_poll_url( 15 )
		);
	}

	/**
	 * @covers Poll_Utils::cuser_can_edit_poll()
	 */
	public function test__cuser_can_edit_poll_allows_super_admin(): void {
		WP_Mock::userFunction( 'DemocracyPoll\plugin' )->andReturn( (object) [
			'super_access' => true,
			'admin_access' => false,
		] );

		$this->assertTrue( Poll_Utils::cuser_can_edit_poll( new Poll( 0 ) ) );
		WP_Mock::userFunction( 'get_current_user_id' )->never();
	}

	/**
	 * @covers Poll_Utils::cuser_can_edit_poll()
	 */
	public function test__cuser_can_edit_poll_denies_user_without_admin_access(): void {
		WP_Mock::userFunction( 'DemocracyPoll\plugin' )->andReturn( (object) [
			'super_access' => false,
			'admin_access' => false,
		] );

		$this->assertFalse( Poll_Utils::cuser_can_edit_poll( new Poll( 0 ) ) );
		WP_Mock::userFunction( 'get_current_user_id' )->never();
	}

	/**
	 * @covers Poll_Utils::cuser_can_edit_poll()
	 * @dataProvider cuser_can_edit_poll_compares_poll_owner_with_current_user__data
	 */
	public function test__cuser_can_edit_poll_compares_poll_owner_with_current_user( $poll, int $cuser_id, bool $expected ): void {
		WP_Mock::userFunction( 'DemocracyPoll\plugin' )->andReturn( (object) [
			'super_access' => false,
			'admin_access' => true,
		] );
		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( $cuser_id );

		$this->assertSame( $expected, Poll_Utils::cuser_can_edit_poll( $poll ) );
	}

	public static function cuser_can_edit_poll_compares_poll_owner_with_current_user__data(): array {
		return [
			'poll belongs to current user' => [ (object) [ 'added_user' => '12' ], 12, true ],
			'poll belongs to another user' => [ (object) [ 'added_user' => 9 ], 12, false ],
			'poll does not exist'          => [ null, 12, false ],
		];
	}

	/**
	 * @covers Poll_Utils::get_minified_styles()
	 */
	public function test__get_minified_styles_returns_configured_css_on_each_call(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'democracy_css' )
			->andReturn( [ 'minify' => '.democracy{color:red}' ] );

		$expected = "\n<style id=\"democracy-poll-css\">.democracy{color:red}</style>\n";
		$this->assertSame( $expected, Poll_Utils::get_minified_styles() );
	}

	/**
	 * @covers Poll_Utils::get_minified_styles()
	 */
	public function test__get_minified_styles_returns_empty_string_without_css(): void {
		WP_Mock::userFunction( 'get_option' )->with( 'democracy_css' )->andReturn( [] );

		$this->assertSame( '', Poll_Utils::get_minified_styles() );
	}

	/**
	 * @covers Poll_Utils::enqueue_js()
	 */
	public function test__enqueue_js_adds_deferred_script_and_inline_config(): void {
		WP_Mock::userFunction( 'DemocracyPoll\plugin' )->andReturn( (object) [
			'url'       => 'https://test.com/path/to/plugin',
			'ver'       => '6.3.1',
			'poll_ajax' => (object) [
				'ajax_url' => 'https://test.com/wp-admin/admin-ajax.php',
			],
		] );
		WP_Mock::userFunction( 'DemocracyPoll\options' )->andReturn( (object) [
			'cookie_days'     => 365,
			'anim_speed'      => 400,
			'line_anim_speed' => 1500,
		] );
		Poll_Utils::enqueue_js();

		$scripts = wp_scripts();
		$script = $scripts->registered['democracy'];

		$this->assertTrue( wp_script_is( 'democracy' ) );
		$this->assertSame( [ 'democracy' ], $scripts->queue );
		$this->assertSame( 'https://test.com/path/to/plugin/assets/js/democracy.min.js', $script->src );
		$this->assertSame( '6.3.1', $script->ver );
		$this->assertSame( 1, $script->extra['group'] );
		$this->assertSame( 'defer', $script->extra['strategy'] );
		$inline_script = $scripts->get_inline_script_data( 'democracy', 'before' );
		$expected_config = 'window.democracyPollConfig = ' . wp_json_encode( [
			'ajax_url'        => 'https://test.com/wp-admin/admin-ajax.php',
			'cookie_days'     => 365,
			'anim_speed'      => 400,
			'line_anim_speed' => 1500,
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ';';

		$this->assertStringContainsString( $expected_config, $inline_script );
	}

	/**
	 * @covers Poll_Utils::parse_voted_str()
	 *
	 * @testWith [ "",                  []            ]
	 *           [ "2,15,31",           [2, 15, 31]   ]
	 *           [ ",0,4,,7,0",         [4, 7]        ]
	 *           [ "4,4,8",             [4, 4, 8]     ]
	 *           [ " 5,09,-2,word,3.8", [5, 9, -2, 3] ]
	 */
	public function test__parse_voted_str_returns_nonzero_integer_ids( string $value, array $expected ): void {
		$this->assertSame( $expected, Poll_Utils::parse_voted_str( $value ) );
	}

}
