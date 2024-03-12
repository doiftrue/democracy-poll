<?php

function democr() {
	return Democracy_Poll::init();
}

function dem_set_dbtables() {
	global $wpdb;
	$wpdb->democracy_q   = $wpdb->prefix . 'democracy_q';
	$wpdb->democracy_a   = $wpdb->prefix . 'democracy_a';
	$wpdb->democracy_log = $wpdb->prefix . 'democracy_log';
}
