<?php

namespace DemocracyPoll;

use DemocracyPoll\Doubles\Poll_Ajax__Double;
use Mockery;
use WP_Mock;

class Poll_Ajax_Exit extends \RuntimeException {}

class Poll_Ajax__Test extends DemocTestCase {

	private Poll $poll;
	private Poll_Renderer $renderer;
	private Poll_Voting_Service $voting;

	public function setUp(): void {
		parent::setUp();

		$_POST = [];
		$this->poll = Mockery::mock( Poll::class );
		$this->renderer = Mockery::mock( Poll_Renderer::class );
		$this->voting = Mockery::mock( Poll_Voting_Service::class );
	}

	public function tearDown(): void {
		$_POST = [];
		parent::tearDown();
	}

	/**
	 * @covers Poll_Ajax::__construct()
	 */
	public function test__constructor_sets_ajax_url(): void {
		WP_Mock::userFunction( 'admin_url' )
			->once()
			->with( 'admin-ajax.php' )
			->andReturn( 'https://example.test/wp-admin/admin-ajax.php' );

		$ajax = new Poll_Ajax();

		$this->assertSame( 'https://example.test/wp-admin/admin-ajax.php', $ajax->ajax_url );
	}

	/**
	 * @covers Poll_Ajax::init()
	 */
	public function test__registers_authenticated_and_public_ajax_handlers(): void {
		$ajax = new Poll_Ajax__Double( $this->poll, $this->renderer, $this->voting );
		$ajax->init();

		$this->assertSame( 10, has_action( 'wp_ajax_dem_ajax', [ $ajax, 'ajax_request_handler' ] ) );
		$this->assertSame( 10, has_action( 'wp_ajax_nopriv_dem_ajax', [ $ajax, 'ajax_request_handler' ] ) );

		remove_action( 'wp_ajax_dem_ajax', [ $ajax, 'ajax_request_handler' ] );
		remove_action( 'wp_ajax_nopriv_dem_ajax', [ $ajax, 'ajax_request_handler' ] );
	}

	/**
	 * @covers Poll_Ajax::ajax_request_handler()
	 */
	public function test__sanitizes_request_vars_before_dispatch(): void {
		$_POST = [
			'dem_act'    => '<b>vote</b>',
			'dem_pid'    => '12foo',
			'answer_ids' => '1\\~New \\"answer\\"',
		];

		$this->renderer->not_show_results = false;
		$this->renderer->shouldReceive( 'get_result_screen' )->once()->andReturn( 'RESULTS' );
		$this->voting->shouldReceive( 'vote' )->once()->with( '1~New "answer"' )->andReturn( '1,2' );

		$this->expect_ajax_exit();
		$this->expectOutputString( 'RESULTS' );

		$ajax = new Poll_Ajax__Double( $this->poll, $this->renderer, $this->voting );
		$ajax->ajax_request_handler();
	}

	/**
	 * @covers Poll_Ajax::ajax_request_handler()
	 * @dataProvider data__view_results_actions
	 */
	public function test__dispatches_current_and_legacy_view_results_actions( string $action ): void {
		$_POST = [
			'dem_act'    => $action,
			'answer_ids' => '',
			'dem_pid'    => 12,
		];
		$this->renderer->not_show_results = false;
		$this->renderer->shouldReceive( 'get_result_screen' )->once()->andReturn( 'RESULTS' );

		$this->expect_ajax_exit();
		$this->expectOutputString( 'RESULTS' );

		$ajax = new Poll_Ajax__Double( $this->poll, $this->renderer, $this->voting );
		$ajax->ajax_request_handler();
	}

	public function data__view_results_actions(): array {
		return [
			'current action' => [ 'viewResults' ],
			'legacy action'  => [ 'view' ],
		];
	}

	/**
	 * @covers Poll_Ajax::ajax_request_handler()
	 * @dataProvider data__vote_screen_actions
	 */
	public function test__dispatches_current_and_legacy_vote_screen_actions( string $action ): void {
		$_POST = [
			'dem_act'    => $action,
			'answer_ids' => '',
			'dem_pid'    => 12,
		];

		$this->renderer->shouldReceive( 'get_vote_screen' )->once()->andReturn( 'VOTE' );

		$this->expect_ajax_exit();
		$this->expectOutputString( 'VOTE' );

		$ajax = new Poll_Ajax__Double( $this->poll, $this->renderer, $this->voting );
		$ajax->ajax_request_handler();
	}

	public function data__vote_screen_actions(): array {
		return [
			'current action' => [ 'voteScreen' ],
			'legacy action'  => [ 'vote_screen' ],
		];
	}

	/**
	 * @covers Poll_Ajax::ajax_request_handler()
	 */
	public function test__passes_answer_ids_to_voting_service_and_renders_results(): void {
		$_POST = [
			'dem_act'    => 'vote',
			'answer_ids' => '1~2',
			'dem_pid'    => 12,
		];

		$this->renderer->not_show_results = false;
		$this->renderer->shouldReceive( 'get_result_screen' )->once()->andReturn( 'RESULTS' );
		$this->voting->shouldReceive( 'vote' )->once()->with( '1~2' )->andReturn( '1,2' );

		$this->expect_ajax_exit();
		$this->expectOutputString( 'RESULTS' );

		$ajax = new Poll_Ajax__Double( $this->poll, $this->renderer, $this->voting );
		$ajax->ajax_request_handler();
	}

	/**
	 * @covers Poll_Ajax::ajax_request_handler()
	 */
	public function test__rejects_unknown_action(): void {
		$_POST = [
			'dem_act'    => 'unknown',
			'answer_ids' => '',
			'dem_pid'    => 12,
		];

		$this->expect_ajax_exit( 'error: unknown action' );

		$ajax = new Poll_Ajax__Double( $this->poll, $this->renderer, $this->voting );
		$ajax->ajax_request_handler();
	}

	private function expect_ajax_exit( string $message = '' ): void {
		$expectation = WP_Mock::userFunction( 'wp_die' )->once();
		$message && $expectation->with( $message );
		$expectation->andThrow( Poll_Ajax_Exit::class );

		$this->expectException( Poll_Ajax_Exit::class );
	}

}
