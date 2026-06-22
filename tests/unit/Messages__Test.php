<?php

namespace DemocracyPoll;

use DemocracyPoll\Helpers\Messages;

class Messages__Test extends DemocTestCase {

	/**
	 * @covers \DemocracyPoll\Helpers\Messages::__construct()
	 * @covers \DemocracyPoll\Helpers\Messages::messages_html()
	 */
	public function test__messages_html_returns_empty_string_without_messages(): void {
		$this->assertSame( "", ( new Messages() )->messages_html() );
	}

	/**
	 * @covers \DemocracyPoll\Helpers\Messages::add_error()
	 * @covers \DemocracyPoll\Helpers\Messages::add_notice()
	 * @covers \DemocracyPoll\Helpers\Messages::add_ok()
	 * @covers \DemocracyPoll\Helpers\Messages::add_warn()
	 * @covers \DemocracyPoll\Helpers\Messages::messages_html()
	 * @covers \DemocracyPoll\Helpers\Messages::msg_html()
	 */
	public function test__messages_html_renders_messages_in_expected_order(): void {
		$messages = new Messages();

		$messages->add_warn( "Careful" );
		$messages->add_ok( "Saved" );
		$messages->add_notice( "Heads up" );
		$messages->add_error( "Broken" );

		$this->assertSame(
			"<div class=\"notice-error notice is-dismissible\"><p>Broken</p></div>"
			. "<div class=\"notice-notice notice is-dismissible\"><p>Heads up</p></div>"
			. "<div class=\"notice-success notice is-dismissible\"><p>Saved</p></div>"
			. "<div class=\"notice-warning notice is-dismissible\"><p>Careful</p></div>",
			$messages->messages_html()
		);
	}

	/**
	 * @covers \DemocracyPoll\Helpers\Messages::msg_html()
	 */
	public function test__msg_html_maps_updated_alias_to_success(): void {
		$messages = new Messages();

		$this->assertSame(
			"<div class=\"notice-success notice is-dismissible\"><p>Saved</p></div>",
			$messages->msg_html( "Saved", "updated" )
		);
	}

}
