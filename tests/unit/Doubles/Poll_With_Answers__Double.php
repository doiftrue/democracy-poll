<?php

namespace DemocracyPoll\Doubles;

use DemocracyPoll\Poll;
use DemocracyPoll\Poll_Answer;
use ReflectionProperty;

class Poll_With_Answers__Double extends Poll {

	public int $set_answers_calls = 0;

	/** @var Poll_Answer[] */
	private array $answers_stub;

	/**
	 * @param Poll_Answer[] $answers_stub
	 */
	public function __construct( array $answers_stub ) {
		parent::__construct( 0 );
		$this->answers_stub = $answers_stub;
	}

	public function set_answers(): void {
		$this->set_answers_calls++;

		$property = new ReflectionProperty( Poll::class, 'answers' );
		$property->setAccessible( true );
		$property->setValue( $this, $this->answers_stub );
	}

}
