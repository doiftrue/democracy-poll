<?php

namespace DemocracyPoll;

use DemocracyPoll\Doubles\DemPoll_Legacy__Double;
use DemocracyPoll\Doubles\Plugin__Double;
use RuntimeException;

class DemPoll_Legacy__Test extends DemocTestCase {

	/**
	 * @covers DemPoll_Legacy::__get()
	 * @covers DemPoll_Legacy::__set()
	 */
	public function test__legacy_vote_properties_proxy_to_user_state(): void {
		$poll = new Poll( 0 );

		$poll->votedFor = 123;
		$this->assertSame( '123', $poll->voted_for );
		$this->assertSame( '123', $poll->votedFor );
		$this->assertTrue( $poll->has_voted );

		$poll->voted_for = '';
		$this->assertSame( '', $poll->votedFor );
		$this->assertFalse( $poll->has_voted );

		$poll->has_voted = true;
		$this->assertTrue( $poll->has_voted );
	}

	/**
	 * @covers DemPoll_Legacy::__get()
	 * @covers DemPoll_Legacy::__set()
	 */
	public function test__legacy_block_properties_proxy_to_user_state(): void {
		$poll = new Poll( 0 );

		$poll->blockVoting = true;
		$this->assertTrue( $poll->voting_blocked );
		$this->assertTrue( $poll->blockVoting );

		$poll->voting_blocked = false;
		$this->assertFalse( $poll->blockVoting );

		$poll->blockForVisitor = true;
		$this->assertTrue( $poll->blocked_by_not_logged );
		$this->assertTrue( $poll->blockForVisitor );
		$this->assertTrue( $poll->voting_blocked );

		$poll->blocked_by_not_logged = false;
		$this->assertFalse( $poll->blockForVisitor );
	}

	/**
	 * @covers DemPoll_Legacy::__isset()
	 */
	public function test__isset_resolves_legacy_and_canonical_properties(): void {
		$poll = new Poll( 0 );

		$this->assertTrue( isset( $poll->votedFor ) );
		$this->assertTrue( isset( $poll->voted_for ) );
		$this->assertTrue( isset( $poll->has_voted ) );
		$this->assertTrue( isset( $poll->blockVoting ) );
		$this->assertTrue( isset( $poll->voting_blocked ) );
		$this->assertTrue( isset( $poll->blockForVisitor ) );
		$this->assertTrue( isset( $poll->blocked_by_not_logged ) );
		$this->assertFalse( isset( $poll->missing_legacy_prop ) );
	}

	/**
	 * @covers DemPoll_Legacy::__isset()
	 * @covers DemPoll_Legacy::__get()
	 */
	public function test__renderer_is_lazy_and_available_only_for_existing_poll(): void {
		$poll = new Poll( 0 );

		$this->assertFalse( isset( $poll->renderer ) );

		$poll->id = 10;
		$poll->open = false;

		$this->assertTrue( isset( $poll->renderer ) );
		$this->assertInstanceOf( Poll_Renderer::class, $poll->renderer );
		$this->assertSame( $poll->renderer, $poll->renderer );
	}

	/**
	 * @covers DemPoll_Legacy::__get()
	 */
	public function test__unknown_get_returns_null(): void {
		$this->assertNull( ( new Poll( 0 ) )->missing_legacy_prop );
	}

	/**
	 * @covers DemPoll_Legacy::__set()
	 */
	public function test__unknown_set_throws_exception(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( DemPoll_Legacy::class . ' class prohibits setting dynamic properties. You are trying to set `missing_legacy_prop`.' );

		$poll = new Poll( 0 );
		$poll->missing_legacy_prop = true;
	}

	/**
	 * @covers DemPoll_Legacy::re_set_answers()
	 */
	public function test__re_set_answers_delegates_to_set_answers(): void {
		$poll = new DemPoll_Legacy__Double();

		$poll->re_set_answers();

		$this->assertSame( 1, $poll->set_answers_calls );
	}

}
