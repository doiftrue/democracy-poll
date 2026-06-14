<?php

namespace DemocracyPoll\Admin;

use DemocracyPoll\Helpers\IP;
use DemocracyPoll\Poll_Utils;
use function DemocracyPoll\plugin;
use function DemocracyPoll\options;

class Admin_Page_Logs implements Admin_Subpage_Interface {

	private const IP_INFO_AJAX_ACTION = 'democracy_ip_info';

	private Admin_Page $admpage;

	public List_Table_Logs $list_table;

	private static ?string $flag_css = null;

	public function __construct( Admin_Page $admin_page ){
		$this->admpage = $admin_page;
	}

	public function request_handler(): void {
		if( ! plugin()->super_access || ! Admin_Page::check_nonce() ){
			return;
		}

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

	public static function init_ajax(): void {
		add_action( 'wp_ajax_' . self::IP_INFO_AJAX_ACTION, [ self::class, 'ip_info_ajax_handler' ] );
	}

	public function load(): void {
		$this->list_table = new List_Table_Logs( $this );

		wp_add_inline_script( Admin_Page::ASSETS_ID, 'window.democracyPollLogs = ' . wp_json_encode( [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'action'  => self::IP_INFO_AJAX_ACTION,
			'nonce'   => wp_create_nonce( self::IP_INFO_AJAX_ACTION ),
		] ) );
	}

	public static function ip_info_ajax_handler(): void {
		if( ! plugin()->admin_access || ! check_ajax_referer( self::IP_INFO_AJAX_ACTION, 'nonce', false ) ){
			wp_send_json_error( null, 403 );
		}

		$log_id = (int) ( $_POST['log_id'] ?? 0 );
		$force  = ! empty( $_POST['force'] );
		if( ! $log_id ){
			wp_send_json_error( null, 400 );
		}

		global $wpdb;
		$log = $wpdb->get_row( $wpdb->prepare(
			"SELECT logid, ip, ip_info FROM $wpdb->democracy_log WHERE logid = %d",
			$log_id
		) );

		if( ! $log ){
			wp_send_json_error( null, 404 );
		}

		if( $force || self::ip_info_needs_update( $log ) ){
			$log->ip_info = IP::prepared_ip_info( $log->ip );
			$wpdb->update( $wpdb->democracy_log, [ 'ip_info' => $log->ip_info ], [ 'logid' => $log->logid ] );
		}

		wp_send_json_success( [
			'html' => self::ip_info_html( (string) $log->ip_info ),
		] );
	}

	public static function ip_info_needs_update( $log ): bool {
		if( empty( $log->ip ) ){
			return false;
		}

		$ip_info = $log->ip_info ?? '';

		return ! $ip_info || ( is_numeric( $ip_info ) && ( time() - DAY_IN_SECONDS ) > (int) $ip_info );
	}

	public static function ip_info_html( string $ip_info ): string {
		$country_img  = '';
		$country_name = '';
		$city         = '';

		if( $ip_info && ! is_numeric( $ip_info ) ){
			[ $country_name, $country_code, $city ] = explode( ',', $ip_info ) + [ '', '', '' ];

			if( null === self::$flag_css ){
				self::$flag_css = (string) file_get_contents( plugin()->dir . '/admin/country_flags/flags.css' );
			}

			preg_match( '~flag-' . strtolower( $country_code ) . ' \{([^}]+)\}~', self::$flag_css, $matches );
			$bg_pos = $matches[1] ?? '';

			if( $bg_pos ){
				$location = $country_name . ( $city ? ", $city" : '' );
				$country_img = '<span title="' . esc_attr( $location ) . '" style="cursor:help; display:inline-block; width:16px; height:11px; background:url(' . plugin()->url . '/admin/country_flags/flags.png) no-repeat; ' . $bg_pos . '"></span> ';
			}
		}

		$location = $country_name . ( $city ? ", $city" : '' );
		$info = $country_img
			? $country_img . ' <span style="opacity:0.8">' . esc_html( $location ) . '</span>'
			: '';

		return $info . '<span style="cursor:pointer; margin-left:1em; opacity:0.4" class="ip_info_up_button_js">up</span>';
	}

	public function render(): void {
		// no access
		if( $this->list_table->poll_id && ! Poll_Utils::cuser_can_edit_poll( $this->list_table->poll_id ) ){
			plugin()->msg->add_error( 'Sorry, you are not allowed to access this page.' );
			echo $this->admpage->subpages_menu();

			return;
		}

		if( ! options()->keep_logs ){
			plugin()->msg->add_warn( __( 'Logs records turned off in the settings - logs are not recorded.', 'democracy-poll' ) );
		}

		echo $this->admpage->subpages_menu();

		$this->list_table->table_title(); // title of single poll
		$this->render_logs_buttons();
		?>
		<form class="democr_options dempage-logs" action="" method="POST">
			<?php wp_nonce_field( 'dem_adminform', '_demnonce' ) ?>
			<?php $this->list_table->display() ?>
		</form>
		<?php
	}

	private function render_logs_buttons(): void {
		global $wpdb;

		if( ! plugin()->super_access ){
			return;
		}

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
		<div class="logs-button-wrapper" style="text-align:right; margin-bottom:1em;">
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

	/**
	 * Delete only the specified log.
	 *
	 * @param array|int $log_ids  Log IDs array or single log ID
	 */
	public function del_only_logs( $log_ids ) {
		global $wpdb;

		$log_ids = array_filter( (array) $log_ids );
		if( ! $log_ids ){
			return false;
		}

		$logid_IN = implode( ',', array_map( 'intval', $log_ids ) );
		$result = $wpdb->query( "DELETE FROM $wpdb->democracy_log WHERE logid IN ($logid_IN)" );

		plugin()->msg->add_ok( $result
			? sprintf( __( 'Lines deleted: %s', 'democracy-poll' ), $result )
			: __( 'Failed to delete', 'democracy-poll' )
		);

		/**
		 * Allows to do something after deleting logs.
		 *
		 * @param array|int $log_ids  Log IDs array or single log ID
		 * @param int       $result   Result of the delete query, number of deleted rows
		 */
		do_action( 'dem_delete_only_logs', $log_ids, $result );

		return $result;
	}

	/**
	 * Delete the specified log and its related votes.
	 *
	 * @param array|int $log_ids  Log IDs array or single log ID
	 */
	public function del_logs_and_votes( $log_ids ): void {
		$log_ids = array_filter( (array) $log_ids );
		if( ! $log_ids ){
			return;
		}

		global $wpdb;

		// Collect all question IDs whose vote counts must be decremented.
		$log_data = $wpdb->get_results(
			"SELECT qid, aids FROM $wpdb->democracy_log WHERE logid IN (" . implode( ',', array_map( 'intval', $log_ids ) ) . ")"
		);
		$aids = wp_list_pluck( $log_data, 'aids' );
		$qids = wp_list_pluck( $log_data, 'qid' );

		if( 'update answers table `votes` field' ){ // @phpstan-ignore-line
			// collect counts how much to minus from every answer
			$minus_data = [];
			foreach( $aids as $_aids ){
				foreach( explode( ',', $_aids ) as $aid ){
					$minus_data[ $aid ] = empty( $minus_data[ $aid ] ) ? 1 : ( $minus_data[ $aid ] + 1 );
				}
			}

			// minus SQL for answer 'votes' field
			$minus_answ_sum = 0;
			foreach( $minus_data as $aid => $minus_num ){
				// IF( (votes<=%d), 0, (votes-%d) ) - for case when minus number bigger than votes. Votes can't be negative
				$sql = $wpdb->prepare( "UPDATE $wpdb->democracy_a SET votes = IF( (votes<=%d), 0, (votes-%d) ) WHERE aid = %d", $minus_num, $minus_num, $aid );
				if( $wpdb->query( $sql ) ){
					$minus_answ_sum += $minus_num;
				}
			}
		}

		if( 'update question table `users_voted` field' ){ // @phpstan-ignore-line
			// collect counts how much to minus from every question 'users_voted' field
			$minus_data = [];
			foreach( $qids as $qid ){
				$minus_data[ $qid ] = empty( $minus_data[ $qid ] ) ? 1 : ( $minus_data[ $qid ] + 1 );
			}

			// minus SQL for question 'users_voted' field
			$minus_users_sum = 0;
			foreach( $minus_data as $qid => $minus_num ){
				if( $wpdb->query( self::users_voted_minus_sql( $minus_num, $qid ) ) ){
					$minus_users_sum += $minus_num;
				}
			}
		}

		// now, delete logs itself
		$result = $wpdb->query( "DELETE FROM $wpdb->democracy_log WHERE logid IN (" . implode( ',', array_map( 'intval', $log_ids ) ) . ")" );

		plugin()->msg->add_ok( $result
			? sprintf(
				__( 'Removed logs: %d. Removed answers:%d. Removed users %d.', 'democracy-poll' ),
				$result, $minus_answ_sum, $minus_users_sum
			)
			: __( 'Failed to delete', 'democracy-poll' )
		);

		/**
		 * Allows to do something after deleting logs and votes.
		 *
		 * @param array|int $log_ids  Log IDs array or single log ID.
		 * @param int       $result   Result of the delete query, number of deleted rows.
		 * @param int       $minus_answ_sum   Number of answers votes minus.
		 * @param int       $minus_users_sum  Number of users votes minus.
		 */
		do_action( 'dem_delete_logs_and_votes', $log_ids, $result, $minus_answ_sum, $minus_users_sum );
	}

	/**
	 * Clears all log table.
	 */
	protected function clear_logs(): void {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE $wpdb->democracy_log" );
		wp_redirect( remove_query_arg( 'dem_clear_logs' ) );
		exit;
	}

	protected function clear_closed_polls_logs(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->democracy_log WHERE qid IN (SELECT id FROM $wpdb->democracy_q WHERE open = 0)" );
		wp_redirect( remove_query_arg( 'dem_del_closed_polls_logs' ) );
		exit;
	}

	protected function clear_new_mark(): void {
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
	 * Check whether the given answer is marked as NEW.
	 *
	 * @param object $answer Answer object.
	 */
	public static function is_new_answer( $answer ): bool {
		return $answer && preg_match( '~-new$~', $answer->added_by );
	}

}
