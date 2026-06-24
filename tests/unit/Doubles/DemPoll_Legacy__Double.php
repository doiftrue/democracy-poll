<?php

namespace DemocracyPoll\Doubles;

use DemocracyPoll\Poll;

/**
 * @see DemPoll_Legacy
 */
class DemPoll_Legacy__Double extends Poll {

	public int $set_answers_calls = 0;

	public function __construct() {
		parent::__construct( 0 );
	}

	public function set_answers(): void {
		$this->set_answers_calls++;
	}

}
