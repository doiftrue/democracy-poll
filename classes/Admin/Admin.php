<?php
/**
 * @noinspection PhpUnnecessaryLocalVariableInspection
 * @noinspection OneTimeUseVariablesInspection
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */

namespace DemocracyPoll\Admin;

/// TODO: refactor - extract to separate class

class Admin extends \Democracy_Poll {

	public function __construct() {
		parent::__construct();

		// add the management page to the admin nav bar
		if( $this->admin_access ){
			add_action( 'admin_menu', [ $this, 'register_option_page' ] );

			// Сохранение настроек экрана
			add_filter( 'set-screen-option', function( $status, $option, $value ) {
				return in_array( $option, [ 'dem_polls_per_page', 'dem_logs_per_page' ] ) ? (int) $value : $status;
			}, 10, 3 );
		}

		// ссылка на настойки
		add_filter( 'plugin_action_links', [ $this, '_plugin_action_setting_page_link' ], 10, 2 );

		// TinyMCE кнопка WP2.5+
		if( demopt()->tinymce_button ){
			Tinymce_Button::init();
		}

		// метабокс
		if( ! demopt()->post_metabox_off ){
			Post_Metabox::init();
		}
	}

	## Страница плагина
	public function register_option_page() {
		if( ! $this->admin_access ){
			return;
		}

		$title = __( 'Democracy Poll', 'democracy-poll' );
		$hook_name = add_options_page( $title, $title, 'edit_posts', basename( DEMOC_PATH ), [ $this, 'admin_page_output' ] );
		// notice: `edit_posts` (role more then subscriber) because capability tests inside the `admin_page.php` and `admin_page_load()`

		add_action( "load-$hook_name", [ $this, 'admin_page_load' ] );
	}

	## admin page html
	public function admin_page_output() {
		if( isset( $_GET['msg'] ) && $_GET['msg'] === 'created' ){
			$this->msg->add_ok( __( 'New Poll Added', 'democracy-poll' ) );
		}

		require DEMOC_PATH . 'admin/admin_page.php';
	}

	## предватирельная загрузка страницы настроек плагина, подключение стилей, скриптов, запросов и т.д.
	function admin_page_load() {
		// run upgrade
		if( $this->super_access ){
			// check and try forse upgrade
			if( isset( $_POST['dem_forse_upgrade'] ) ){
				update_option( 'democracy_version', '0.1' );
			} // hack

			( new \DemocracyPoll\Utils\Upgrader() )->upgrade();

			if( isset( $_POST['dem_forse_upgrade'] ) ){
				wp_redirect( $_SERVER['REQUEST_URI'] );
				exit;
			}
		}

		//wp_enqueue_script('ace', DEMOC_URL .'admin/ace/src-min-noconflict/ace.js', array(), DEM_VER, true );

		// Iris Color Picker
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_style( 'wp-color-picker' );

		// datepicker
		wp_enqueue_script( 'jquery-ui-datepicker' );
		//ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/themes/smoothness/jquery-ui.css
		wp_enqueue_style( 'jquery-style', DEMOC_URL . 'admin/css/jquery-ui.css', [], DEM_VER );

		// democracy
		wp_enqueue_script( 'democracy-scripts', DEMOC_URL . 'js/admin.js', [ 'jquery'/*,'ace'*/ ], DEM_VER, true );
		wp_enqueue_style( 'democracy-styles', DEMOC_URL . 'admin/css/admin.css', [], DEM_VER );


		// handlers ------
		// adminform_verified
		if( isset( $_REQUEST['_demnonce'] ) && wp_verify_nonce( $_REQUEST['_demnonce'], 'dem_adminform' ) ){

			// options update
			if( $this->super_access ){
				$up = false;
				if( isset( $_POST['dem_save_l10n'] ) || isset( $_POST['dem_reset_l10n'] ) ){
					// обновляем произвольную локализацию
					if( isset( $_POST['dem_save_l10n'] ) ){
						$up = $this->update_l10n();
					}

					// сбрасываем произвольную локализацию
					if( isset( $_POST['dem_reset_l10n'] ) ){
						$up = update_option( 'democracy_l10n', [] );
					}

					// clear_cache
					self::handle_front_l10n( 'clear_cache' );
				}

				if( isset( $_POST['dem_save_main_options'] ) ){
					$up = demopt()->update_options( 'main' );
				}
				if( isset( $_POST['dem_reset_main_options'] ) ){
					$up = demopt()->reset_options( 'main' );
				}
				if( isset( $_POST['dem_save_design_options'] ) ){
					$up = demopt()->update_options( 'design' );
				}
				if( isset( $_POST['dem_reset_design_options'] ) ){
					$up = demopt()->reset_options( 'design' );
				}

				// hack to immediately apply the option change
				if( $up ){
					demopt()->toolbar_menu
						? add_action( 'admin_bar_menu', [ $this, 'toolbar', ], 99 )
						: remove_action( 'admin_bar_menu', [ $this, 'toolbar' ], 99 );
				}

				// запрос на создание страницы архива
				if( isset( $_GET['dem_create_archive_page'] ) ){
					$this->dem_create_archive_page();
				}

				// Clear logs
				if( isset( $_GET['dem_clear_logs'] ) ){
					$this->clear_logs();
				}
				if( isset( $_GET['dem_del_closed_polls_logs'] ) ){
					$this->clear_closed_polls_logs();
				}
				if( isset( $_GET['dem_del_new_mark'] ) ){
					$this->clear_new_mark();
				}
			}

			// make life easy
			$_poll_id = 0;
			$fn__setgetcheck = static function( $name ) use ( & $_poll_id ) {
				if( empty( $_REQUEST[ $name ] ) ){
					return $_poll_id = 0;
				}

				$_poll_id = (int) $_REQUEST[ $name ];

				return democr()->cuser_can_edit_poll( $_poll_id ) ? $_poll_id : 0;
			};

			// Add/update a poll
			if( isset( $_POST['dmc_create_poll'] ) || ( $fn__setgetcheck( 'dmc_update_poll' ) ) ){
				$this->insert_poll_handler();
			}

			// delete a poll
			if( $fn__setgetcheck( 'delete_poll' ) ){
				$this->delete_poll( $_poll_id );
			}

			// activates a poll
			if( $fn__setgetcheck( 'dmc_activate_poll' ) ){
				$this->poll_activation( $_poll_id, true );
			}
			// deactivates a poll
			if( $fn__setgetcheck( 'dmc_deactivate_poll' ) ){
				$this->poll_activation( $_poll_id, false );
			}

			// open voting a poll
			if( $fn__setgetcheck( 'dmc_open_poll' ) ){
				$this->poll_opening( $_poll_id, 1 );
			}                    //echo "$_poll_id ";
			// close voting a poll
			if( $fn__setgetcheck( 'dmc_close_poll' ) ){
				$this->poll_opening( $_poll_id, 0 );
			}                    //echo "$_poll_id ";

		}

		// LOGS
		//if( isset($_GET['del_poll_logs']) && wp_verify_nonce($_GET['del_poll_logs'], 'del_poll_logs') )
		//	$this->del_poll_logs( $_GET['poll'] );

		// admin subpages (after handlers) ------
		$sp = $_GET['subpage'] ?? '';
		if( $sp === 'design' ){
			// CodeMirror
			if( function_exists( 'wp_enqueue_code_editor' ) ){
				add_action( 'admin_enqueue_scripts', function() {
					// подключаем редактор кода для HTML.
					$settings = wp_enqueue_code_editor( [ 'type' => 'text/css' ] );

					// инициализация
					wp_add_inline_script( 'code-editor', sprintf(
						'jQuery( function(){  wp.codeEditor.initialize( jQuery("textarea[name=additional_css]"), %s );  } );', wp_json_encode( $settings )
					) );
				}, 99 );
			}
		}
		elseif( $sp === 'general_settings' ){}
		elseif( $sp === 'l10n' ){}
		elseif( $sp === 'add_new' || isset( $_GET['edit_poll'] ) ){}
		// logs list
		elseif( $sp === 'logs' ){
			$this->list_table = new List_Table_Logs();
		}
		// polls list
		else{
			$this->list_table = new List_Table_Polls();
		}
	}

	### PLUGIN OPTIONS ----------


	## Обновляет произвольный текст перевода
	private function update_l10n(): bool {
		$new_l10n = stripslashes_deep( $_POST['l10n'] );

		foreach( $new_l10n as $key => & $val ){
			$val = trim( $val );

			// delete if no difference from original translations_api
			if( __( $key, 'democracy-poll' ) === $val ){
				unset( $new_l10n[ $key ] );
			}
			// sanitize value: Thanks to //pluginvulnerabilities.com/?p=2967
			else{
				$val = wp_kses( $val, \Democracy_Poll::$allowed_tags );
			}
		}

		return (bool) update_option( 'democracy_l10n', $new_l10n );
	}

	/**
	 * Получает существующие полные css файлы из каталога плагина
	 * @return array Возвращает массив имен (путей) к файлам
	 */
	public function _get_styles_files(): array {
		$arr = [];

		foreach( glob( DEMOC_PATH . 'styles/*.css' ) as $file ){
			if( preg_match( '~\.min~', basename( $file ) ) ){
				continue;
			}

			$arr[] = $file;
		}

		return $arr;
	}

	## deletes specified poll
	function delete_poll( $poll_id ) {
		global $wpdb;

		if( ! $poll_id = intval( $poll_id ) ){
			return;
		}

		$wpdb->delete( $wpdb->democracy_q, [ 'id' => $poll_id ] );
		$wpdb->delete( $wpdb->democracy_a, [ 'qid' => $poll_id ] );
		$wpdb->delete( $wpdb->democracy_log, [ 'qid' => $poll_id ] );

		$this->msg->add_ok( __( 'Poll Deleted', 'democracy-poll' ) . ": $poll_id" );
	}

	/**
	 * Закрывает/открывает голосование
	 *
	 * @param int  $poll_id  ID опроса
	 * @param bool $open     Что сделать, открыть или закрыть голосование?
	 */
	function poll_opening( $poll_id, $open ) {
		global $wpdb;

		if( ! $poll = \DemPoll::get_poll( $poll_id ) ){
			return;
		}

		$open = $open ? 1 : 0;

		$new_data = [ 'open' => $open ];

		// удаляем дату окончания при открытии голосования
		if( $open ){
			$new_data['end'] = 0;
		}
		// ставим дату при закрытии опроса и деактивируем опрос
		else{
			$new_data['end'] = current_time( 'timestamp' ) - 10;
			$this->poll_activation( $poll_id, false );
		}

		if( $wpdb->update( $wpdb->democracy_q, $new_data, [ 'id' => $poll->id ] ) ){
			$this->msg->add_ok( $open
				? __( 'Poll Opened', 'democracy-poll' )
				: __( 'Voting is closed', 'democracy-poll' )
			);
		}
	}

	/**
	 * Активирует/деактивирует опрос
	 *
	 * @param int  $poll_id     ID опроса
	 * @param bool $activation  Что сделать, активировать (true) или деактивировать?
	 */
	private function poll_activation( $poll_id, $activation = true ): bool {
		global $wpdb;

		$poll = \DemPoll::get_poll( $poll_id );
		if( ! $poll ){
			return false;
		}

		$active = (int) $activation;

		if( ! $poll->open && $active ){
			$this->msg->add_error( __( 'You can not activate closed poll...', 'democracy-poll' ) );

			return false;
		}

		$done = $wpdb->update( $wpdb->democracy_q, [ 'active' => $active ], [ 'id' => $poll->id ] );

		if( $done ){
			$this->msg->add_ok( $active
				? __( 'Poll Activated', 'democracy-poll' )
				: __( 'Poll Deactivated', 'democracy-poll' )
			);
		}

		return (bool) $done;
	}

	function insert_poll_handler() {
		$data = [];

		// collect all fields which start with 'dmc_'
		foreach( (array) $_POST as $key => $val ){
			if( str_starts_with( $key, 'dmc_' ) ){
				$data[ substr( $key, 4 ) ] = $val;
			}
		}

		$data = wp_unslash( $data );

		$this->insert_poll( $data );
	}

	/**
	 * Add or Update poll. Expects unslashed data.
	 *
	 * @param array $data  Data of added poll. If set 'qid' key poll wil be updated.
	 *
	 * @return bool True when added updated, False otherwise.
	 */
	function insert_poll( $data ) {
		global $wpdb;

		$orig_data = $data;

		$poll_id = (int) ( $data['qid'] ?? 0 );
		$update = (bool) $poll_id;

		// sanitize
		$data = (object) $this->sanitize_poll_data( $data );

		if( ! $data->question ){
			$this->msg->add_ok( 'error: question not set' );

			return false;
		}

		/// answers
		$old_answers = (array) ( $data->old_answers ?? [] );
		$new_answers = array_filter( (array) ( $data->new_answers ?? [] ) );

		// add data if insert new poll
		if( ! $update ){
			$data->added = current_time( 'timestamp' );
			$data->added_user = get_current_user_id();
			$data->open = 1; // poll is open by default
		}

		// Remove invalid for the table fields
		$q_fields = wp_list_pluck( $wpdb->get_results( "SHOW COLUMNS FROM $wpdb->democracy_q" ), 'Field' );
		$q_data = array_intersect_key( (array) $data, array_flip( $q_fields ) );

		do_action_ref_array( 'dem_before_insert_quest_data', [ & $q_data, & $old_answers, & $new_answers, $update ] );

		// UPDATE POLL
		if( $update ){
			$wpdb->update( $wpdb->democracy_q, $q_data, [ 'id' => $poll_id ] );

			// upadate answers
			if( 1 ){
				$ids = [];

				// Обновим старые ответы
				foreach( $old_answers as $aid => $anws ){
					$answ_row = $wpdb->get_row( "SELECT * FROM $wpdb->democracy_a WHERE aid = " . (int) $aid );

					// удалим метку NEW
					$added_by = $this->is_new_answer( $answ_row )
						? str_replace( '-new', '', $answ_row->added_by )
						: $answ_row->added_by;

					$order = $anws['aorder'];

					$wpdb->update(
						$wpdb->democracy_a,
						[
							'answer'   => $anws['answer'],
							'votes'    => $anws['votes'],
							'aorder'   => $order,
							'added_by' => $added_by,
						],
						[ 'qid' => $poll_id, 'aid' => $aid ]
					);

					// собираем ID, которые остались. Для исключения из удаления
					$ids[] = $aid;
					$max_order_num = isset( $max_order_num ) ? ( $max_order_num < $order ? $order : $max_order_num ) : $order;
				}

				// Удаляем удаленные ответы, которые есть в БД но нет в запросе
				if( 1 ){
					$ids = array_map( 'absint', $ids );
					$AND_NOT_IN = $ids ? sprintf( "AND aid NOT IN (" . implode( ',', $ids ) . ")" ) : '';
					$del_ids = $wpdb->get_col(
						"SELECT aid FROM $wpdb->democracy_a WHERE qid = $poll_id $AND_NOT_IN"
					);

					if( $del_ids ){
						// delete answers
						$deleted = $wpdb->query( "DELETE FROM $wpdb->democracy_a WHERE aid IN (" . implode( ',', $del_ids ) . ")" );

						// delete answers logs
						if( 1 ){
							// delete logs
							$user_voted_minus = $wpdb->query(
								"DELETE FROM $wpdb->democracy_log WHERE qid = $poll_id AND aids IN (" . implode( ',', $del_ids ) . ")"
							);

							// обновим значение 'users_voted' в бд
							if( $user_voted_minus ){
								$wpdb->query( self::users_voted_minus_sql( $user_voted_minus, $poll_id ) );
							}

							// Обновим мульти логи, где по несколько ответов: '321,654'
							$up_logs = $wpdb->get_results(
								"SELECT logid, aids FROM $wpdb->democracy_log
									WHERE qid = $poll_id AND aids RLIKE '(" . implode( '|', $del_ids ) . ")'"
							);

							foreach( $up_logs as $log ){
								$_ids_patt = implode( '|', $del_ids ); // pattern part
								$new_aids = preg_replace( "~^(?:$_ids_patt),|,(?:$_ids_patt)(?=,)|,(?:$_ids_patt)\$~", '', $log->aids );
								$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->democracy_log SET aids = %s WHERE logid = $log->logid", $new_aids ) );
							}
						}

						if( $deleted ){
							do_action( 'dem_answers_deleted', $del_ids, $poll_id );
						}
					}
				}

				// Добавим новые ответы
				foreach( $new_answers as $anws ){
					$anws = trim( $anws );

					if( $anws ){
						$wpdb->insert( $wpdb->democracy_a, [
							'answer' => $anws,
							'aorder' => ( $max_order_num ?? 0 ) ? $max_order_num++ : 0,
							'qid'    => $poll_id,
						] );
					}
				}
			}

			$this->msg->add_ok( __( 'Poll Updated', 'democracy-poll' ) );

			// collect answers users votes count
			// обновим 'users_voted' в questions после того как логи были обновлены, зависит от логов
			if( 1 ){
				$users_voted = 0;
				// соберем из логов
				if( $data->multiple && ! $data->users_voted ){
					$users_voted = $wpdb->get_var( "SELECT count(*) FROM $wpdb->democracy_log WHERE qid = " . (int) $poll_id );
				}
				// равно количеству голосов
				if( ! $data->multiple ){
					$users_voted = $wpdb->get_var( "SELECT SUM(votes) FROM $wpdb->democracy_a WHERE qid = " . (int) $poll_id );
				}
				//$users_voted = array_sum( wp_list_pluck($old_answers, 'votes') );

				if( $users_voted ){
					$wpdb->update( $wpdb->democracy_q, [ 'users_voted' => $users_voted ], [ 'id' => $poll_id ] );
				}
			}
		}
		// ADD POLL
		else{
			$wpdb->insert( $wpdb->democracy_q, $q_data );

			if( ! $poll_id = $wpdb->insert_id ){
				$this->msg->add_ok( 'error: sql error when adding poll data' );

				return false;
			}

			foreach( $new_answers as $answer ){
				$answer = trim( $answer );

				if( ! empty( $answer ) ){
					$wpdb->insert( $wpdb->democracy_a, [ 'answer' => $answer, 'qid' => $poll_id ] );
				}
			}

			wp_redirect( add_query_arg( [ 'msg' => 'created' ], $this->edit_poll_url( $poll_id ) ) );
		}

		do_action( 'dem_poll_inserted', $poll_id, $update );

		return true;
	}

	/**
	 * Sanitize all poll fields before save in db.
	 */
	public function sanitize_poll_data( $data ) {
		$original_data = $data;

		foreach( $data as $key => & $val ){
			if( is_string( $val ) ){
				$val = trim( $val );
			}

			// valid tags
			if( $key === 'question' || $key === 'note' ){
				$val = wp_kses( $val, self::$allowed_tags );
			}
			// date
			elseif( $key === 'end' || $key === 'added' ){
				if( preg_match( '~\d{1,2}-\d{1,2}-\d{4}~', $val ) ){
					$val = strtotime( $val );
				}
				else{
					$val = 0;
				}
			}
			// fix multiple
			elseif( $key === 'multiple' && $val == 1 ){
				$val = 2;
			}
			// numbers
			elseif( in_array( $key, [ 'qid', 'democratic', 'active', 'multiple', 'forusers', 'revote' ] ) ){
				$val = (int) $val;
			}
			// answers
			elseif( $key === 'old_answers' || $key === 'new_answers' ){
				if( is_string( $val ) ){
					$val = $this->sanitize_answer_data( $val );
				}
				else{
					foreach( $val as & $_val ){
						$_val = $this->sanitize_answer_data( $_val );
					}
					unset( $_val );
				}
			}
			// remove tags
			else{
				$val = wp_kses( $val, 'strip' );
			}
		}
		unset( $val );

		return apply_filters( 'demadmin_sanitize_poll_data', $data, $original_data );
	}


	#### CSS ------------
	## Обновляет опцию "democracy_css"
	public function update_democracy_css() {
		$additional_css = $_POST['additional_css'] ?? '';
		$additional = strip_tags( stripslashes( $additional_css ) );

		$this->regenerate_democracy_css( $additional );
	}

	## Регенерирует стили в настройках, на оснвое настроек. не трогает дополнительные стили
	public function regenerate_democracy_css( $additional = null ) {

		// чтобы при обновлении плагина, доп. стили не слетали
		if( $additional === null ){
			$css = get_option( 'democracy_css', [] );
			$additional = $css['additional_css'] ?? '';
		}

		// если нет, то тема отключена
		$base = $this->collect_base_css();

		$newdata = [
			'base_css'       => $base,
			'additional_css' => $additional,
			'minify'         => $this->cssmin( $base . $additional ),
		];

		update_option( 'democracy_css', $newdata );
	}

	## Собирает базовые стили.
	## @return css код стилей или '', если шаблон отключен.
	private function collect_base_css(): string {

		$tpl = demopt()->css_file_name;

		// выходим если не указан шаблон
		if( ! $tpl ){
			return '';
		}

		$button = demopt()->css_button;
		$loader = demopt()->loader_fill;

		$radios = demopt()->checkradio_fname;

		$out = '';
		$styledir = DEMOC_PATH . 'styles';

		$out .= $this->parse_cssimport( "$styledir/$tpl" );
		$out .= $radios ? "\n" . file_get_contents( "$styledir/checkbox-radio/$radios" ) : '';
		$out .= $button ? "\n" . file_get_contents( "$styledir/buttons/$button" ) : '';

		if( $loader ){
			$out .= "\n.dem-loader .fill{ fill: $loader !important; }\n";
			$out .= ".dem-loader .css-fill{ background-color: $loader !important; }\n";
			$out .= ".dem-loader .stroke{ stroke: $loader !important; }\n";
		}

		// progress line
		$d_bg       = demopt()->line_bg;
		$d_fill     = demopt()->line_fill;
		$d_height   = demopt()->line_height;
		$d_fillThis = demopt()->line_fill_voted;

		if( $d_bg ){
			$out .= "\n.dem-graph{ background: $d_bg !important; }\n";
		}
		if( $d_fill ){
			$out .= "\n.dem-fill{ background-color: $d_fill !important; }\n";
		}
		if( $d_fillThis ){
			$out .= ".dem-voted-this .dem-fill{ background-color:$d_fillThis !important; }\n";
		}
		if( $d_height ){
			$out .= ".dem-graph{ height:{$d_height}px; line-height:{$d_height}px; }\n";
		}

		if( $button ){
			// button
			$bbackground = demopt()->btn_bg_color;
			$bcolor      = demopt()->btn_color;
			$bbcolor     = demopt()->btn_border_color;
			// hover
			$bh_bg     = demopt()->btn_hov_bg;
			$bh_color  = demopt()->btn_hov_color;
			$bh_bcolor = demopt()->btn_hov_border_color;

			if( $bbackground ){
				$out .= "\n.dem-button{ background-color:$bbackground !important; }\n";
			}
			if( $bcolor ){
				$out .= ".dem-button{ color:$bcolor !important; }\n";
			}
			if( $bbcolor ){
				$out .= ".dem-button{ border-color:$bbcolor !important; }\n";
			}

			if( $bh_bg ){
				$out .= "\n.dem-button:hover{ background-color:$bh_bg !important; }\n";
			}
			if( $bh_color ){
				$out .= ".dem-button:hover{ color:$bh_color !important; }\n";
			}
			if( $bh_bcolor ){
				$out .= ".dem-button:hover{ border-color:$bh_bcolor !important; }\n";
			}
		}

		return $out;
	}

	/**
	 * Сжимает css YUICompressor
	 */
	public function cssmin( string $input_css ): string {

		require_once DEMOC_PATH . 'admin/CssMin/cssmin.php';

		$compressor = new \tubalmartin\CssMin\Minifier();

		// $compressor->set_memory_limit('256M');
		// $compressor->set_max_execution_time(120);

		return $compressor->run( $input_css );
	}

	## Импортирует @import в css
	private function parse_cssimport( $css_filepath ) {
		$filecode = file_get_contents( $css_filepath );

		$filecode = preg_replace_callback( '~@import [\'"](.*?)[\'"];~', static function( $m ) use ( $css_filepath ) {
			return file_get_contents( dirname( $css_filepath ) . '/' . $m[1] );
		}, $filecode );

		return $filecode;
	}

	## Ссылка на настройки со страницы плагинов
	public function _plugin_action_setting_page_link( $actions, $plugin_file ) {
		if( false === strpos( $plugin_file, basename( DEMOC_PATH ) ) ){
			return $actions;
		}

		$settings_link = sprintf( '<a href="%s">%s</a>', $this->admin_page_url(), __( 'Settings', 'democracy-poll' ) );
		array_unshift( $actions, $settings_link );

		return $actions;
	}

	/**
	 * Создает страницу архива. Сохраняет УРЛ созданой страницы в опции плагина.
	 * Перед созданием проверят нет ли уже такой страницы.
	 *
	 * @return false|void
	 */
	public function dem_create_archive_page() {
		global $wpdb;

		// Пробуем найти страницу с архивом
		$page = $wpdb->get_row(
			"SELECT * FROM $wpdb->posts WHERE post_content LIKE '[democracy_archives]' AND post_status = 'publish' LIMIT 1"
		);
		if( $page ){
			$page_id = $page->ID;
		}
		// Создаем новую страницу
		else{
			$page_id = wp_insert_post( [
				'post_title'   => __( 'Polls Archive', 'democracy-poll' ),
				'post_content' => '[democracy_archives]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_name'    => 'democracy-archives',
			] );

			if( ! $page_id ){
				return false;
			}
		}

		// обновляем опцию плагина
		demopt()->update_single_option( 'archive_page_id', $page_id );

		wp_redirect( remove_query_arg( 'dem_create_archive_page' ) );
	}

	/**
	 * Clears all log table.
	 */
	protected function clear_logs() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE $wpdb->democracy_log" );
		wp_redirect( remove_query_arg( 'dem_clear_logs' ) );
		exit;
	}

	protected function clear_closed_polls_logs() {
		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->democracy_log WHERE qid IN (SELECT id FROM $wpdb->democracy_q WHERE open = 0)" );
		wp_redirect( remove_query_arg( 'dem_del_closed_polls_logs' ) );
		exit;
	}

	protected function clear_new_mark() {
		global $wpdb;
		$wpdb->query( "UPDATE $wpdb->democracy_a SET added_by = REPLACE( added_by, '-new', '')" );
		wp_redirect( remove_query_arg( 'dem_del_new_mark' ) );
		exit;
	}

	/**
	 * Удаляет только указанный лог
	 *
	 * @param array|int $log_ids  Log IDs array or single log ID
	 */
	public function del_only_logs( $log_ids ) {
		global $wpdb;

		$log_ids = array_filter( (array) $log_ids );
		if( ! $log_ids ){
			return false;
		}

		$res = $wpdb->query( "DELETE FROM $wpdb->democracy_log WHERE logid IN (" . implode( ',', array_map( 'intval', $log_ids ) ) . ")" );

		$this->msg->add_ok( $res
			? sprintf( __( 'Lines deleted: %s', 'democracy-poll' ), $res )
			: __( 'Failed to delete', 'democracy-poll' )
		);

		do_action( 'dem_delete_only_logs', $log_ids, $res );

		return $res;
	}

	/**
	 * Удаляет указанный лог и связанные голоса
	 *
	 * @param array|int $log_ids  Log IDs array or single log ID
	 */
	public function del_logs_and_votes( $log_ids ) {
		$log_ids = array_filter( (array) $log_ids );
		if( ! $log_ids ){
			return false;
		}

		global $wpdb;

		// Соберем все ID вопросов, которые нужно минусануть
		$log_data = $wpdb->get_results(
			"SELECT qid, aids FROM $wpdb->democracy_log WHERE logid IN (" . implode( ',', array_map( 'intval', $log_ids ) ) . ")"
		);
		$aids = wp_list_pluck( $log_data, 'aids' );
		$qids = wp_list_pluck( $log_data, 'qid' );

		// update answers table 'votes' field
		if( 1 ){
			// collect count how much to minus from every answer
			$minus_data = [];
			foreach( $aids as $_aids ){
				foreach( explode( ',', $_aids ) as $aid ){
					$minus_data[ $aid ] = empty( $minus_data[ $aid ] ) ? 1 : ( $minus_data[ $aid ] + 1 );
				}
			}

			// minus sql for answer 'votes' field
			$minus_answ_sum = 0;
			foreach( $minus_data as $aid => $minus_num ){
				// IF( (votes<=%d), 0, (votes-%d) ) - for case when minus number bigger than votes. Votes can't be negative
				$sql = $wpdb->prepare( "UPDATE $wpdb->democracy_a SET votes = IF( (votes<=%d), 0, (votes-%d) ) WHERE aid = %d", $minus_num, $minus_num, $aid );
				if( $wpdb->query( $sql ) ){
					$minus_answ_sum += $minus_num;
				}
			}
		}

		// update question table 'users_voted' field
		if( 1 ){
			// collect count how much to minus from every question 'users_voted' field
			$minus_data = [];
			foreach( $qids as $qid ){
				$minus_data[ $qid ] = empty( $minus_data[ $qid ] ) ? 1 : ( $minus_data[ $qid ] + 1 );
			}

			// minus sql for question 'users_voted' field
			$minus_users_sum = 0;
			foreach( $minus_data as $qid => $minus_num ){
				if( $wpdb->query( self::users_voted_minus_sql( $minus_num, $qid ) ) ){
					$minus_users_sum += $minus_num;
				}
			}
		}

		// now, delete logs itself
		$res = $wpdb->query( "DELETE FROM $wpdb->democracy_log WHERE logid IN (" . implode( ',', array_map( 'intval', $log_ids ) ) . ")" );

		$this->msg->add_ok( $res
			? sprintf(
				__( 'Removed logs:%d. Taken away answers:%d. Taken away users %d.', 'democracy-poll' ),
				$res, $minus_answ_sum, $minus_users_sum
			)
			: __( 'Failed to delete', 'democracy-poll' )
		);

		do_action( 'dem_delete_logs_and_votes', $log_ids, $res, $minus_answ_sum, $minus_users_sum );
	}

	private static function users_voted_minus_sql( $minus_num, $qid ) {
		global $wpdb;

		return $wpdb->prepare( "UPDATE $wpdb->democracy_q SET users_voted = IF( (users_voted<=%d), 0, (users_voted-%d) ) WHERE id = %d", $minus_num, $minus_num, $qid );
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



