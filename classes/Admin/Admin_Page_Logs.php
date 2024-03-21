<?php

namespace DemocracyPoll\Admin;

use function DemocracyPoll\plugin;
use function DemocracyPoll\options;

class Admin_Page_Logs implements Admin_Subpage_Interface {

	/** @var Admin_Page */
	private $admpage;

	/** @var List_Table_Logs */
	public $list_table;

	public function __construct( Admin_Page $admin_page ){
		$this->admpage = $admin_page;
	}

	public function load(){
		$this->list_table = new List_Table_Logs( $this );
	}

	public function request_handler(  ){
		if( ! plugin()->super_access || ! Admin_Page::check_nonce() ){
			return;
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

	/**
	 * @param List_Table_Logs $list_table
	 *
	 * @return void
	 */
	public function render() {

		// no access
		if( $this->list_table->poll_id && ! plugin()->cuser_can_edit_poll( $this->list_table->poll_id ) ){
			plugin()->msg->add_error( 'Sorry, you are not allowed to access this page.' );
			echo $this->admpage->subpages_menu();

			return;
		}

		if( ! options()->keep_logs ){
			plugin()->msg->add_warn( __( 'Logs records turned off in the settings - logs are not recorded.', 'democracy-poll' ) );
		}

		echo $this->admpage->subpages_menu();

		$this->list_table->table_title();

		if( plugin()->super_access ){
			global $wpdb;
			$count = $wpdb->get_var(
				"SELECT count(*) FROM $wpdb->democracy_log WHERE qid IN (SELECT id FROM $wpdb->democracy_q WHERE open = 0)"
			);

			$del_new_marks_button = options()->democracy_off
				? ''
				: sprintf( '<a class="button button-small" href="%s">%s</a>',
					esc_url( Admin_Page::add_nonce( $_SERVER['REQUEST_URI'] . '&dem_del_new_mark' ) ),
					sprintf( __( 'Delete all NEW marks', 'democracy-poll' ), $count )
				);
			?>
			<div style="text-align:right; margin-bottom:1em;">
				<?= $del_new_marks_button ?>

				<a class="button button-small"
				   href="<?= esc_url( Admin_Page::add_nonce( $_SERVER['REQUEST_URI'] ) ) ?>&dem_del_closed_polls_logs"
				   onclick="return confirm( '<?= __( 'Are you sure?', 'democracy-poll' ) ?>' )"
				>
					<?= sprintf( __( 'Delete logs of closed pols - %d', 'democracy-poll' ), $count ) ?>
				</a>

				<a class="button button-small"
				   href="<?= esc_url( Admin_Page::add_nonce( $_SERVER['REQUEST_URI'] ) ) ?>&dem_clear_logs"
				   onclick="return confirm( '<?= __( 'Are you sure?', 'democracy-poll' ) ?>' )"
				>
					<?= __( 'Delete all logs', 'democracy-poll' ) ?>
				</a>
			</div>
			<?php
		}

		?>
		<form action="" method="POST">
			<?php wp_nonce_field( 'dem_adminform', '_demnonce' ) ?>
			<?php $this->list_table->display() ?>
		</form>
		<?php
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

		plugin()->msg->add_ok( $res
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
			return;
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

		plugin()->msg->add_ok( $res
			? sprintf(
				__( 'Removed logs: %d. Removed answers:%d. Removed users %d.', 'democracy-poll' ),
				$res, $minus_answ_sum, $minus_users_sum
			)
			: __( 'Failed to delete', 'democracy-poll' )
		);

		do_action( 'dem_delete_logs_and_votes', $log_ids, $res, $minus_answ_sum, $minus_users_sum );
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

	public static function users_voted_minus_sql( $minus_num, $qid ) {
		global $wpdb;

		return $wpdb->prepare( "UPDATE $wpdb->democracy_q SET users_voted = IF( (users_voted<=%d), 0, (users_voted-%d) ) WHERE id = %d", $minus_num, $minus_num, $qid );
	}

	/**
	 * Проверяет является ли переданный ответ новым ответом - NEW
	 *
	 * @param object $answer  Объект ответа
	 */
	public static function is_new_answer( $answer ): bool {
		return preg_match( '~-new$~', $answer->added_by );
	}

}
