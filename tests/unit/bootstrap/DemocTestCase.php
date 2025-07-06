<?php

namespace DemocracyPoll;

use \WP_Mock\Tools\TestCase;

class DemocTestCase extends TestCase {

	public function setUp(): void {
		\WP_Mock::setUp();
	}

	public function tearDown(): void {
		\WP_Mock::tearDown();
	}

	public static function setUpBeforeClass(): void {
	}

	public static function tearDownAfterClass(): void {
	}

}
