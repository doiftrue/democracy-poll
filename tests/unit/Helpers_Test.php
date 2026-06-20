<?php /** @noinspection JsonEncodingApiUsageInspection */

namespace DemocracyPoll;

use DemocracyPoll\Admin\Admin_Page_Logs;
use DemocracyPoll\Admin\Admin_Page_l10n;
use DemocracyPoll\Helpers\IP;
use WP_Mock;

class Helpers_Test extends DemocTestCase {

	/**
	 * @covers Admin_Page_l10n::get_front_texts()
	 */
	public function test__get_front_texts(): void {
		$texts = Admin_Page_l10n::get_front_texts();

		$this->assertContains( 'Vote', $texts );
		$this->assertContains( 'Results', $texts );
		$this->assertContains( 'Only registered users can vote. <a>Login</a> to vote.', $texts );
	}

	/**
	 * @covers Admin_Page_l10n::update_l10n()
	 */
	public function test__update_l10n_removes_original_contextual_translation(): void {
		WP_Mock::userFunction( '_x' )->andReturnUsing(
			static fn( $text ) => 'Vote' === $text ? 'Localized Vote' : $text
		);
		WP_Mock::userFunction( 'update_option' )
			->once()
			->with( 'democracy_l10n', [ 'Results' => 'Custom Results' ] )
			->andReturn( true );
		WP_Mock::userFunction( 'get_option' )->andReturn( [] );

		$reflection = new \ReflectionClass( Admin_Page_l10n::class );
		$page = $reflection->newInstanceWithoutConstructor();

		$this->assertTrue( $page->update_l10n( [
			'Vote'    => 'Localized Vote',
			'Results' => 'Custom Results',
		] ) );
	}

	/**
	 * @covers IP::get_ip_info()
	 */
	public function test__get_ip_info(): void {
		$response = [
			'response' => [ 'code' => 200 ],
			'body'     => json_encode( [
				'success'      => true,
				'country'      => 'Uzbekistan',
				'country_code' => 'UZ',
				'city'         => 'Tashkent',
			] ),
		];

		WP_Mock::userFunction( 'wp_safe_remote_get' )->andReturn( $response );
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn( $response['body'] );

		$this->assertSame( [
			'city'         => 'Tashkent',
			'country'      => 'Uzbekistan',
			'country_code' => 'UZ',
		], IP::get_ip_info( '217.25.239.59' ) );
	}

	/**
	 * @covers IP::get_ip_info()
	 */
	public function test__get_ip_info_returns_empty_array_for_api_error(): void {
		$response = [
			'response' => [ 'code' => 200 ],
			'body'     => json_encode( [
				'success' => false,
				'message' => 'Invalid IP address',
			] ),
		];

		WP_Mock::userFunction( 'wp_safe_remote_get' )->andReturn( $response );
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn( $response['body'] );

		$this->assertSame( [], IP::get_ip_info( '217.25.239.59' ) );
	}

	/**
	 * @covers Admin_Page_Logs::ip_info_needs_update()
	 * @dataProvider data__ip_info_needs_update
	 */
	public function test__ip_info_needs_update( object $log, bool $expected ): void {
		$this->assertSame( $expected, Admin_Page_Logs::ip_info_needs_update( $log ) );
	}

	public function data__ip_info_needs_update(): \Generator {
		yield 'no IP' => [
			(object) [ 'ip' => '', 'ip_info' => '' ],
			false,
		];

		yield 'missing info' => [
			(object) [ 'ip' => '8.8.8.8', 'ip_info' => '' ],
			true,
		];

		yield 'stored info' => [
			(object) [ 'ip' => '8.8.8.8', 'ip_info' => 'United States,US,Mountain View' ],
			false,
		];

		yield 'recent failed attempt' => [
			(object) [ 'ip' => '8.8.8.8', 'ip_info' => (string) ( time() - HOUR_IN_SECONDS ) ],
			false,
		];

		yield 'stale failed attempt' => [
			(object) [ 'ip' => '8.8.8.8', 'ip_info' => (string) ( time() - DAY_IN_SECONDS - 1 ) ],
			true,
		];
	}

	/**
	 * @covers Admin_Page_Logs::ip_info_html()
	 */
	public function test__ip_info_html_contains_refresh_button(): void {
		$html = Admin_Page_Logs::ip_info_html( '' );

		$this->assertStringContainsString( 'class="ip_info_up_button_js"', $html );
	}

}
