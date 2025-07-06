<?php /** @noinspection JsonEncodingApiUsageInspection */

namespace DemocracyPoll\unit;

use DemocracyPoll\Helpers\Helpers;

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

}
