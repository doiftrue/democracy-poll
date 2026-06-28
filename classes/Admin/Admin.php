<?php
/**
 * @noinspection PhpUnnecessaryLocalVariableInspection
 * @noinspection OneTimeUseVariablesInspection
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */

namespace DemocracyPoll\Admin;

use DemocracyPoll\Options;
use DemocracyPoll\Plugin;
use function DemocracyPoll\container;

class Admin {

	private Options $options;
	private Plugin $plugin;

	public function __construct( Plugin $plugin, Options $options ) {
		$this->plugin = $plugin;
		$this->options = $options;
	}

	public function init(): void {
		container()->get( Admin_Page::class )->init(); /** @see Admin_Page::__construct() */

		add_filter( 'plugin_action_links', [ $this, '_plugin_action_setting_page_link' ], 10, 2 );

		// TinyMCE button WP 2.5+
		if( $this->options->tinymce_button ){
			Tinymce_Button::init();
		}

		if( ! $this->options->post_metabox_off ){
			Post_Metabox::init();
		}
	}

	public function _plugin_action_setting_page_link( $actions, $plugin_file ) {
		if( false === strpos( $plugin_file, basename( $this->plugin->dir ) ) ){
			return $actions;
		}

		$settings_link = sprintf( '<a href="%s">%s</a>', $this->plugin->admin_page_url, __( 'Settings', 'democracy-poll' ) );
		array_unshift( $actions, $settings_link );

		return $actions;
	}

}
