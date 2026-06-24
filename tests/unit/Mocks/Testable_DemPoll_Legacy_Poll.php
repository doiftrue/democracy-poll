<?php

namespace DemocracyPoll\Mocks;

use DemocracyPoll\Poll;

class Testable_DemPoll_Legacy_Poll extends Poll {

	public int $set_answers_calls = 0;

	public function __construct() {
		parent::__construct( 0 );
	}

	public function set_answers(): void {
		$this->set_answers_calls++;
	}

}
