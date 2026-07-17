<?php

namespace DemocracyPoll;

use Unitest_WP_Copy\WPDB_Runtime;
use WP_Mock;

class Poll_Logs_Wpdb__Double extends WPDB_Runtime {

	public string $democracy_log = 'wp_democracy_log';
	public array $inserted_data = [];
	public array $results_by_identity = [];

	public function get_results( $query ): array {
		if( str_contains( $query, 'userid = 42' ) ){
			return $this->results_by_identity['user'] ?? [];
		}
		if( str_contains( $query, 'fingerprint =' ) && str_contains( $query, 'userid = 0' ) ){
			return $this->results_by_identity['fingerprint'] ?? [];
		}
		if( str_contains( $query, 'ip =' ) ){
			return $this->results_by_identity['ip'] ?? [];
		}

		return [];
	}

	public function insert( $table, $data ): bool {
		$this->inserted_data = $data;

		return true;
	}

}

class Poll_Logs__Test extends DemocTestCase {

	private Poll_Logs_Wpdb__Double $wpdb;

	public function setUp(): void {
		parent::setUp();

		$this->wpdb = new Poll_Logs_Wpdb__Double();
		$GLOBALS['wpdb'] = $this->wpdb;
		$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
	}

	public function tearDown(): void {
		unset( $GLOBALS['wpdb'], $_SERVER['REMOTE_ADDR'] );
		parent::tearDown();
	}

	/**
	 * @covers Poll_Logs::get_user_vote_logs()
	 * @covers Poll_Logs::set_fingerprint()
	 */
	public function test__guest_is_identified_by_ip_when_same_ip_votes_are_not_allowed(): void {
		$this->set_options( [
			'allow_same_ip_votes' => false,
			'soft_ip_detect'      => false,
		] );
		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( 0 );
		$this->wpdb->results_by_identity = [
			'ip'          => [ (object) [ 'aids' => '1' ] ],
			'fingerprint' => [ (object) [ 'aids' => '2' ] ],
		];

		$logs = $this->logs();
		$logs->set_fingerprint( 'v1:' . str_repeat( 'a', 64 ) );
		$this->assertSame( '1', $logs->get_user_vote_logs()[0]->aids );
	}

	/**
	 * @covers Poll_Logs::get_user_vote_logs()
	 * @covers Poll_Logs::set_fingerprint()
	 */
	public function test__guest_is_identified_by_fingerprint_when_same_ip_votes_are_allowed(): void {
		$this->set_options( [
			'allow_same_ip_votes' => true,
			'soft_ip_detect'      => false,
		] );
		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( 0 );
		$fingerprint = 'v1:' . str_repeat( 'b', 64 );
		$this->wpdb->results_by_identity = [
			'ip'          => [ (object) [ 'aids' => '1' ] ],
			'fingerprint' => [ (object) [ 'aids' => '2' ] ],
		];

		$logs = $this->logs();
		$logs->set_fingerprint( $fingerprint );
		$this->assertSame( '2', $logs->get_user_vote_logs()[0]->aids );
	}

	/**
	 * @covers Poll_Logs::get_user_vote_logs()
	 */
	public function test__guest_falls_back_to_ip_when_fingerprint_is_missing(): void {
		$this->set_options( [
			'allow_same_ip_votes' => true,
			'soft_ip_detect'      => false,
		] );
		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( 0 );
		$this->wpdb->results_by_identity['ip'] = [ (object) [ 'aids' => '1' ] ];

		$logs = $this->logs();
		$logs->set_fingerprint( '' );
		$this->assertSame( '1', $logs->get_user_vote_logs()[0]->aids );
	}

	/**
	 * @covers Poll_Logs::get_user_vote_logs()
	 */
	public function test__initial_render_defers_guest_lookup_until_fingerprint_is_resolved(): void {
		$this->set_options( [
			'allow_same_ip_votes' => true,
			'soft_ip_detect'      => false,
		] );
		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( 0 );

		$this->assertSame( [], $this->logs()->get_user_vote_logs() );
	}

	/**
	 * @covers Poll_Logs::get_user_vote_logs()
	 * @covers Poll_Logs::set_fingerprint()
	 */
	public function test__logged_in_user_is_identified_by_account_regardless_of_fingerprint(): void {
		$this->set_options( [
			'allow_same_ip_votes' => true,
			'soft_ip_detect'      => false,
		] );
		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( 42 );
		$this->wpdb->results_by_identity = [
			'user'        => [ (object) [ 'aids' => '3' ] ],
			'fingerprint' => [ (object) [ 'aids' => '2' ] ],
		];

		$logs = $this->logs();
		$logs->set_fingerprint( 'v1:' . str_repeat( 'c', 64 ) );
		$this->assertSame( '3', $logs->get_user_vote_logs()[0]->aids );
	}

	/**
	 * @covers Poll_Logs::insert_logs()
	 * @covers Poll_Logs::set_fingerprint()
	 */
	public function test__new_log_stores_browser_fingerprint(): void {
		$this->set_options( [
			'allow_same_ip_votes' => true,
			'cookie_days'         => 365,
			'soft_ip_detect'      => false,
		] );
		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( 0 );
		$fingerprint = 'v1:' . str_repeat( 'd', 64 );
		$poll = $this->poll();
		$poll->user_state->voted_for = '7';

		$logs = new Poll_Logs( $poll );
		$logs->set_fingerprint( $fingerprint );

		$this->assertTrue( $logs->insert_logs() );
		$this->assertSame( hash( 'sha256', $fingerprint ), $this->wpdb->inserted_data['fingerprint'] );
		$this->assertSame( '203.0.113.10', $this->wpdb->inserted_data['ip'] );
	}

	/**
	 * @covers Poll_Logs::insert_logs()
	 * @covers Poll_Logs::set_fingerprint()
	 */
	public function test__ip_mode_does_not_store_browser_fingerprint(): void {
		$this->set_options( [
			'allow_same_ip_votes' => false,
			'cookie_days'         => 365,
			'soft_ip_detect'      => false,
		] );
		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( 0 );
		$poll = $this->poll();
		$poll->user_state->voted_for = '7';

		$logs = new Poll_Logs( $poll );
		$logs->set_fingerprint( 'v1:' . str_repeat( 'e', 64 ) );
		$logs->insert_logs();

		$this->assertSame( '', $this->wpdb->inserted_data['fingerprint'] );
	}

	/**
	 * @covers Poll_Logs::insert_logs()
	 * @covers Poll_Logs::set_fingerprint()
	 */
	public function test__logged_in_vote_does_not_store_browser_fingerprint(): void {
		$this->set_options( [
			'allow_same_ip_votes' => true,
			'cookie_days'         => 365,
			'soft_ip_detect'      => false,
		] );
		WP_Mock::userFunction( 'get_current_user_id' )->andReturn( 42 );
		$poll = $this->poll();
		$poll->user_state->voted_for = '7';

		$logs = new Poll_Logs( $poll );
		$logs->set_fingerprint( 'v1:' . str_repeat( 'f', 64 ) );
		$logs->insert_logs();

		$this->assertSame( 42, $this->wpdb->inserted_data['userid'] );
		$this->assertSame( '', $this->wpdb->inserted_data['fingerprint'] );
	}

	private function logs(): Poll_Logs {
		return new Poll_Logs( $this->poll() );
	}

	private function poll(): Poll {
		$poll = new Poll( 0 );
		$poll->id = 10;

		return $poll;
	}

}
