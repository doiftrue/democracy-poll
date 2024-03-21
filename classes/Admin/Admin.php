<?php
/**
 * @noinspection PhpUnnecessaryLocalVariableInspection
 * @noinspection OneTimeUseVariablesInspection
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */

namespace DemocracyPoll\Admin;

use function DemocracyPoll\plugin;
use function DemocracyPoll\options;

class Admin {

	public function __construct() {
	}

	public function init(){

		( new Admin_Page() )->init();

		// ссылка на настойки
		add_filter( 'plugin_action_links', [ $this, '_plugin_action_setting_page_link' ], 10, 2 );

		// TinyMCE кнопка WP2.5+
		if( options()->tinymce_button ){
			Tinymce_Button::init();
		}

		// метабокс
		if( ! options()->post_metabox_off ){
			Post_Metabox::init();
		}
	}

	## Ссылка на настройки со страницы плагинов
	public function _plugin_action_setting_page_link( $actions, $plugin_file ) {
		if( false === strpos( $plugin_file, basename( DEMOC_PATH ) ) ){
			return $actions;
		}

		$settings_link = sprintf( '<a href="%s">%s</a>', plugin()->admin_page_url(), __( 'Settings', 'democracy-poll' ) );
		array_unshift( $actions, $settings_link );

		return $actions;
	}

}




