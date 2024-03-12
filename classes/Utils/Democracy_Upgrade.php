<?php

class Democracy_Upgrade {

	private $old_ver;

	public function __construct() {
	    $this->old_ver = get_option( 'democracy_version' );
	}

	/**
	 * Plugin Upgrade
	 * Need initiated {@see Democracy_Poll} class.
	 * Нужно вызывать на странице настроек плагина, чтобы не грузить лишний раз сервер.
	 */
	public function upgrade() {

		if( $this->old_ver === DEM_VER || ! $this->old_ver ){
			return;
		}

		// обновим css
		democr()->regenerate_democracy_css();

		update_option( 'democracy_version', DEM_VER );

		$this->run_staff();
	}

	private function run_staff(){
		global $wpdb;

		$cols_q = $wpdb->get_results( "SHOW COLUMNS FROM $wpdb->democracy_q", OBJECT_K );
		$fields_q = array_keys( $cols_q );

		$cols_a = $wpdb->get_results( "SHOW COLUMNS FROM $wpdb->democracy_a", OBJECT_K );
		$fields_a = array_keys( $cols_a );

		$cols_log = $wpdb->get_results( "SHOW COLUMNS FROM $wpdb->democracy_log", OBJECT_K );
		$fields_log = array_keys( $cols_log );

		// 3.1.3
		if( ! in_array( 'end', $fields_q, true ) ){
			$wpdb->query( "ALTER TABLE $wpdb->democracy_q ADD `end` int(10) UNSIGNED NOT NULL DEFAULT 0 AFTER `added`;" );
		}

		if( ! in_array( 'note', $fields_q, true ) ){
			$wpdb->query( "ALTER TABLE $wpdb->democracy_q ADD `note` text NOT NULL;" );
		}

		if( in_array( 'current', $fields_q, true ) ){
			$wpdb->query( "ALTER TABLE $wpdb->democracy_q CHANGE `current` `active` tinyint(1) UNSIGNED NOT NULL DEFAULT 0;" );
			$wpdb->query( "ALTER TABLE $wpdb->democracy_q CHANGE `active` `open`    tinyint(1) UNSIGNED NOT NULL DEFAULT 0;" );
		}

		// 4.1
		if( ! in_array( 'aids', $fields_log, true ) ){
			// если нет поля aids, создаем 2 поля и индексы
			$wpdb->query( "ALTER TABLE $wpdb->democracy_log ADD `aids`   text NOT NULL;" );
			$wpdb->query( "ALTER TABLE $wpdb->democracy_log ADD `userid` bigint(20) UNSIGNED NOT NULL DEFAULT 0;" );
			$wpdb->query( "ALTER TABLE $wpdb->democracy_log ADD KEY userid (userid)" );
			$wpdb->query( "ALTER TABLE $wpdb->democracy_log ADD KEY qid (qid)" );
		}
	}

}
