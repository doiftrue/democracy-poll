<?php

namespace DemocracyPoll;

use DemocracyPoll\Infra\Container;
use WP_Mock;
use WP_Mock\Tools\TestCase;

class DemocTestCase extends TestCase {

	public function setUp(): void {
		WP_Mock::setUp();
		$GLOBALS['democracy_poll_test_container'] = new Container();
	}

	public function tearDown(): void {
		unset( $GLOBALS['democracy_poll_test_container'] );
		WP_Mock::tearDown();
	}

	public static function setUpBeforeClass(): void {
	}

	public static function tearDownAfterClass(): void {
	}

	protected function create_poll( array $overrides = [] ): Poll {
		return new Poll( $this->db_poll_data( $overrides ) );
	}

	protected function db_poll_data( array $overrides = [] ): object {
		return (object) array_merge( [
			'id'            => 10,
			'question'      => 'Question',
			'added'         => 1,
			'added_user'    => 2,
			'end'           => 0,
			'users_voted'   => 3,
			'democratic'    => true,
			'active'        => true,
			'open'          => false,
			'multiple'      => 0,
			'forusers'      => false,
			'revote'        => true,
			'show_results'  => true,
			'answers_order' => 'by_id',
			'in_posts'      => '',
			'note'          => '',
		], $overrides );
	}

}
