<?php
/**
 * Plugin Name: Democracy Poll
 * Description: Allows creation of democratic polls. Visitors can vote for multiple answers and add their own answers.
 *
 * Author: Kama
 * Author URI: https://wp-kama.com/
 * Plugin URI: https://wp-kama.ru/67
 *
 * Text Domain: democracy-poll
 * Domain Path: /languages/
 *
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * Version: 6.0.4
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

/**
 * NOTE: Init the plugin later on the 'after_setup_theme' hook to
 * run current_user_can() later to avoid possible conflicts.
 */
add_action( 'after_setup_theme', [ plugin(), 'init' ] );
