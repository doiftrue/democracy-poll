<?php

namespace DemocracyPoll\Doubles;

use DemocracyPoll\Options;
use DemocracyPoll\Plugin;

class Plugin__Double extends Plugin {

	public function __construct( array $options = [] ) {

		$this->opt = new class( $options ) extends Options {

			public function __construct( array $options ) {
				$this->opt = $options;
			}

		};

	}

}
