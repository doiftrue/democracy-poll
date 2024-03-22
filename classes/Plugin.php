<?php

namespace DemocracyPoll;

use DemocracyPoll\Admin\Admin;
use DemocracyPoll\Admin\Admin_Page_l10n;
use DemocracyPoll\Helpers\Helpers;
use DemocracyPoll\Helpers\Kses;
use DemocracyPoll\Helpers\Messages;
use DemocracyPoll\Utils\Activator;

class Plugin {

	/** @var bool only access to add/edit poll and so on. */
	public $admin_access;

	/** @var bool full access to change settings and so on. */
	public $super_access;

	/** @var Poll_Ajax */
	public $poll_ajax;

	/** @var Options */
	public $opt;

	/** @var Admin */
	public $admin;

	/** @var Messages  */
	public $msg;

	/** @var bool whether page caching is enabled */
	public $is_cachegear_on;

	public function __construct() {
		$this->opt = new Options();
		$this->msg = new Messages();
	}

	public function basic_init() {
		$this->opt->set_opt();

		Activator::set_db_tables();
		if( is_multisite() ){
			add_action( 'switch_blog', [ Activator::class, 'set_db_tables' ] );
		}

		$this->set_access_caps();
		Kses::set_allowed_tags();
		$this->load_textdomain();
	}

	public function init() {
		$this->basic_init();

		$this->set_is_cachegear_on();

		// admin part
		if( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ){
			$this->admin = new Admin();
			$this->admin->init();
		}

		( new Shortcodes() )->init();
		$this->poll_ajax = new Poll_Ajax();
		$this->poll_ajax->init();

		// For front-end localisation and custom translation
		Admin_Page_l10n::add_gettext_filter();

		// menu in the admin bar
		if( $this->admin_access && $this->opt->toolbar_menu ){
			add_action( 'admin_bar_menu', [ $this, 'add_toolbar_node' ], 99 );
		}

		$this->hide_form_indexing();
	}

	// hide duplicate content. For 5+ versions it's no need
	private function hide_form_indexing() {
		// hide duplicate content. For 5+ versions it's no need
		if(
			isset( $_GET['dem_act'] )
			|| isset( $_GET['dem_action'] )
			|| isset( $_GET['dem_pid'] )
			|| isset( $_GET['show_addanswerfield'] )
			|| isset( $_GET['dem_add_user_answer'] )
		){
			add_action( 'wp', function() {
				status_header( 404 );
			} );

			add_action( 'wp_head', function() {
				echo "\n<!--democracy-poll-->\n" . '<meta name="robots" content="noindex,nofollow">' . "\n";
			} );
		}
	}

	private function set_access_caps() {
		$is_adminor = current_user_can( 'manage_options' );

		// access to change settings...
		$this->super_access = (bool) apply_filters( 'dem_super_access', $is_adminor );

		// access to add/edit poll and so on...
		$this->admin_access = $is_adminor;

		// open admin manage access for other roles
		if( ! $this->admin_access && $this->opt->access_roles ){
			foreach( wp_get_current_user()->roles as $role ){
				if( in_array( $role, $this->opt->access_roles, true ) ){
					$this->admin_access = true;
					break;
				}
			}
		}
	}

	private function set_is_cachegear_on() {

		if( $this->opt->force_cachegear ){
			$this->is_cachegear_on = true;
			return;
		}

		$status = apply_filters( 'dem_cachegear_status', null );
		if( null !== $status ){
			$this->is_cachegear_on = (bool) $status;
			return;
		}

		$this->is_cachegear_on = Helpers::is_page_cache_plugin_on();
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'democracy-poll', false, basename( DEMOC_PATH ) . '/languages/' );
	}

	/**
	 * @param \WP_Admin_Bar $toolbar
	 */
	public function add_toolbar_node( $toolbar ) {

		$toolbar->add_node( [
			'id'    => 'dem_settings',
			'title' => 'Democracy',
			'href'  => $this->admin_page_url(),
		] );

		$list = [
			''                 => __( 'Polls List', 'democracy-poll' ),
			'add_new'          => __( 'Add Poll', 'democracy-poll' ),
			'logs'             => __( 'Logs', 'democracy-poll' ),
			'general_settings' => __( 'Settings', 'democracy-poll' ),
			'design'           => __( 'Theme Settings', 'democracy-poll' ),
			'l10n'             => __( 'Texts changes', 'democracy-poll' ),
		];

		if( ! $this->super_access ){
			unset( $list['general_settings'], $list['design'], $list['l10n'] );
		}

		foreach( $list as $subpage => $title ){
			$toolbar->add_node( [
				'parent' => 'dem_settings',
				'id'     => $subpage ?: 'polls_list',
				'title'  => $title,
				'href'   => add_query_arg( [ 'subpage' => $subpage ], $this->admin_page_url() ),
			] );
		}
	}

	/**
	 * Returns the URL to the main page of the plugin settings.
	 */
	public function admin_page_url(): string {
		return admin_url( 'options-general.php?page=' . basename( DEMOC_PATH ) );
	}

	/**
	 * A link to edit the poll.
	 *
	 * @param int $poll_id  Poll ID
	 *
	 * @return string URL
	 */
	public function edit_poll_url( $poll_id ): string {
		return $this->admin_page_url() . '&edit_poll=' . (int) $poll_id;
	}

	/**
	 * Check if current or specified user can edit specified poll.
	 *
	 * @param \DemPoll|object|int $poll  Poll object or poll id.
	 */
	public function cuser_can_edit_poll( $poll ): bool {

		if( $this->super_access ){
			return true;
		}

		if( ! $this->admin_access ){
			return false;
		}

		// get poll object
		if( is_numeric( $poll ) ){
			$poll = \DemPoll::get_poll_object( $poll );
		}

		return $poll && (int) $poll->added_user === (int) get_current_user_id();
	}

	public function get_minified_styles_once(): string {
		static $once = 0;
		if( $once++ ){
			return '';
		}

		$demcss = get_option( 'democracy_css' );
		$minified = $demcss['minify'] ?? '';

		return $minified
			? "\n" . '<style id="democracy-poll">' . $minified . '</style>' . "\n"
			: '';
	}

	/**
	 * Adds scripts to the footer.
	 */
	public function add_js_once() {
		static $once = 0;
		if( $once++ ){
			return;
		}

		// inline HTML
		if( $this->opt->inline_js_css ){
			wp_enqueue_script( 'jquery' );
			add_action( ( is_admin() ? 'admin_footer' : 'wp_footer' ), [ __CLASS__, '_add_js_wp_footer' ], 0 );
		}
		else{
			wp_enqueue_script( 'democracy', DEMOC_URL . 'js/democracy.min.js', [], DEM_VER, true );
		}
	}

	public static function _add_js_wp_footer() {
		echo "\n" . '<script id="democracy-poll">' . file_get_contents( DEMOC_PATH . 'js/democracy.min.js' ) . '</script>' . "\n";
	}

}

