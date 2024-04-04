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
 * Version: 6.0.3
 */

namespace DemocracyPoll;

defined( 'ABSPATH' ) || exit;

$data = get_file_data( __FILE__, [ 'Version' => 'Version' ] );
define( 'DEM_VER', $data['Version'] );

define( 'DEMOC_MAIN_FILE', __FILE__ );
define( 'DEMOC_URL', plugin_dir_url( __FILE__ ) );
define( 'DEMOC_PATH', plugin_dir_path( __FILE__ ) );

require_once __DIR__ . '/autoload.php';

register_activation_hook( __FILE__, [ \DemocracyPoll\Utils\Activator::class, 'activate' ] );

add_action( 'plugins_loaded', '\DemocracyPoll\init' );
function init() {
	plugin()->init();

	// enable widget
	if( options()->use_widget ){
		add_action( 'widgets_init', function() {
			register_widget( \DemocracyPoll\Poll_Widget::class );
		} );
	}
}


