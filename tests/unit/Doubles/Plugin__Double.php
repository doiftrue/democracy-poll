<?php

namespace DemocracyPoll\Doubles;

use DemocracyPoll\Options;
use DemocracyPoll\Plugin;
use function DemocracyPoll\container;

class Plugin__Double extends Plugin {

	public function __construct( array $options = [] ) {
		$options = new class( $options ) extends Options {

			public function __construct( array $options ) {
				$this->opt = $options;
			}

		};

		container()->set( Options::class, $options );
		$this->options = $options;
	}

}
