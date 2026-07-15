<?php

namespace DemocracyPoll;

use DemocracyPoll\Admin\Tinymce_Button;
use DemocracyPoll\Doubles\Plugin__Double;

class Tinymce_Button__Test extends DemocTestCase {

	/**
	 * @covers \DemocracyPoll\Admin\Tinymce_Button::__construct()
	 * @covers \DemocracyPoll\Admin\Tinymce_Button::add_tinymce_plugin()
	 * @covers \DemocracyPoll\Admin\Tinymce_Button::register()
	 * @covers \DemocracyPoll\Admin\Tinymce_Button::tinymce_l10n()
	 * @covers \DemocracyPoll\Admin\Tinymce_Button::tinymce_register_button()
	 */
	public function test__registers_tinymce_filters_with_injected_plugin(): void {
		$plugin = new Plugin__Double();
		$plugin->url = 'https://example.test/democracy-poll';

		$button = new Tinymce_Button( $plugin );
		$button->register();

		$this->assertSame(
			[ 'demTiny' => 'https://example.test/democracy-poll/assets/admin/tinymce.js' ],
			apply_filters( 'mce_external_plugins', [] )
		);
		$this->assertSame(
			[ 'bold', 'separator', 'demTiny' ],
			apply_filters( 'mce_buttons', [ 'bold' ] )
		);
		$this->assertSame(
			[
				'Insert Poll of Democracy' => 'Insert Poll of Democracy',
				'Insert Poll ID'           => 'Insert Poll ID',
			],
			apply_filters( 'wp_mce_translation', [] )
		);
	}

}
