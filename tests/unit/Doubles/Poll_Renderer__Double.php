<?php

namespace DemocracyPoll\Doubles;

use DemocracyPoll\Poll;
use DemocracyPoll\Poll_Renderer;

class Poll_Renderer__Double extends Poll_Renderer {

	public function __construct( ?Poll $poll = null ) {
		parent::__construct( $poll ?? new Poll( 0 ) );
	}

	public function get_poll_assets_once(): array {
		return parent::get_poll_assets_once();
	}

	protected function get_loader_html(): string {
		return '<div class="loader">Loader</div>';
	}

	protected function notice_template_html(): string {
		return '<template class="notice-template"></template>';
	}

}
