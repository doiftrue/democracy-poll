<?php

namespace DemocracyPoll;

use DemocracyPoll\Doubles\Plugin__Double;
use DemocracyPoll\Infra\Container;
use WP_Mock;
use WP_Mock\Tools\TestCase;

class DemocTestCase extends TestCase {

	public function setUp(): void {
		WP_Mock::setUp();

		$container = new Container();
		$GLOBALS['dem_test_container'] = $container;
		$container->set( Plugin::class, new Plugin__Double() );
	}

	public function tearDown(): void {
		unset( $GLOBALS['dem_test_container'] );
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

	protected function set_options( array $options ): void {
		$service = new class( $options ) extends Options {

			public function __construct( array $options ) {
				$this->opt = $options;
			}

		};

		container()->set( Options::class, $service );
	}

}
