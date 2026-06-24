<?php

namespace DemocracyPoll\Mocks;

use DemocracyPoll\Poll;
use DemocracyPoll\Poll_Logs;

class Fake_Poll_Logs extends Poll_Logs {

	/** @var object[] */
	private array $logs;

	/**
	 * @param object[] $logs
	 */
	public function __construct( Poll $poll, array $logs = [] ) {
		parent::__construct( $poll );
		$this->logs = $logs;
	}

	public function get_user_vote_logs(): array {
		return $this->logs;
	}

}
