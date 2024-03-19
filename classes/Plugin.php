<?php

namespace DemocracyPoll;

// TODO: decompose class

class Plugin {

	// only access to add/edit poll and so on.
	public $admin_access;

	// full access to change settings and so on.
	public $super_access;

	/** @var Poll_Ajax */
	public $poll_ajax;

	/** @var Options */
	public $options;

	/** @var Admin\Admin */
	public $admin;

	/** @var Helpers\Messages  */
	public $msg;

	// The tags allowed in questions and answers. Will be added to global $allowedtags.
	public static $allowed_tags = [
		'a'      => [ 'href' => true, 'rel' => true, 'name' => true, 'target' => true, ],
		'b'      => [],
		'strong' => [],
		'i'      => [],
		'em'     => [],
		'span'   => [ 'class' => true ],
		'code'   => [],
		'var'    => [],
		'del'    => [ 'datetime' => true, ],
		'img'    => [ 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'align' => true ],
		'h2'     => [],
		'h3'     => [],
		'h4'     => [],
		'h5'     => [],
		'h6'     => [],
	];

	public function __construct() {
		global $allowedtags;

		self::$allowed_tags = array_merge( $allowedtags, array_map( '_wp_add_global_attributes', self::$allowed_tags ) );

		$this->set_access_caps();

		$this->msg = new \DemocracyPoll\Helpers\Messages();
	}

	public function init() {

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
			$this->front_init();
		}

		$this->load_textdomain();

		// For front part localisation and custom translation setup
		\DemocracyPoll\Admin\Admin_Page_l10n::add_gettext_filter();

		if( is_multisite() ){
			add_action( 'switch_blog', 'democracy_set_db_tables' );
		}

		// меню в панели инструментов
		if( $this->admin_access && demopt()->toolbar_menu ){
			add_action( 'admin_bar_menu', [ $this, 'add_toolbar_node' ], 99 );
		}

		$this->hide_form_indexing();
	}

	protected function front_init() {

		// шоткод [democracy]
		add_shortcode( 'democracy', [ $this, 'poll_shortcode' ] );
		add_shortcode( 'democracy_archives', [ $this, 'archives_shortcode' ] );

		$this->poll_ajax = new Poll_Ajax();
		$this->poll_ajax->init();
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
		$this->super_access = apply_filters( 'dem_super_access', $is_adminor );

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
	 * wp_kses value with democracy allowed tags. For esc outputing strings...
	 */
	public function kses_html( $value ): string {
		return wp_kses( $value, self::$allowed_tags );
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

	/**
	 * Очищает данные ответа
	 *
	 * @param string|array $data  Что очистить? Если передана строка, удалить из нее недопустимые HTML теги.
	 *
	 * @return string|array Чистые данные.
	 */
	public function sanitize_answer_data( $data, $filter_type = '' ) {

		$allowed_tags = $this->admin_access ? self::$allowed_tags : 'strip';

		if( is_string( $data ) ){
			$data = wp_kses( trim( $data ), $allowed_tags );
		}
		else{
			foreach( $data as $key => & $val ){

				if( is_string( $val ) ){
					$val = trim( $val );
				}

				// допустимые теги
				if( $key === 'answer' ){
					$val = wp_kses( $val, $allowed_tags );
				}
				// числа
				elseif( in_array( $key, [ 'qid', 'aid', 'votes' ] ) ){
					$val = (int) $val;
				}
				// остальное
				else{
					$val = wp_kses( $val, 'strip' );
				}
			}
		}

		return apply_filters( 'dem_sanitize_answer_data', $data, $filter_type );
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
			$poll = \DemPoll::get_poll( $poll );
		}

		if( $poll && (int) $poll->added_user === (int) get_current_user_id() ){
			return true;
		}

		return false;
	}

	// FRONT ---

	# шоткод архива опросов
	public function archives_shortcode( $args ) {

		$args = shortcode_atts( [
			'before_title'   => '',
			'after_title'    => '',
			'active'         => null,    // 1 (active), 0 (not active) or null (param not set).
			'open'           => null,    // 1 (opened), 0 (closed) or null (param not set) polls.
			'screen'         => 'voted',
			'per_page'       => 10,
			'add_from_posts' => true,    // add From posts: html block
			'orderby'        => '',      // string|array - [ 'open' => 'ASC' ] | 'open' | rand
		], $args );

		return '<div class="dem-archives-shortcode">' . get_democracy_archives( $args ) . '</div>';
	}

	# шоткод опроса
	public function poll_shortcode( $atts ) {

		$atts = shortcode_atts( [
			'id' => '', // number or 'current', 'last'
			// 'before_title'  => '', // IMP! can't add this - security reason
			// 'after_title'   => '', // IMP! can't add this - security reason

		], $atts, 'democracy' );

		// для опредления к какой записи относиться опрос. проверка, если шорткод вызван не из контента...
		$post_id = ( is_singular() && is_main_query() ) ? $GLOBALS['post'] : 0;

		if( $atts['id'] === 'current' ){
			$atts['id'] = \DemocracyPoll\Admin\Post_Metabox::get_post_poll_id( $post_id );
		}

		return '<div class="dem-poll-shortcode">' . get_democracy_poll( $atts['id'], '', '', $post_id ) . '</div>';
	}

	# добавляет стили в WP head
	function add_css_once() {
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
	function add_js_once() {
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

	static function _add_js_wp_footer() {
		echo "\n<!--democracy-->\n" .
		     '<script type="text/javascript">' . file_get_contents( DEMOC_PATH . 'js/democracy.min.js' ) . '</script>' . "\n";
	}

	/**
	 * Получает объекты записей к которым прикреплен опрос (где испльзуется шорткод).
	 *
	 * @param object $poll  Объект текущего опроса.
	 *
	 * @return array|false Массив объектов записей
	 */
	public function get_in_posts_posts( $poll ) {
		global $wpdb;

		if( empty( $poll->in_posts ) || empty( $poll->id ) ){
			return false;
		}

		$pids = explode( ',', $poll->in_posts );

		$posts = [];
		$delete_pids = []; // удалим ID записей которых теперь уже нет...

		foreach( $pids as $post_id ){
			if( $post = get_post( $post_id ) ){
				$posts[] = $post;
			}
			else{
				$delete_pids[] = $post_id;
			}
		}

		if( $delete_pids ){
			$new_in_posts = array_diff( $pids, $delete_pids );
			$wpdb->update( $wpdb->democracy_q, [ 'in_posts' => implode( ',', $new_in_posts ) ], [ 'id' => $poll->id ] );
		}

		return $posts;
	}

	/**
	 * Проверяет является ли переданный ответ новым ответом - NEW
	 *
	 * @param object $answer  Объект ответа
	 */
	public function is_new_answer( $answer ): bool {
		return preg_match( '~-new$~', $answer->added_by );
	}

}

