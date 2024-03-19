<?php

namespace DemocracyPoll;

// TODO: decompose class

use DemocracyPoll\Helpers\Kses;

class Plugin {

	/** @var bool only access to add/edit poll and so on. */
	public $admin_access;

	/** @var bool full access to change settings and so on. */
	public $super_access;

	/** @var Poll_Ajax */
	public $poll_ajax;

	/** @var Options */
	public $opt;

	/** @var Admin\Admin */
	public $admin;

	/** @var Helpers\Messages  */
	public $msg;

	public function __construct() {

		$this->set_access_caps();

		$this->opt = new \DemocracyPoll\Options();

		$this->msg = new \DemocracyPoll\Helpers\Messages();
	}

	public function init() {
		Kses::set_allowed_tags();
		$this->load_textdomain();

		// admin part
		if(
			\DemocracyPoll\Utils\Activator::$activation_running
			||
			( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) )
		){
			$this->admin = new \DemocracyPoll\Admin\Admin();
			$this->admin->init();
		}
		// front-end
		else{
			( new Shortcodes() )->init();

			$this->poll_ajax = new Poll_Ajax();
			$this->poll_ajax->init();
		}

		// For front part localisation and custom translation setup
		\DemocracyPoll\Admin\Admin_Page_l10n::add_gettext_filter();

		if( is_multisite() ){
			add_action( 'switch_blog', 'democracy_set_db_tables' );
		}

		// menu in the admin bar
		if( $this->admin_access && demopt()->toolbar_menu ){
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
		if( ! $this->admin_access && demopt()->access_roles ){
			foreach( wp_get_current_user()->roles as $role ){
				if( in_array( $role, demopt()->access_roles, true ) ){
					$this->admin_access = true;
					break;
				}
			}
		}
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
	 * @param object|string|int $poll  Poll object.
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

		if( $poll && (int) $poll->added_user === (int) get_current_user_id() ){
			return true;
		}

		return false;
	}

	/**
	 * Проверяет, используется ли страничный плагин кэширования на сайте.
	 */
	public function is_cachegear_on(): bool {

		if( demopt()->force_cachegear ){
			return true;
		}

		$status = apply_filters( 'dem_cachegear_status', null );

		if( null !== $status ){
			return (bool) $status;
		}

		// wp total cache
		if(
			class_exists( \W3TC\Dispatcher::class )
			&& \W3TC\Dispatcher::component( 'ModuleStatus' )
			&& \W3TC\Dispatcher::component( 'ModuleStatus' )->is_enabled( 'pgcache' )
		){
			return true;
		}
		// wp super cache
		if( defined( 'WPCACHEHOME' ) && @ $GLOBALS['cache_enabled'] ){
			return true;
		}
		// WordFence
		if( class_exists( \wfConfig::class ) && wfConfig::get( 'cacheType' ) === 'falcon' ){
			return true;
		}
		// WP Rocket
		if( class_exists( \HyperCache::class ) ){
			return true;
		}
		// Quick Cache
		if( function_exists( '\quick_cache\plugin' ) && \quick_cache\plugin()->options['enable'] ){
			return true;
		}
		// wp-fastest-cache
		// aio-cache

		return false;
	}

	// FRONT ---

	# добавляет стили в WP head
	public function add_css_once() {
		static $once = 0;
		if( $once++ ){
			return '';
		}

		$demcss = get_option( 'democracy_css' );
		$minify = @ $demcss['minify'];

		if( $minify ){
			return "\n<!--democracy-->\n" . '<style type="text/css">' . $minify . '</style>' . "\n";
		}
	}

	# добавляет скрипты в подвал
	public function add_js_once() {
		static $once = 0;
		if( $once++ ){
			return;
		}

		// inline HTML
		if( demopt()->inline_js_css ){
			wp_enqueue_script( 'jquery' );
			add_action( ( is_admin() ? 'admin_footer' : 'wp_footer' ), [ __CLASS__, '_add_js_wp_footer' ], 0 );
			// подключаем через фильтр, потому что иногда вылазиет баг, когда опрос добавляется прямо в контент...
			//return "\n" .'<script type="text/javascript">'. file_get_contents( DEMOC_PATH .'js/democracy.min.js' ) .'</script>'."\n";
		}
		else{
			wp_enqueue_script( 'democracy', DEMOC_URL . 'js/democracy.min.js', [], DEM_VER, true );
		}
	}

	public static function _add_js_wp_footer() {
		echo "\n<!--democracy-->\n" .
		     '<script type="text/javascript">' . file_get_contents( DEMOC_PATH . 'js/democracy.min.js' ) . '</script>' . "\n";
	}

}

