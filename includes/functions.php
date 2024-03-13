<?php

function democr() {
	return Democracy_Poll::instance();
}

function demopt() {
	static $inst;
	$inst || $inst = new \DemocracyPoll\Options();

	return $inst;
}

function democracy_set_db_tables() {
	global $wpdb;
	$wpdb->democracy_q   = $wpdb->prefix . 'democracy_q';
	$wpdb->democracy_a   = $wpdb->prefix . 'democracy_a';
	$wpdb->democracy_log = $wpdb->prefix . 'democracy_log';
}
