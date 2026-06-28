<?php

namespace DemocracyPoll\Doubles;

class Poll_Renderer_Render__Double extends Poll_Renderer__Double {

	public function get_poll_assets_once(): array {
		return [
			'styles'               => '<style>poll styles</style>',
			'loader_html'          => '<span>loader</span>',
			'notice_template_html' => '<template>notice</template>',
		];
	}

	protected function get_poll_body( string $show_screen ): string {
		return '<div>poll body</div>';
	}

	protected function get_cache_screens(): string {
		return '<div>cache screens</div>';
	}

}
