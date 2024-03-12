<?php
/**
 * Plugin Name: Democracy Poll
 * Description: Allows to create democratic polls. Visitors can vote for more than one answer & add their own answers.
 *
 * Author: Kama
 * Author URI: https://wp-kama.com/
 * Plugin URI: https://wp-kama.ru/67
 *
 * Text Domain: democracy-poll
 * Domain Path: /languages/
 *
 * Requires at least: 4.7
 * Requires PHP: 7.0
 *
 * Version: 5.6.0
 */


// no direct access
defined( 'ABSPATH' ) || exit;

__( 'Allows to create democratic polls. Visitors can vote for more than one answer & add their own answers.' );


$data = get_file_data( __FILE__, [ 'Version' => 'Version' ] );
define( 'DEM_VER', $data['Version'] );

define( 'DEMOC_MAIN_FILE', __FILE__ );
define( 'DEMOC_URL', plugin_dir_url( __FILE__ ) );
define( 'DEMOC_PATH', plugin_dir_path( __FILE__ ) );

require_once __DIR__ . '/autoload.php';

dem_set_dbtables();

register_activation_hook( __FILE__, [ Democracy_Activate::class, 'activate' ] );

add_action( 'plugins_loaded', 'democracy_poll_init' );
function democracy_poll_init() {

	Democracy_Poll::init();

	// enable widget
	if( democr()->opt( 'use_widget' ) ){
		add_action( 'widgets_init', function() {
			register_widget( Democracy_Widget::class );
		} );
	}
}


