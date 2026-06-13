<?php /** @noinspection JsonEncodingApiUsageInspection */

namespace DemocracyPoll\unit;

use DemocracyPoll\Helpers\Helpers;
use DemocracyPoll\Helpers\IP;
use WP_Mock;

class Helpers_Test extends \DemocracyPoll\DemocTestCase {

	/**
	 * @covers Helpers::objects_array_sort()
	 * @dataProvider data__objects_array_sort
	 */
	public function test__objects_array_sort( $input, $args, $expect ) {
		$sorted = Helpers::objects_array_sort( $input, $args );

		$this->assertSame( $expect, json_encode( $sorted ) );
	}

	public function data__objects_array_sort(): \Generator {
		yield 'sort objects' => [
			[
				(object) [ 'votes' => 2, 'id' => 1 ],
				(object) [ 'votes' => 1, 'id' => 2 ],
				(object) [ 'votes' => 3, 'id' => 3 ],
				(object) [ 'votes' => 3, 'id' => 4 ],
			],
			[ 'votes' => 'DESC', 'id' => 'ASC' ],
			json_encode( [
				(object) [ 'votes' => 3, 'id' => 3 ],
				(object) [ 'votes' => 3, 'id' => 4 ],
				(object) [ 'votes' => 2, 'id' => 1 ],
				(object) [ 'votes' => 1, 'id' => 2 ],
			] ),
		];

		yield 'sort arrays' => [
			[
				[ 'votes' => 2, 'id' => 1 ],
				[ 'votes' => 1, 'id' => 2 ],
				[ 'votes' => 3, 'id' => 3 ],
				[ 'votes' => 3, 'id' => 4 ],
			],
			[ 'votes' => 'asc', 'id' => 'desc' ],
			json_encode( [
				[ 'votes' => 1, 'id' => 2 ],
				[ 'votes' => 2, 'id' => 1 ],
				[ 'votes' => 3, 'id' => 4 ],
				[ 'votes' => 3, 'id' => 3 ],
			] ),
		];
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

}
