<?php

namespace DemocracyPoll\Doubles;

use DemocracyPoll\Poll;
use DemocracyPoll\Poll_Logs;

class Poll_Logs__Double extends Poll_Logs {

	/** @var object[] */
	private array $logs;
	public string $fingerprint = '';
	private bool $identity_resolved;

	/**
	 * @param object[] $logs
	 */
	public function __construct( Poll $poll, array $logs = [], bool $identity_resolved = false ) {
		parent::__construct( $poll );
		$this->logs = $logs;
		$this->identity_resolved = $identity_resolved;
	}

	public function get_user_vote_logs(): array {
		return $this->logs;
	}

	public function set_fingerprint( string $fingerprint ): void {
		$this->fingerprint = $fingerprint;
	}

	public function is_identity_resolved(): bool {
		return $this->identity_resolved;
	}

}
