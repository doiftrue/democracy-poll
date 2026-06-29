<?php

namespace DemocracyPoll;

use DemocracyPoll\Doubles\Poll_Renderer__Double;
use DemocracyPoll\Doubles\Poll_Renderer_Render__Double;
use DemocracyPoll\Doubles\Plugin__Double;
use WP_Mock;

class Poll_Renderer__Test extends DemocTestCase {

	/**
	 * @covers Poll_Renderer::get_poll_assets_once()
	 * @runInSeparateProcess  Because of static variable `static $once`.
	 * @preserveGlobalState disabled
	 */
	public function test__get_poll_assets_once_returns_assets_only_for_first_renderer(): void {
		container()->set( Poll_Ajax::class, (object) [ 'ajax_url' => '' ] );

		$this->set_options( [
			'dont_show_results' => false,
			'cookie_days'       => 365,
			'anim_speed'        => 400,
			'line_anim_speed'   => 1500,
		] );

		$plugin = container()->get( Plugin::class );
		$plugin->url = 'https://test.com/path/to/plugin';
		$plugin->ver = '6.3.1';

		WP_Mock::userFunction( 'get_option' )
			->with( 'democracy_css' )
			->andReturn( [ 'minify' => '.democracy{color:red}' ] );

		$first_renderer = new Poll_Renderer__Double();
		$second_renderer = new Poll_Renderer__Double();

		$this->assertSame( [
			'notice_template_html' => '<template class="notice-template"></template>',
			'loader_html'          => '<div class="loader">Loader</div>',
			'styles'               => "\n<style id=\"democracy-poll-css\">.democracy{color:red}</style>\n",
		], $first_renderer->get_poll_assets_once() );
		$this->assertSame( [], $second_renderer->get_poll_assets_once() );
		$this->assertSame( [ 'democracy' ], wp_scripts()->queue );
	}

	/**
	 * @covers Poll_Renderer::render_poll()
	 */
	public function test__render_poll_uses_title_markup_and_includes_integrated_parts(): void {
		$renderer = $this->create_renderer();

		$html = $renderer->render_poll( 'voted', '<h2 class="poll-title">{question}</h2>' );

		$this->assertStringContainsString( '<style>poll styles</style>', $html );
		$this->assertStringContainsString( '<span>loader</span>', $html );
		$this->assertStringContainsString( '<template>notice</template>', $html );
		$this->assertStringContainsString( '<h2 class="poll-title">Question</h2>', $html );
		$this->assertStringContainsString( '<div>poll body</div>', $html );
		$this->assertStringContainsString( '<div>cache screens</div>', $html );
	}

	/**
	 * @covers Poll_Renderer::get_screen()
	 * @covers Poll_Renderer::render_poll()
	 */
	public function test__get_screen_renders_legacy_title_arguments(): void {
		$renderer = $this->create_renderer();

		$html = $renderer->get_screen( 'voted', '<h2>', '</h2>' );

		$this->assertStringContainsString( '<h2>Question</h2>', $html );
		$this->assertStringContainsString( '<div>poll body</div>', $html );
		$this->assertStringContainsString( '<div>cache screens</div>', $html );
	}

	private function create_renderer(): Poll_Renderer_Render__Double {
		$this->set_options( [
			'democracy_off'     => false,
			'keep_logs'         => true,
			'revote_off'        => false,
			'dont_show_results' => false,
			'archive_page_id'   => 0,
			'title_markup'      => '<strong class="dem-poll-title">{question}</strong>',
			'answs_max_height'  => '',
		] );

		$plugin = container()->get( Plugin::class );
		$plugin->is_cachegear_on = true;

		WP_Mock::userFunction( 'is_singular' )->andReturn( false );
		WP_Mock::userFunction( 'is_admin' )->andReturn( false );

		$poll = $this->create_poll( [ 'open' => true ] );

		return new Poll_Renderer_Render__Double( $poll );
	}

}
