<?php

namespace DemocracyPoll\Admin;

use DemocracyPoll\Helpers\Helpers;
use DemocracyPoll\Helpers\Kses;
use function DemocracyPoll\plugin;
use function DemocracyPoll\options;

class Admin_Page_Edit_Poll implements Admin_Subpage_Interface {

	/** @var int */
	private $poll_id = 0;

	/** @var \DemPoll|null */
	private $poll = null;

	/** @var Admin_Page */
	private $admpage;

	public function set_poll_id( int $poll_id ){
		$this->poll_id = $poll_id;
	}

	public function __construct( Admin_Page $admin_page ) {
		$this->admpage = $admin_page;
	}

	public function load() {
		wp_enqueue_script( 'jquery-ui-sortable' );
	}

	public function request_handler() {

		if( ( $_GET['msg'] ?? '' ) === 'created' ){
			plugin()->msg->add_ok( __( 'New Poll Added', 'democracy-poll' ) );
		}

		if( ! plugin()->admin_access || ! Admin_Page::check_nonce() ){
			return;
		}

		// Add/update a poll
		$poll_id = $_POST['dmc_create_poll'] ?? $_POST['dmc_update_poll'] ?? 0;
		if( $poll_id ){
			plugin()->cuser_can_edit_poll( $poll_id )
				? $this->insert_poll_handler()
				: plugin()->msg->add_error( 'Low capability to add/edit poll' );
		}
	}

	public function render() {
		global $wpdb;

		// no access
		if( $this->poll_id && ! plugin()->cuser_can_edit_poll( $this->poll_id ) ){
			wp_die( 'Sorry, you are not allowed to access this page.' );
		}

		$poll_id = (int) $this->poll_id;

		$edit = (bool) $this->poll_id;
		$answers = false;

		$title = '';
		$shortcode = '';
		if( $this->poll_id ){
			$this->poll = \DemPoll::get_poll_object( $this->poll_id );
			$answers = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->democracy_a WHERE qid = %d", $this->poll_id ) );

			$log_link = options()->keep_logs
				? sprintf( '<small> : <a href="%s">%s</a></small>',
					add_query_arg( [ 'subpage' => 'logs', 'poll' => $this->poll->id ], plugin()->admin_page_url() ),
					__( 'Poll logs', 'democracy-poll' ) )
				: '';

			$title = Kses::kses_html( $this->poll->question ) . $log_link;
			$shortcode = self::shortcode_html( $this->poll_id ) . ' — ' . __( 'shortcode for use in post content', 'democracy-poll' );

			$hidden_inputs = '<input type="hidden" name="dmc_update_poll" value="' . (int) $this->poll_id . '">';
		}
		else{
			$hidden_inputs = "<input type='hidden' name='dmc_create_poll' value='1'>";
		}

		$poll = $this->poll;

		echo $this->admpage->subpages_menu();

		echo ( $title ? "<h2>$title</h2>$shortcode" : '' );

		?>
		<form action="<?= esc_url( remove_query_arg( 'msg' ) ) ?>" method="POST" class="dem-new-poll">

			<input type="hidden" name="dmc_qid" value="<?= (int) $poll_id ?>">
			<?= wp_nonce_field( 'dem_adminform', '_demnonce', false, false ) ?>

			<label>
				<?= __( 'Question:', 'democracy-poll' ) ?>
				<input type="text" id="the-question" name="dmc_question" value="<?= esc_attr( $poll->question ?? '' ) ?>" tabindex="1">
			</label>

			<?= apply_filters( 'demadmin_after_question', '', $poll ) ?>

			<?= __( 'Answers:', 'democracy-poll' ) ?>

			<ol class="new-poll-answers">
				<?php
				$is_answers_order = false;

				if( $answers ){
					$is_answers_order = ( $answers[0]->aorder > 0 );

					// сортировка, по порядку или по кол. голосов
					$_answers = Helpers::objects_array_sort( $answers, ( $is_answers_order ? [ 'aorder' => 'asc' ] : [
						'votes' => 'desc',
						'aid'   => 'asc',
					] ) );

					foreach( $_answers as $answer ){
						$after_answer = apply_filters( 'demadmin_after_answer', '', $answer );
						$answer = apply_filters( 'demadmin_edit_poll_answer', $answer );

						$by_user = $answer->added_by ? '<i>*' . ( Admin_Page_Logs::is_new_answer( $answer ) ? ' new' : '' ) . '</i>' : '';

						echo '
						<li class="answ">
							<input class="answ-text" type="text" name="dmc_old_answers[' . $answer->aid . '][answer]" value="' . esc_attr( $answer->answer ) . '" tabindex="2">
							<input type="number" min="0" name="dmc_old_answers[' . $answer->aid . '][votes]" value="' . ( $answer->votes ?: '' ) . '" tabindex="3" style="width:100px;min-width:100px;">
							<input type="hidden" name="dmc_old_answers[' . $answer->aid . '][aorder]" value="' . esc_attr( @ $answer->aorder ) . '">
							' . $by_user . '
							' . $after_answer . '
						</li>';
					}
				}
				else{
					for( $i = 0; $i < 2; $i++ ){
						?>
						<li class="answ new"><input type="text" name="dmc_new_answers[]" value=""></li>
						<?php
					}
				}

				// users_voted filed
				if( $edit ){
					// сбросить порядок, если установлен
					?>
					<li class="not__answer reset__aorder" style="list-style:none; <?= ( $is_answers_order ? '' : 'display:none;' ) ?>">
						<span class="dashicons dashicons-menu"></span>
						<span style="cursor:pointer; border-bottom:1px dashed #999;">&#215; <?= __( 'reset order', 'democracy-poll' ) ?></span>
					</li>
					<?php

					echo '
					<li class="not__answer" style="list-style:none;">
						<div style="width:80%; min-width:400px; max-width:800px; display:inline-block; text-align:right;">
							' . ( ( $poll->multiple ?? 0 ) ? __( 'Sum of votes:', 'democracy-poll' ) . ' ' . array_sum( wp_list_pluck( $_answers, 'votes' ) ) . '.' : '' ) . '
							' . __( 'Users vote:', 'democracy-poll' ) . '
						</div>
						<input type="number" min="0" title="' . ( @ $poll->multiple ? __( 'leave blank to update from logs', 'democracy-poll' ) : __( 'Voices', 'democracy-poll' ) ) . '" style="min-width:100px; width:100px; cursor:help;" name="dmc_users_voted" value="' . ( @ $poll->users_voted ?: '' ) . '" ' . ( @ $poll->multiple ? '' : 'readonly' ) . ' />
					</li>
					';
				}

				if( ! options()->democracy_off ){
					?>
					<li class="not__answer" style="list-style:none;">
						<label>
							<span class="dashicons dashicons-megaphone"></span>
							<input type="hidden" name="dmc_democratic" value=""/>
							<input type="checkbox" name="dmc_democratic"
							       value="1" <?php checked( ( ! isset( $poll->democratic ) || $poll->democratic ), 1 ) ?> />
							<?= esc_html__( 'Allow users to add answers (democracy).', 'democracy-poll' ) ?>
						</label>
					</li>
					<?php
				}
				?>
			</ol>

			<hr>

			<ol class="poll-options">
				<li>
					<label>
						<span class="dashicons dashicons-controls-play"></span>
						<input type="hidden" name="dmc_active" value=""/>
						<input type="checkbox" name="dmc_active"
						       value='1' <?php $edit ? checked( @ $poll->active, 1 ) : 'checked="true"' ?> />
						<?= esc_html__( 'Activate this poll.', 'democracy-poll' ) ?>
					</label>
				</li>

				<li>
					<label>
						<span class="dashicons dashicons-image-filter"></span>
						<?php $ml = (int) @ $poll->multiple; ?>
						<input type="hidden" name='dmc_multiple' value=''>
						<input type="checkbox" name="dmc_multiple"
						       value="<?= $ml ?>" <?= $ml ? 'checked="checked"' : '' ?> >
						<input type="number" min="0" value="<?= $ml ?>"
						       style="width:50px; <?= $ml ? '' : 'display:none;' ?>">
						<?= esc_html__( 'Allow to choose multiple answers.', 'democracy-poll' ) ?>
					</label>
				</li>

				<li>
					<label>
						<span class="dashicons dashicons-no"></span>
						<input type="text" name="dmc_end" value="<?= @ $poll->end ? date( 'd-m-Y', $poll->end ) : '' ?>"
						       style="width:120px; min-width:120px;">
						<?= esc_html__( 'Date, when poll was/will be closed. Format: dd-mm-yyyy.', 'democracy-poll' ) ?>
					</label>
				</li>

				<?php if( ! options()->revote_off ){ ?>
					<li>
						<label>
							<span class="dashicons dashicons-update"></span>
							<input type="hidden" name='dmc_revote' value=''>
							<input type="checkbox" name="dmc_revote"
							       value="1" <?php checked( ( ! isset( $poll->revote ) || $poll->revote ), 1 ) ?> >
							<?= esc_html__( 'Allow to change mind (revote).', 'democracy-poll' ) ?>
						</label>
					</li>
				<?php } ?>

				<?php if( ! options()->only_for_users ){ ?>
					<li>
						<label>
							<span class="dashicons dashicons-admin-users"></span>
							<input type="hidden" name="dmc_forusers" value="">
							<input type="checkbox" name="dmc_forusers" value="1" <?php checked( $poll->forusers ?? 0, 1 ) ?> >
							<?= esc_html__( 'Only registered users allowed to vote.', 'democracy-poll' ) ?>
						</label>
					</li>
				<?php } ?>

				<?php if( ! options()->dont_show_results ){ ?>
					<li>
						<label>
							<span class="dashicons dashicons-visibility"></span>
							<input type="hidden" name='dmc_show_results' value=''>
							<input type="checkbox" name="dmc_show_results"
							       value="1" <?php checked( ( ! isset( $poll->show_results ) || @ $poll->show_results ), 1 ) ?> >
							<?= esc_html__( 'Allow to watch the results of the poll.', 'democracy-poll' ) ?>
						</label>
					</li>
				<?php } ?>

				<li class="answers__order" style="<?= $is_answers_order ? 'display:none;' : '' ?>">
					<span class="dashicons dashicons-menu"></span>
					<select name="dmc_answers_order">
						<option value="" <?php selected( @ $poll->answers_order, '' ) ?>>
							-- <?= esc_html__( 'as in settings', 'democracy-poll' ) ?>:
							<?= Helpers::allowed_answers_orders()[ options()->order_answers ] ?> --
						</option>
						<?= Helpers::answers_order_select_options( $poll->answers_order ?? '' ) ?>
					</select>
					<?= esc_html__( 'How to sort the answers during the vote?', 'democracy-poll' ) ?><br>
				</li>

				<li><label>
						<textarea name="dmc_note" style="height:3.5em;"><?= esc_textarea( $poll->note ?? '' ) ?></textarea>
						<br><span
							class="description"><?= esc_html__( 'Note: This text will be added under poll.', 'democracy-poll' ); ?></span>

					</label>
				</li>

				<li>
					<label>
						<span class="dashicons dashicons-calendar-alt"></span>
						<input type="text" name="dmc_added"
						       value="<?= date( 'd-m-Y', ( ( $poll->added ?? '' ) ?: current_time( 'timestamp' ) ) ) ?>"
						       style="width:120px;min-width:120px;" disabled/>
						<span class="dashicons dashicons-edit"
						      onclick="jQuery(this).prev().removeAttr('disabled'); jQuery(this).remove();"
						      style="padding-top:.1em;"></span>
						<?= esc_html__( 'Create date.', 'democracy-poll' ) ?>
					</label>
				</li>
			</ol>

			<?php
			echo $hidden_inputs;
			$btn_value = ( $edit ? __( 'Save Changes', 'democracy-poll' ) : __( 'Add Poll', 'democracy-poll' ) );
			echo '<input type="submit" class="button-primary" value="' . $btn_value . '">';

			// buttons
			if( $edit ){
				echo ' ' . self::open_button( $poll );

				echo ' ' . self::activate_button( $poll );

				echo sprintf(
					' <a href="%s" class="button" onclick="return confirm(\'%s\');" title="%s"><span class="dashicons dashicons-trash"></span></a>',
					Admin_Page::add_nonce( add_query_arg( [ 'delete_poll' => $poll->id ], plugin()->admin_page_url() ) ),
					__( 'Are you sure?', 'democracy-poll' ),
					__( 'Delete', 'democracy-poll' )
				);

				// in posts
				if( $posts = Helpers::get_posts_with_poll( $poll ) ){
					$links = [];
					foreach( $posts as $post ){
						$links[] = sprintf( '<a href="%s">%s</a>', get_permalink( $post ), esc_html( $post->post_title ) );
					}

					echo '
					<div style="margin-top:4em;">
						<h4>' . __( 'Posts where the poll shortcode used:', 'democracy-poll' ) . '</h4>
						<ol>
							<li>' . implode( "</li>\n<li>", $links ) . '</li>
						</ol>
					</div>
					';
				}
			}
			?>
		</form>
		<?php
	}

	public function insert_poll_handler() {
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
	public function insert_poll( $data ) {
		global $wpdb;

		$orig_data = $data;

		$poll_id = (int) ( $data['qid'] ?? 0 );
		$update = (bool) $poll_id;

		// sanitize
		$data = (object) $this->sanitize_poll_data( $data );

		if( ! $data->question ){
			plugin()->msg->add_warn( 'error: question not set' );

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
					$added_by = Admin_Page_Logs::is_new_answer( $answ_row )
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
								$wpdb->query( Admin_Page_Logs::users_voted_minus_sql( $user_voted_minus, $poll_id ) );
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

			plugin()->msg->add_ok( __( 'Poll Updated', 'democracy-poll' ) );

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
				plugin()->msg->add_error( 'error: sql error when adding poll data' );

				return false;
			}

			foreach( $new_answers as $answer ){
				$answer = trim( $answer );

				if( ! empty( $answer ) ){
					$wpdb->insert( $wpdb->democracy_a, [ 'answer' => $answer, 'qid' => $poll_id ] );
				}
			}

			wp_redirect( add_query_arg( [ 'msg' => 'created' ], plugin()->edit_poll_url( $poll_id ) ) );
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
				$val = Kses::kses_html( $val );
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
					$val = Kses::sanitize_answer_data( $val );
				}
				else{
					foreach( $val as & $_val ){
						$_val = Kses::sanitize_answer_data( $_val );
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

	public static function shortcode_html( $poll_id ): string {

		if( ! $poll_id ){
			return '';
		}

		return '<span style="cursor:pointer; padding:2px 4px; background:#fff;"
		onclick="var sel = window.getSelection(), range = document.createRange(); range.selectNodeContents(this); sel.removeAllRanges(); sel.addRange(range); document.execCommand(\'copy\');">[democracy id="' . $poll_id . '"]</span>';
	}

	/**
	 * Выводит кнопки активации/деактивации опроса.
	 *
	 * @param object $poll  Объект опроса.
	 * @param string $url   УРЛ страницы ссылки, которую нужно обработать.
	 */
	public static function activate_button( $poll, $icon_reverse = false ): string {
		if( $poll->active ){
			$url = esc_url( Admin_Page::add_nonce( add_query_arg( [ 'dmc_deactivate_poll' => $poll->id, 'dmc_activate_poll' => null, ] ) ) );
			$title = __( 'Deactivate', 'democracy-poll' );
			$icon = $icon_reverse ? 'dashicons-controls-play' : 'dashicons-controls-pause';
		}
		else{
			$url = esc_url( Admin_Page::add_nonce( add_query_arg( [ 'dmc_deactivate_poll' => null, 'dmc_activate_poll' => $poll->id, ] ) ) );
			$title = __( 'Activate', 'democracy-poll' );
			$icon = $icon_reverse ? 'dashicons-controls-pause' : 'dashicons-controls-play';
		}

		return sprintf(
			'<a class="button" href="%s" title="%s"><span class="dashicons %s"></span></a>',
			esc_url( $url ), esc_html( $title ), $icon
		);
	}

	/**
	 * Выводит кнопки открытия/закрытия опроса.
	 *
	 * @param object $poll  Объект опроса.
	 * @param string $url   УРЛ страницы ссылки, которую нужно обработать.
	 */
	public static function open_button( $poll, $icon_reverse = false ): string {

		if( $poll->open ){
			$url = esc_url( Admin_Page::add_nonce( add_query_arg( [ 'dmc_close_poll' => $poll->id, 'dmc_open_poll' => null ] ) ) );
			$title = __( 'Close voting', 'democracy-poll' );
			$icon = $icon_reverse ? 'dashicons-yes' : 'dashicons-no';
		}
		else{
			$url = esc_url( Admin_Page::add_nonce( add_query_arg( [ 'dmc_close_poll' => null, 'dmc_open_poll' => $poll->id ] ) ) );
			$title = __( 'Open voting', 'democracy-poll' );
			$icon = $icon_reverse ? 'dashicons-no' : 'dashicons-yes';
		}

		return sprintf(
			'<a class="button" href="%s" title="%s"><span class="dashicons %s"></span></a>',
			esc_url( $url ), esc_html( $title ), $icon
		);
	}

	## deletes specified poll
	public static function delete_poll( $poll_id ) {
		global $wpdb;

		$poll_id = (int) $poll_id;
		if( ! $poll_id ){
			return;
		}

		$wpdb->delete( $wpdb->democracy_q, [ 'id' => $poll_id ] );
		$wpdb->delete( $wpdb->democracy_a, [ 'qid' => $poll_id ] );
		$wpdb->delete( $wpdb->democracy_log, [ 'qid' => $poll_id ] );

		plugin()->msg->add_ok( __( 'Poll Deleted', 'democracy-poll' ) . ": $poll_id" );
	}

	public static function open_poll( $poll_id ): bool {
		return self::_poll_opening( $poll_id, 'open' );
	}

	public static function close_poll( $poll_id ): bool {
		return self::_poll_opening( $poll_id, 'close' );
	}

	public static function activate_poll( $poll_id ): bool {
		return self::_poll_activation( $poll_id, 'activate' );
	}

	public static function deactivate_poll( $poll_id ): bool {
		return self::_poll_activation( $poll_id, 'deactivate' );
	}

	/**
	 * Закрывает/открывает голосование
	 *
	 * @param int  $poll_id  ID опроса
	 * @param bool $action   Что сделать, открыть или закрыть голосование?
	 */
	private static function _poll_opening( $poll_id, $action ): bool {
		global $wpdb;

		$poll = \DemPoll::get_poll_object( $poll_id );
		if( ! $poll ){
			return false;
		}

		$new_data = [ 'open' => ( $action === 'open' ) ? 1 : 0 ];

		// удаляем дату окончания при открытии голосования
		if( $action === 'open' ){
			$new_data['end'] = 0;
		}
		// ставим дату при закрытии опроса и деактивируем опрос
		else{
			$new_data['end'] = current_time( 'timestamp' ) - 10;
			self::deactivate_poll( $poll_id );
		}

		$done = $wpdb->update( $wpdb->democracy_q, $new_data, [ 'id' => $poll->id ] );

		if( $done ){
			plugin()->msg->add_ok( ( $action === 'open' )
				? __( 'Poll Opened', 'democracy-poll' )
				: __( 'Poll Closed', 'democracy-poll' )
			);
		}

		return (bool) $done;
	}

	/**
	 * Активирует/деактивирует опрос
	 *
	 * @param int  $poll_id     ID опроса
	 * @param bool $activation  Что сделать, активировать (true) или деактивировать?
	 */
	private static function _poll_activation( $poll_id, $action ): bool {
		global $wpdb;

		$poll = \DemPoll::get_poll_object( $poll_id );
		if( ! $poll ){
			return false;
		}

		$activate = ( $action === 'activate' );

		if( ! $poll->open && $activate ){
			plugin()->msg->add_error( __( 'You can not activate closed poll...', 'democracy-poll' ) );

			return false;
		}

		$done = $wpdb->update( $wpdb->democracy_q, [ 'active' => $activate ? 1 : 0 ], [ 'id' => $poll->id ] );

		if( $done ){
			plugin()->msg->add_ok( $activate
				? __( 'Poll Activated', 'democracy-poll' )
				: __( 'Poll Deactivated', 'democracy-poll' )
			);
		}

		return (bool) $done;
	}

}
