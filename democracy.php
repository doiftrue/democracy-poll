<?php
/**
 * Plugin Name: Democracy Poll
 * Description: Allows to create democratic polls. Visitors can vote for more than one answer & add their own answers.
 *
 * Author: Kama
 * Author URI: http://wp-kama.ru/
 * Plugin URI: http://wp-kama.ru/id_67/plagin-oprosa-dlya-wordpress-democracy-poll.html
 *
 * Text Domain: democracy-poll
 * Domain Path: /languages/
 *
 * Requires at least: 4.6
 * Requires PHP: 5.6
 *
 * Version: 5.6.0
 */


// no direct access
defined( 'ABSPATH' ) || exit;

__( 'Allows to create democratic polls. Visitors can vote for more than one answer & add their own answers.' );


$data = get_file_data( __FILE__, [ 'Version' =>'Version' ] );
define( 'DEM_VER', $data['Version'] );

define( 'DEMOC_MAIN_FILE', __FILE__ );
define( 'DEMOC_URL', plugin_dir_url( __FILE__ ) );
define( 'DEMOC_PATH', plugin_dir_path( __FILE__ ) );


/**
 * Sets democracy tables.
 *
 * @return void
 */
function dem_set_dbtables(){
	global $wpdb;
	$wpdb->democracy_q   = $wpdb->prefix .'democracy_q';
	$wpdb->democracy_a   = $wpdb->prefix .'democracy_a';
	$wpdb->democracy_log = $wpdb->prefix .'democracy_log';
}
dem_set_dbtables();


require_once DEMOC_PATH .'admin/upgrade-activate-funcs.php';
require_once DEMOC_PATH .'theme-functions.php';

require_once DEMOC_PATH .'/classes/DemPoll.php';
require_once DEMOC_PATH .'/classes/Democracy_Poll.php';
require_once DEMOC_PATH .'/classes/Admin/Democracy_Poll_Admin.php';

register_activation_hook( __FILE__, 'democracy_activate' );

add_action( 'plugins_loaded', 'democracy_poll_init' );
function democracy_poll_init(){

	Democracy_Poll::init();

	// enable widget
	if( democr()->opt( 'use_widget' ) ){
		require_once DEMOC_PATH . 'widget_democracy.php';
	}
}

function democr(){
	return Democracy_Poll::init();
}



