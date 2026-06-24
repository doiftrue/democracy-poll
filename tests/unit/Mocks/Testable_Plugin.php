<?php

namespace DemocracyPoll\Mocks;

use DemocracyPoll\Options;
use DemocracyPoll\Plugin;

class Testable_Plugin extends Plugin {

	public function __construct( array $options = [] ) {

		$this->opt = new class( $options ) extends Options {

			public function __construct( array $options ) {
				$this->opt = $options;
			}

		};

	}

}
