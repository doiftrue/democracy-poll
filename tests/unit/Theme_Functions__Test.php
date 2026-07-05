<?php

namespace DemocracyPoll;

use Mockery;
use WP_Mock;

class Theme_Functions__Test extends DemocTestCase {

	/**
	 * @covers ::democracy_poll()
	 * @covers ::get_democracy_poll()
	 *
	 * @testWith [ "new" ]
	 *           [ "legacy" ]
	 */
	public function test__democracy_poll_supports_new_and_legacy_signatures( string $signature ): void {
		$this->set_up_test_environment();
		$poll = $this->db_poll_data();

		$args = 'new' === $signature
			? [ [ 'poll' => $poll, 'title_markup' => '<h2>{question}</h2>', 'from_post' => 99 ] ]
			: [ $poll, '<h2>', '</h2>', 99 ];

		ob_start();
		democracy_poll( ...$args );
		$html = ob_get_clean();

		$this->assertSame( 'vote|<h2>{question}</h2>', $html );
		$this->assertSame( [
			'table' => 'wp_democracy_q',
			'data'  => [ 'in_posts' => '99' ],
			'where' => [ 'id' => 10 ],
		], $GLOBALS['wpdb']->last_update );
	}

	/**
	 * @covers ::get_democracy_poll_results()
	 *
	 * @testWith [ "new" ]
	 *           [ "legacy" ]
	 */
	public function test__get_democracy_poll_results_supports_new_and_legacy_signatures( string $signature ): void {
		$this->set_up_test_environment();
		$poll = $this->db_poll_data();
		$args = 'new' === $signature
			? [ [ 'poll' => $poll, 'title_markup' => '<h2>{question}</h2>' ] ]
			: [ $poll, '<h2>', '</h2>' ];

		$html = get_democracy_poll_results( ...$args );

		$this->assertSame( 'voted|<h2>{question}</h2>', $html );
	}

	private function set_up_test_environment(): void {
		$this->set_options( [
			'democracy_off' => false,
			'keep_logs'     => false,
			'revote_off'    => false,
		] );

		$renderer = Mockery::mock( Poll_Renderer::class );
		$renderer
			->shouldReceive( 'render_poll' ) /** @see Poll_Renderer::render_poll() */
			->andReturnUsing( static fn( $show_screen, $title_markup ) => "$show_screen|$title_markup" );

		container()->set( Poll_Renderer::class, static fn( Poll $poll ) => $renderer );

		$GLOBALS['wpdb'] = new class {

			public string $democracy_q = 'wp_democracy_q';
			public array $last_update = [];

			public function update( string $table, array $data, array $where ): bool {
				$this->last_update = compact( 'table', 'data', 'where' );

				return true;
			}

		};
	}

}
