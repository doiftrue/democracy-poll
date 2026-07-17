<?php

namespace DemocracyPoll;

use DemocracyPoll\Doubles\Poll_Cookies__Double;
use DemocracyPoll\Doubles\Poll_With_Answers__Double;
use Unitest_WP_Copy\WPDB_Runtime;
use WP_Error;
use WP_Mock;

class Poll_Voting_Wpdb__Double extends WPDB_Runtime {

	public string $democracy_a = 'wp_democracy_a';
	public string $democracy_q = 'wp_democracy_q';
	public string $democracy_log = 'wp_democracy_log';
	public int $answer_votes = 5;
	public int $users_voted = 3;
	public bool $log_insert_result = true;
	public array $inserted_log = [];

	public function get_results( $query ): array {
		return [];
	}

	public function query( $query ) {
		if( str_contains( $query, 'SET votes = (votes+1)' ) ){
			$this->answer_votes++;
		}
		elseif( str_contains( $query, 'SET votes = IF( votes>0, votes-1, 0 )' ) ){
			$this->answer_votes = max( 0, $this->answer_votes - 1 );
		}
		elseif( str_contains( $query, 'SET users_voted = (users_voted+1)' ) ){
			$this->users_voted++;
		}
		elseif( str_contains( $query, 'SET users_voted = IF( users_voted>0, users_voted-1, 0 )' ) ){
			$this->users_voted = max( 0, $this->users_voted - 1 );
		}

		return str_starts_with( $query, 'DELETE FROM' ) ? 0 : 1;
	}

	public function insert( $table, $data ) {
		if( $table === $this->democracy_log ){
			$this->inserted_log = $data;

			return $this->log_insert_result;
		}

		return true;
	}

}

class Poll_Voting__Test extends DemocTestCase {

	private Poll_Voting_Wpdb__Double $wpdb;

	public function setUp(): void {
		parent::setUp();

		$this->wpdb = new Poll_Voting_Wpdb__Double();
		$GLOBALS['wpdb'] = $this->wpdb;
		$_SERVER['REMOTE_ADDR'] = '203.0.113.20';
		$this->set_options( [
			'allow_same_ip_votes' => true,
			'cookie_days'         => 365,
			'order_answers'       => 'by_id',
			'soft_ip_detect'      => false,
		] );
		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( 0 );
	}

	public function tearDown(): void {
		unset( $GLOBALS['wpdb'], $_SERVER['REMOTE_ADDR'] );
		parent::tearDown();
	}

	/**
	 * @covers Poll_Voting::vote()
	 */
	public function test__vote_is_logged_even_without_a_logging_option(): void {
		$poll = $this->poll();
		$fingerprint = 'v1:' . str_repeat( 'e', 64 );

		$result = ( new Poll_Voting( $poll, container()->get( Options::class ) ) )->vote( '7', $fingerprint );

		$this->assertSame( '7', $result );
		$this->assertSame( 6, $this->wpdb->answer_votes );
		$this->assertSame( 4, $this->wpdb->users_voted );
		$this->assertSame( hash( 'sha256', $fingerprint ), $this->wpdb->inserted_log['fingerprint'] );
	}

	/**
	 * @covers Poll_Voting::vote()
	 */
	public function test__failed_log_insert_rolls_back_the_vote(): void {
		$this->wpdb->log_insert_result = false;
		$poll = $this->poll();

		$result = ( new Poll_Voting( $poll, container()->get( Options::class ) ) )->vote(
			'7',
			'v1:' . str_repeat( 'f', 64 )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 5, $this->wpdb->answer_votes );
		$this->assertSame( 3, $this->wpdb->users_voted );
		$this->assertSame( 3, $poll->users_voted );
		$this->assertFalse( $poll->user_state->has_voted );
	}

	private function poll(): Poll {
		$poll = new Poll_With_Answers__Double( [] );
		$poll->id = 10;
		$poll->open = true;
		$poll->democratic = false;
		$poll->multiple = 0;
		$poll->users_voted = 3;
		$poll->user_state->poll_cookie = new Poll_Cookies__Double( $poll, '', false );

		return $poll;
	}

}
