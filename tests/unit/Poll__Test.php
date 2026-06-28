<?php

namespace DemocracyPoll;

use DemocracyPoll\Doubles\Poll_With_Answers__Double;
use WP_Mock;

class Poll__Test extends DemocTestCase {

	/**
	 * @covers \DemocracyPoll\Poll::__construct()
	 */
	public function test__empty_constructor_initializes_user_state_and_defaults(): void {
		$poll = new Poll( 0 );

		$this->assertInstanceOf( Poll_User_State::class, $poll->user_state );
		$this->assertSame( 0, $poll->id );
		$this->assertSame( '', $poll->question );
		$this->assertTrue( $poll->democratic );
		$this->assertTrue( $poll->active );
		$this->assertTrue( $poll->open );
		$this->assertTrue( $poll->revote );
		$this->assertTrue( $poll->show_results );
	}

	/**
	 * @covers \DemocracyPoll\Poll::__construct()
	 */
	public function test__constructor_maps_db_object_to_typed_properties(): void {
		$this->mock_options();

		$poll = new Poll( $this->db_poll_data( [
			'id'            => '12',
			'question'      => 123,
			'added'         => '100',
			'added_user'    => '7',
			'end'           => 0,
			'users_voted'   => '5',
			'democratic'    => '1',
			'active'        => '0',
			'open'          => '1',
			'multiple'      => '3',
			'forusers'      => '1',
			'revote'        => '1',
			'show_results'  => '0',
			'answers_order' => 1,
			'in_posts'      => 456,
			'note'          => 'Poll note',
		] ) );

		$this->assertSame( 12, $poll->id );
		$this->assertSame( '123', $poll->question );
		$this->assertSame( 100, $poll->added );
		$this->assertSame( 7, $poll->added_user );
		$this->assertSame( 0, $poll->end );
		$this->assertSame( 5, $poll->users_voted );
		$this->assertTrue( $poll->democratic );
		$this->assertFalse( $poll->active );
		$this->assertTrue( $poll->open );
		$this->assertSame( 3, $poll->multiple );
		$this->assertTrue( $poll->forusers );
		$this->assertTrue( $poll->revote );
		$this->assertFalse( $poll->show_results );
		$this->assertSame( '1', $poll->answers_order );
		$this->assertSame( '456', $poll->in_posts );
		$this->assertSame( 'Poll note', $poll->note );
	}

	/**
	 * @covers \DemocracyPoll\Poll::__construct()
	 */
	public function test__constructor_respects_global_democracy_off_option(): void {
		$this->mock_options( [
			'democracy_off' => true,
		] );

		$poll = new Poll( $this->db_poll_data( [
			'democratic' => true,
		] ) );

		$this->assertFalse( $poll->democratic );
	}

	/**
	 * @covers \DemocracyPoll\Poll::__construct()
	 * @dataProvider data__revote_flags
	 */
	public function test__constructor_resolves_revote_from_options( bool $keep_logs, bool $revote_off, bool $db_revote, bool $expected ): void {
		$this->mock_options( [
			'keep_logs'  => $keep_logs,
			'revote_off' => $revote_off,
		] );

		$poll = new Poll( $this->db_poll_data( [
			'revote' => $db_revote,
		] ) );

		$this->assertSame( $expected, $poll->revote );
	}

	public function data__revote_flags(): array {
		return [
			'enabled everywhere'  => [ true, false, true, true ],
			'logs disabled'      => [ false, false, true, false ],
			'revote disabled'    => [ true, true, true, false ],
			'db revote disabled' => [ true, false, false, false ],
		];
	}

	/**
	 * @covers \DemocracyPoll\Poll::__construct()
	 */
	public function test__constructor_keeps_defaults_when_db_object_has_no_id(): void {
		$poll = new Poll( (object) [ 'id' => 0 ] );

		$this->assertSame( 0, $poll->id );
		$this->assertSame( '', $poll->question );
		$this->assertTrue( $poll->open );
	}

	/**
	 * @covers \DemocracyPoll\Poll::__isset()
	 */
	public function test__isset_resolves_regular_properties(): void {
		$poll = new Poll( 0 );

		$this->assertTrue( isset( $poll->id ) );
		$this->assertTrue( isset( $poll->question ) );
		$this->assertFalse( isset( $poll->missing_poll_prop ) );
	}

	/**
	 * @covers \DemocracyPoll\Poll::__get()
	 * @covers \DemocracyPoll\Poll::__isset()
	 */
	public function test__answers_are_lazy_loaded_once(): void {
		$answer = new Poll_Answer( [
			'aid'      => 1,
			'qid'      => 10,
			'answer'   => 'First',
			'votes'    => 2,
			'aorder'   => 0,
			'added_by' => '',
		] );
		$poll = new Poll_With_Answers__Double( [ $answer ] );

		$this->assertTrue( isset( $poll->answers ) );
		$this->assertSame( 1, $poll->set_answers_calls );
		$this->assertSame( [ $answer ], $poll->answers );

		$poll->answers; // set magic property

		$this->assertSame( 1, $poll->set_answers_calls );
	}

	/**
	 * @covers \DemocracyPoll\Poll::__get()
	 */
	public function test__unknown_get_returns_null(): void {
		$this->assertNull( ( new Poll( 0 ) )->missing_poll_prop );
	}

	private function mock_options( array $overrides = [] ): void {
		WP_Mock::userFunction( 'DemocracyPoll\options' )
			->andReturn( (object) array_merge( [
				'democracy_off' => false,
				'keep_logs'     => true,
				'revote_off'    => false,
			], $overrides ) );
	}

}
