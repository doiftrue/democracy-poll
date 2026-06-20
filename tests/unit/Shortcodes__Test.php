<?php

namespace DemocracyPoll;

use Closure;

class Shortcodes__Test extends DemocTestCase {

	/**
	 * @covers \DemocracyPoll\Shortcodes::normalize_poll_id_attr()
	 */
	public function test__normalize_poll_id_attr(): void {
		$call = Closure::bind( static fn( $pid ) => Shortcodes::normalize_poll_id_attr( $pid ), null, Shortcodes::class );

		$this->assertSame( 'last', $call( 'last' ) );
		$this->assertSame( 'last', $call( '"last"' ) );
		$this->assertSame( 'last', $call( "\u{201D}last\u{201D}" ) );
		$this->assertSame( 'last', $call( "\u{2018}last\u{2019}" ) );
		$this->assertSame( 'last', $call( '&quot;last&quot;' ) );
		$this->assertSame( '123', $call( '123' ) );
		$this->assertSame( '123', $call( 123 ) );
	}

}
