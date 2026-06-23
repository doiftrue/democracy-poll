<?php

namespace DemocracyPoll;

class Poll_Answer__Test extends DemocTestCase {

	/**
	 * @covers Poll_Answer::__construct()
	 */
	public function test__constructor_maps_array_data_to_typed_properties(): void {
		$answer = new Poll_Answer( [
			'aid'      => '15',
			'qid'      => '7',
			'answer'   => 'Answer text',
			'votes'    => 12,
			'aorder'   => 3,
			'added_by' => '42',
		] );

		$this->assertSame( 15, $answer->aid );
		$this->assertSame( 7, $answer->qid );
		$this->assertSame( 'Answer text', $answer->answer );
		$this->assertSame( 12, $answer->votes );
		$this->assertSame( 3, $answer->aorder );
		$this->assertSame( '42', $answer->added_by );
	}

	/**
	 * @covers Poll_Answer::__construct()
	 */
	public function test__constructor_maps_object_data_to_typed_properties(): void {
		$answer = new Poll_Answer( (object) [
			'aid'      => 15,
			'qid'      => 7,
			'answer'   => 123,
			'votes'    => '12',
			'aorder'   => '3',
			'added_by' => null,
		] );

		$this->assertSame( 15, $answer->aid );
		$this->assertSame( 7, $answer->qid );
		$this->assertSame( '123', $answer->answer );
		$this->assertSame( 12, $answer->votes );
		$this->assertSame( 3, $answer->aorder );
		$this->assertSame( '', $answer->added_by );
	}

}
