<?php

namespace DemocracyPoll\unit;

use DemocracyPoll\Poll_Cookies;
use DemPoll;
use WP_Mock;

class Poll_Cookies__Test extends \DemocracyPoll\DemocTestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::userFunction( 'DemocracyPoll\options' )
			->andReturn( (object) [ 'cookie_days' => 2 ] );
		$_COOKIE = [];
	}

	public function tearDown(): void {
		$_COOKIE = [];
		parent::tearDown();
	}

	/**
	 * @covers \DemocracyPoll\Poll_Cookies::get()
	 */
	public function test__gets_aggregate_values(): void {
		$_COOKIE['demPoll'] = implode( '|', [
			'12:1_2-' . Poll_Cookies::to_base36( time() ),
			'13:0-' . Poll_Cookies::to_base36( time() ),
			'14:0-' . Poll_Cookies::to_base36( time() - ( DAY_IN_SECONDS / 2 ) - 1 ),
			'15:3-' . Poll_Cookies::to_base36( time() - ( DAY_IN_SECONDS * 2 ) - 1 ),
			'invalid',
		] );

		$this->assertSame( '1,2', ( new Poll_Cookies( $this->get_poll( 12 ) ) )->get() );
		$this->assertSame( Poll_Cookies::NOT_VOTED, ( new Poll_Cookies( $this->get_poll( 13 ) ) )->get() );
		$this->assertSame( '', ( new Poll_Cookies( $this->get_poll( 14 ) ) )->get() );
		$this->assertSame( '', ( new Poll_Cookies( $this->get_poll( 15 ) ) )->get() );
	}

	/**
	 * @covers \DemocracyPoll\Poll_Cookies::set()
	 */
	public function test__sets_shared_cookie(): void {
		$old_timestamp = time() - 100;
		$_COOKIE['demPoll'] = '9:10-' . Poll_Cookies::to_base36( $old_timestamp );
		$timestamp_before = time();

		$cookie = new Testable_Poll_Cookies( $this->get_poll( 12, '3,4' ) );
		$cookie->set();
		$timestamp_after = time();

		$this->assertMatchesRegularExpression( '/^9:10-[0-9a-z]+\|12:3_4-[0-9a-z]+$/', $_COOKIE['demPoll'] );
		[ $old_record, $new_record ] = explode( '|', $_COOKIE['demPoll'] );
		$this->assertSame( '9:10-' . Poll_Cookies::to_base36( $old_timestamp ), $old_record );

		$new_timestamp = Poll_Cookies::from_base36( substr( $new_record, strrpos( $new_record, '-' ) + 1 ) );
		$this->assertGreaterThanOrEqual( $timestamp_before, $new_timestamp );
		$this->assertLessThanOrEqual( $timestamp_after, $new_timestamp );
		$this->assertSame( $new_timestamp + ( DAY_IN_SECONDS * 2 ), $cookie->sent[0]['expire'] );
	}

	/**
	 * @covers \DemocracyPoll\Poll_Cookies::set_not_voted()
	 */
	public function test__not_voted_uses_internal_expiration_without_shortening_votes(): void {
		$vote_timestamp = time() - 100;
		$_COOKIE['demPoll'] = '9:10-' . Poll_Cookies::to_base36( $vote_timestamp );
		$timestamp_before = time();

		$cookie = new Testable_Poll_Cookies( $this->get_poll( 12 ) );
		$cookie->set_not_voted();
		$timestamp_after = time();

		$this->assertMatchesRegularExpression( '/^9:10-[0-9a-z]+\|12:0-[0-9a-z]+$/', $_COOKIE['demPoll'] );
		$not_voted_record = explode( '|', $_COOKIE['demPoll'] )[1];
		$not_voted_timestamp = Poll_Cookies::from_base36( substr( $not_voted_record, strrpos( $not_voted_record, '-' ) + 1 ) );

		$this->assertGreaterThanOrEqual( $timestamp_before, $not_voted_timestamp );
		$this->assertLessThanOrEqual( $timestamp_after, $not_voted_timestamp );
		$this->assertSame( $vote_timestamp + ( DAY_IN_SECONDS * 2 ), $cookie->sent[0]['expire'] );
		$this->assertSame( Poll_Cookies::NOT_VOTED, $cookie->get() );
	}

	/**
	 * @covers \DemocracyPoll\Poll_Cookies::set_not_voted()
	 */
	public function test__not_voted_only_cookie_expires_after_12_hours(): void {
		$cookie = new Testable_Poll_Cookies( $this->get_poll( 12 ) );
		$cookie->set_not_voted();

		$timestamp = Poll_Cookies::from_base36( substr( $_COOKIE['demPoll'], strrpos( $_COOKIE['demPoll'], '-' ) + 1 ) );

		$this->assertSame( $timestamp + ( DAY_IN_SECONDS / 2 ), $cookie->sent[0]['expire'] );
	}

	/**
	 * @covers \DemocracyPoll\Poll_Cookies::delete()
	 */
	public function test__deletes_only_current_poll_value(): void {
		$timestamp = time();
		$_COOKIE['demPoll'] = '12:1_2-' . Poll_Cookies::to_base36( $timestamp )
			. '|13:3-' . Poll_Cookies::to_base36( $timestamp );

		$cookie = new Testable_Poll_Cookies( $this->get_poll( 12 ) );
		$cookie->delete();

		$this->assertSame( '13:3-' . Poll_Cookies::to_base36( $timestamp ), $_COOKIE['demPoll'] );
		$this->assertSame( $timestamp + ( DAY_IN_SECONDS * 2 ), $cookie->sent[0]['expire'] );
	}

	private function get_poll( int $id, string $voted_for = '' ): DemPoll {
		$poll = new DemPoll( 0 );
		$poll->id = $id;
		$poll->user_state->voted_for = $voted_for;

		return $poll;
	}

}

class Testable_Poll_Cookies extends Poll_Cookies {

	public array $sent = [];

	protected function send_cookie( string $name, string $value, int $expire ): void {
		$this->sent[] = [
			'name'   => $name,
			'value'  => $value,
			'expire' => $expire,
		];
	}

}
