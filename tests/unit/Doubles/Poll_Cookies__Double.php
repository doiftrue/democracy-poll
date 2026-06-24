<?php

namespace DemocracyPoll\Doubles;

use DemocracyPoll\Poll;
use DemocracyPoll\Poll_Cookies;

class Poll_Cookies__Double extends Poll_Cookies {

	public array $sent = [];
	public int $set_calls = 0;
	public int $set_not_voted_calls = 0;
	private ?string $value;
	private ?bool $is_not_voted;

	public function __construct( Poll $poll, ?string $value = null, ?bool $is_not_voted = null ) {
		parent::__construct( $poll );
		$this->value = $value;
		$this->is_not_voted = $is_not_voted;
	}

	public function get(): string {
		return $this->value ?? parent::get();
	}

	public function is_not_voted(): bool {
		return $this->is_not_voted ?? parent::is_not_voted();
	}

	public function set(): void {
		if( null !== $this->value || null !== $this->is_not_voted ){
			$this->set_calls++;
			return;
		}

		parent::set();
	}

	public function set_not_voted(): void {
		if( null !== $this->value || null !== $this->is_not_voted ){
			$this->set_not_voted_calls++;
			return;
		}

		parent::set_not_voted();
	}

	protected function send_cookie( string $name, string $value, int $expire ): void {
		$this->sent[] = [
			'name'   => $name,
			'value'  => $value,
			'expire' => $expire,
		];
	}

}
