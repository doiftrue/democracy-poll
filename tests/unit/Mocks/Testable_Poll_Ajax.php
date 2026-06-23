<?php

namespace DemocracyPoll;

class Testable_Poll_Ajax extends Poll_Ajax {

	public Poll $poll;
	public Poll_Renderer $renderer;
	public Poll_Voting_Service $voting;

	public function __construct( Poll $poll, Poll_Renderer $renderer, Poll_Voting_Service $voting ) {
		$this->poll = $poll;
		$this->renderer = $renderer;
		$this->voting = $voting;
	}

	protected function create_poll(): Poll {
		return $this->poll;
	}

	protected function create_renderer( Poll $poll ): Poll_Renderer {
		return $this->renderer;
	}

	protected function create_voting_service( Poll $poll ): Poll_Voting_Service {
		return $this->voting;
	}

}
