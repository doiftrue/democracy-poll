<?php

namespace DemocracyPoll;

use DemocracyPoll\Doubles\Poll_Renderer__Double;
use WP_Mock;

class Poll_Renderer__Test extends DemocTestCase {

	/**
	 * @covers Poll_Renderer::get_poll_assets_once()
	 * @runInSeparateProcess  Because of static variable `static $once`.
	 * @preserveGlobalState disabled
	 */
	public function test__get_poll_assets_once_returns_assets_only_for_first_renderer(): void {
		WP_Mock::userFunction( 'DemocracyPoll\options' )->andReturn( (object) [
			'dont_show_results' => false,
		] );
		WP_Mock::userFunction( 'DemocracyPoll\plugin' )->andReturn( (object) [
			'url' => 'https://test.com/path/to/plugin',
			'ver' => '6.3.1',
		] );
		WP_Mock::userFunction( 'get_option' )
			->with( 'democracy_css' )
			->andReturn( [ 'minify' => '.democracy{color:red}' ] );

		$first_renderer = new Poll_Renderer__Double();
		$second_renderer = new Poll_Renderer__Double();

		$this->assertSame( [
			'loader_html' => '<div class="loader">Loader</div>',
			'styles'      => "\n<style id=\"democracy-poll-css\">.democracy{color:red}</style>\n",
		], $first_renderer->get_poll_assets_once() );
		$this->assertSame( [], $second_renderer->get_poll_assets_once() );
		$this->assertSame( [ 'democracy' ], wp_scripts()->queue );
	}

}
