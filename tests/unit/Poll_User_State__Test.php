<?php

namespace DemocracyPoll;

use RuntimeException;
use WP_Mock;

class Poll_User_State__Test extends DemocTestCase {

	/**
	 * @covers Poll_User_State::__construct()
	 * @covers Poll_User_State::__get()
	 */
	public function test__empty_poll_has_no_vote_and_blocks_voting(): void {
		$state = ( new Poll( 0 ) )->user_state;

		$this->assertSame( '', $state->voted_for );
		$this->assertFalse( $state->has_voted );
		$this->assertTrue( $state->voting_blocked );
	}

	/**
	 * @covers Poll_User_State::__set()
	 * @covers Poll_User_State::__get()
	 */
	public function test__setting_voted_for_updates_has_voted(): void {
		$state = ( new Poll( 0 ) )->user_state;

		$state->voted_for = '1,2';
		$this->assertSame( '1,2', $state->voted_for );
		$this->assertTrue( $state->has_voted );

		$state->voted_for = '';
		$this->assertSame( '', $state->voted_for );
		$this->assertFalse( $state->has_voted );
	}

	/**
	 * @covers Poll_User_State::__set()
	 * @covers Poll_User_State::__get()
	 */
	public function test__setting_blocked_by_not_logged_also_blocks_voting(): void {
		$state = ( new Poll( 0 ) )->user_state;

		$state->blocked_by_not_logged = true;

		$this->assertTrue( $state->blocked_by_not_logged );
		$this->assertTrue( $state->voting_blocked );
	}

	/**
	 * @covers Poll_User_State::__isset()
	 * @covers Poll_User_State::__get()
	 */
	public function test__isset_resolves_known_lazy_properties(): void {
		$state = ( new Poll( 0 ) )->user_state;

		$this->assertTrue( isset( $state->voted_for ) );
		$this->assertTrue( isset( $state->has_voted ) );
		$this->assertTrue( isset( $state->voting_blocked ) );
		$this->assertFalse( isset( $state->unknown ) );
	}

	/**
	 * @covers Poll_User_State::__get()
	 */
	public function test__unknown_get_throws_exception(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( Poll_User_State::class . ' class has no `missing` property.' );

		( new Poll( 0 ) )->user_state->missing;
	}

	/**
	 * @covers Poll_User_State::__set()
	 */
	public function test__unknown_set_throws_exception(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( Poll_User_State::class . ' class has no `missing` property.' );

		$state = ( new Poll( 0 ) )->user_state;
		$state->missing = true;
	}

	/**
	 * @covers Poll_User_State::__get()
	 */
	public function test__blocked_by_not_logged_uses_global_and_poll_user_only_flags(): void {
		WP_Mock::userFunction( 'DemocracyPoll\options' )
			->andReturn( (object) [
				'only_for_users' => false,
				'keep_logs'      => false,
			] );
		WP_Mock::userFunction( 'is_user_logged_in' )->andReturn( false );

		$poll = $this->poll( 10 );
		$poll->forusers = true;

		$this->assertTrue( $poll->user_state->blocked_by_not_logged );
	}

	/**
	 * @covers Poll_User_State::__get()
	 */
	public function test__voted_for_prefers_logs_over_cookie_when_logs_are_enabled(): void {
		WP_Mock::userFunction( 'DemocracyPoll\options' )
			->andReturn( (object) [
				'only_for_users' => false,
				'keep_logs'      => true,
			] );

		$state = $this->state_with_dependencies( $this->poll( 10 ), '1,2', false, [ (object) [ 'aids' => '7,8' ] ] );

		$this->assertSame( '7,8', $state->voted_for );
		$this->assertTrue( $state->has_voted );
	}

	/**
	 * @covers Poll_User_State::__get()
	 */
	public function test__voted_for_uses_cookie_when_logs_are_disabled(): void {
		WP_Mock::userFunction( 'DemocracyPoll\options' )
			->andReturn( (object) [
				'only_for_users' => false,
				'keep_logs'      => false,
			] );

		$state = $this->state_with_dependencies( $this->poll( 10 ), '3,4', false );

		$this->assertSame( '3,4', $state->voted_for );
		$this->assertTrue( $state->has_voted );
	}

	/**
	 * @covers Poll_User_State::__get()
	 */
	public function test__voted_for_ignores_not_voted_cookie_marker(): void {
		WP_Mock::userFunction( 'DemocracyPoll\options' )
			->andReturn( (object) [
				'only_for_users' => false,
				'keep_logs'      => false,
			] );

		$state = $this->state_with_dependencies( $this->poll( 10 ), Poll_Cookies::NOT_VOTED, true );

		$this->assertSame( '', $state->voted_for );
		$this->assertFalse( $state->has_voted );
	}

	/**
	 * @covers Poll_User_State::set_vote_cookie()
	 * @covers Poll_User_State::set_not_voted_cookie()
	 */
	public function test__cookie_mutators_delegate_to_poll_cookie(): void {
		$state = $this->state_with_dependencies( $this->poll( 10 ) );
		/** @var Testable_Poll_Cookies $cookie */
		$cookie = $state->poll_cookie;

		$state->set_vote_cookie();
		$state->set_not_voted_cookie();

		$this->assertSame( 1, $cookie->set_calls );
		$this->assertSame( 1, $cookie->set_not_voted_calls );
	}

	private function poll( int $id ): Poll {
		$poll = new Poll( 0 );
		$poll->id = $id;
		$poll->open = true;

		return $poll;
	}

	/**
	 * @param object[] $logs
	 */
	private function state_with_dependencies(
		Poll $poll,
		string $cookie_value = '',
		bool $is_not_voted = false,
		array $logs = []
	): Poll_User_State {
		$state = new Poll_User_State( $poll );
		$state->poll_cookie = new Testable_Poll_Cookies( $poll, $cookie_value, $is_not_voted );
		$state->poll_logs = new Fake_Poll_Logs( $poll, $logs );

		return $state;
	}

}
