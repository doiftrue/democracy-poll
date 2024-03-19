<?php

function democr(): \DemocracyPoll\Plugin {
	static $inst;
	$inst || $inst = new \DemocracyPoll\Plugin();

	return $inst;
}

function demopt(): \DemocracyPoll\Options {
	return democr()->opt;
}

function democracy_set_db_tables() {
	global $wpdb;
	$wpdb->democracy_q   = $wpdb->prefix . 'democracy_q';
	$wpdb->democracy_a   = $wpdb->prefix . 'democracy_a';
	$wpdb->democracy_log = $wpdb->prefix . 'democracy_log';
}
