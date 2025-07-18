<?php

use DemocracyPoll\Helpers\Helpers;
use DemocracyPoll\Helpers\IP;
use DemocracyPoll\Helpers\Kses;
use DemocracyPoll\Poll_Answer;
use DemocracyPoll\Poll_Renderer;
use function DemocracyPoll\options;


/**
 * TODO: split:
 * DemPoll                    –  plain data (id, props, getters)
 * DemPoll_Answer             –  (already exists)
 * DemPoll_Renderer           –  (already exists)
 * DemPoll_Repository         –  CRUD via $wpdb
 * DemPoll_Service            –  vote(), delete_vote(), business rules
 * DemPoll_Cookie             –  set_cookie(), unset_cookie(), expire helpers
 * DemPoll_Log_Repository     –  insert_logs(), delete_vote_log(), get_user_vote_logs()
 * DemPoll_Permission         –  set_voted_data(), can_vote(), etc.
 * DemPoll_Answer_Repository  –  set_answers(), insert_democratic_answer()
 * DemPoll_Validator          –  input limits, sanitize
 */


/**
 * Display and vote a separate poll.
 */
class DemPoll {

	public Poll_Renderer $renderer;

	public $has_voted        = false;
	public $votedFor         = '';    // за какие ответы голосовал
	public $blockVoting      = false; // блокировать голосование
	public $blockForVisitor  = false; // только для зарегистрированных
	public $not_show_results = false; // не показывать результаты

	public $in_archive = false; // в архивной странице
	public $for_cache = false;

	public $cookie_key;

	/** @var object|null */
	public $data = null;

	/** @var Poll_Answer[] */
	public array $answers = [];

	/// Fields from DB

	public $id = 0;

	/** @var string Poll title */
	public $question = '';

	/** @var int Added UNIX timestamp */
	public $added = 0;

	/** @var int End UNIX timestamp */
	public $end = 0;

	/** @var int User ID */
	public $added_user = 0;

	public $users_voted = 0;

	public $democratic = false;

	public $active = false;

	public $open = false;

	/** @var int How many answers may be selected. */
	public $multiple = 0;

	/** @var bool For logged users only */
	public $forusers = false;

	public $revote = false;

	public $show_results = false;

	public $answers_order = '';

	/** @var string Comma separated posts_ids. Eg: '16865,16892' */
	public $in_posts = '';

	/** @var string Additional poll notes */
	public $note = '';

	/**
	 * @param object|int $poll_id  Poll id to get. Or poll object from DB.
	 */
	public function __construct( $poll_id ) {
		global $wpdb;

		if( ! $poll_id ){
			return;
		}

		$poll_obj = null;
		is_object( $poll_id ) && $poll_obj = $poll_id;
		is_numeric( $poll_id ) && $poll_obj = self::get_poll_object( $poll_id );
		if( ! $poll_obj || ! isset( $poll_obj->id ) ){
			return;
		}

		$this->data = $poll_obj;

		$this->id            = (int) $poll_obj->id;
		$this->question      = $this->data->question;
		$this->added         = (int) $this->data->added;
		$this->added_user    = (int) $this->data->added_user;
		$this->end           = (int) $this->data->end;
		$this->users_voted   = (int) $this->data->users_voted;
		$this->democratic    = (bool) ( options()->democracy_off ? false : $this->data->democratic );
		$this->active        = (bool) $this->data->active;
		$this->open          = (bool) $this->data->open;
		$this->multiple      = (int) $this->data->multiple;
		$this->forusers      = (bool) $this->data->forusers;
		$this->revote        = (bool) ( options()->revote_off ? false : $this->data->revote );
		$this->show_results  = (bool) $this->data->show_results;
		$this->answers_order = $this->data->answers_order;
		$this->in_posts      = $this->data->in_posts;
		$this->note          = $this->data->note;

		$this->cookie_key = "demPoll_$this->id";

		$this->set_voted_data();
		$this->set_answers(); // установим свойство $this->answers

		// закрываем опрос т.к. срок закончился
		if( $this->end && $this->open && ( current_time( 'timestamp' ) > $this->end ) ){
			$wpdb->update( $wpdb->democracy_q, [ 'open' => 0 ], [ 'id' => $this->id ] );
		}

		// только для зарегистрированных
		if( ( options()->only_for_users || $this->forusers ) && ! is_user_logged_in() ){
			$this->blockForVisitor = true;
		}

		// блокировка возможности голосовать
		if( $this->blockForVisitor || ! $this->open || $this->has_voted ){
			$this->blockVoting = true;
		}

		if(
			( ! $poll_obj->show_results || options()->dont_show_results )
			&& $poll_obj->open
			&& ( ! is_admin() || defined( 'DOING_AJAX' ) )
		){
			$this->not_show_results = true;
		}

		$this->renderer = new Poll_Renderer( $this );
	}

	/**
	 * @param int|string $poll_id Poll id to get. Specify 'rand', 'last' when you need a random or last poll.
	 *
	 * @return object|null
	 */
	public static function get_poll_object( $poll_id ) {
		global $wpdb;

		if( 'rand' === $poll_id ){
			$poll_obj = $wpdb->get_row( "SELECT * FROM $wpdb->democracy_q WHERE active = 1 ORDER BY RAND() LIMIT 1" );
		}
		elseif( 'last' === $poll_id ){
			$poll_obj = $wpdb->get_row( "SELECT * FROM $wpdb->democracy_q WHERE open = 1 ORDER BY id DESC LIMIT 1" );
		}
		else {
			$poll_obj = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM $wpdb->democracy_q WHERE id = %d LIMIT 1", $poll_id
			) );
		}

		/**
		 * Allows to modify the poll object before it is returned.
		 *
		 * @param object|null $poll_obj Raw poll object from DB.
		 */
		return apply_filters( 'dem_get_poll', $poll_obj );
	}

	/**
	 * Adds a vote.
	 *
	 * @param string|array $aids  Answer IDs separated by commas. May contain a string,
	 *                            which will be added as a user answer.
	 *
	 * @return WP_Error|string $aids IDs, separated
	 */
	public function vote( $aids ) {

		if( ! $this->id ){
			return new WP_Error( 'vote_err', 'ERROR: no id' );
		}

		// set the cookie again, there was a bug...
		if( $this->has_voted && ( $_COOKIE[ $this->cookie_key ] === 'notVote' ) ){
			$this->set_cookie();
		}

		// must run after "$this->has_voted" check, because if $this->has_voted then $this->blockVoting always true
		if( $this->blockVoting ){
			return new WP_Error( 'vote_err', 'ERROR: voting is blocked...' );
		}

		global $wpdb;

		if( ! is_array( $aids ) ){
			$aids = trim( $aids );
			$aids = explode( '~', $aids );
		}

		$aids = array_map( 'trim', $aids ); // could have string (free answer)
		$aids = array_filter( $aids );

		// check the quantity
		if( $this->multiple > 1 && count( $aids ) > $this->multiple ){
			return new WP_Error( 'vote_err', __( 'ERROR: You select more number of answers than it is allowed...', 'democracy-poll' ) );
		}

		// Add user free answer
		// Checks values of $aids array, trying to find string, if has - it's free answer
		if( $this->democratic ){
			$new_free_answer = false;

			foreach( $aids as $k => $id ){
				if( ! is_numeric( $id ) ){
					$new_free_answer = $id;
					unset( $aids[ $k ] ); // remove from the common array, so that there is no this answer

					// clear array because multiple voting is blocked
					if( ! $this->multiple ){
						$aids = [];
					}
					//break; !!!!NO
				}
			}

			// if there is free answer, add it and vote
			if( $new_free_answer && ( $aid = $this->insert_democratic_answer( $new_free_answer ) ) ){
				$aids[] = $aid;
			}
		}

		// collect $ids into string for cookie. Here are only ints
		$aids = array_filter( $aids );

		if( ! $aids ){
			return new WP_Error( 'vote_err', 'ERROR: internal - no ids. Contact developer...' );
		}

		// AND clause
		$AND = '';

		// one answer
		if( count( $aids ) === 1 ){
			$aids = reset( $aids );
			$AND = $wpdb->prepare( ' AND aid = %d LIMIT 1', $aids );
		}
		// many answers (multiple)
		elseif( $this->multiple ){
			$aids = array_map( 'intval', $aids );

			// no more than allowed...
			if( count( $aids ) > (int) $this->multiple ){
				$aids = array_slice( $aids, 0, $this->multiple );
			}

			$aids = implode( ',', $aids ); // must be separate!
			$AND = ' AND aid IN (' . $aids . ')';
		}

		if( ! $AND ){
			return new WP_Error( 'vote_err', 'ERROR: internal - no $AND. Contact developer...' );
		}

		// update in DB
		$wpdb->query( $wpdb->prepare(
			"UPDATE $wpdb->democracy_a SET votes = (votes+1) WHERE qid = %d $AND", $this->id
		) );
		$wpdb->query( $wpdb->prepare(
			"UPDATE $wpdb->democracy_q SET users_voted = (users_voted+1) WHERE id = %d", $this->id
		) );

		$this->users_voted++;
		$this->data->users_voted++; // just in case

		$this->blockVoting = true;
		$this->has_voted   = true;
		$this->votedFor    = $aids;

		$this->set_answers(); // reinitialize answers

		$this->set_cookie(); // set the cookie

		if( options()->keep_logs ){
			$this->insert_logs();
		}

		/**
		 * Allows to perform actions after the user has voted.
		 *
		 * @param int|string $voted_for The IDs of the answers the user voted for. Or custom answer as string.
		 * @param DemPoll    $poll      The current poll object.
		 */
		do_action_ref_array( 'dem_voted', [ $this->votedFor, $this ] );

		return $this->votedFor;
	}

	/**
	 * Deletes the user's voting data.
	 * Resets the $this->has_voted and $this->votedFor properties.
	 * Should be called before outputting data to the screen.
	 */
	public function delete_vote(): void {
		if( ! $this->id ){
			return;
		}

		if( ! $this->revote ){
			return;
		}

		// Before deleting, check if the logging option is enabled and if there are voting records in the database,
		// because cookies can be deleted and then the voting data will go negative
		if( options()->keep_logs ){
			if( $this->get_user_vote_logs() ){
				$this->minus_vote();
				$this->delete_vote_log();
			}
		}
		// If the logging option is not enabled, votes are subtracted based on cookies.
		// Here votes can be rolled back, because it is not possible to check different browsers.
		else {
			$this->minus_vote();
		}

		$this->unset_cookie();

		$this->has_voted = false;
		$this->votedFor = false;
		$this->blockVoting = ! $this->open;

		$this->set_answers(); // reinitialize answers if an added answer was deleted

		/**
		 * Allows to perform actions after the user's vote has been deleted.
		 *
		 * @param DemPoll $poll The current poll object.
		 */
		do_action_ref_array( 'dem_vote_deleted', [ $this ] );
	}

	private function insert_democratic_answer( $answer ): int {
		global $wpdb;

		$new_answer = Kses::sanitize_answer_data( $answer, 'democratic_answer' );
		$new_answer = wp_unslash( $new_answer );

		// check if the answer already exists
		$aids = $wpdb->query( $wpdb->prepare(
			"SELECT aid FROM $wpdb->democracy_a WHERE answer = %s AND qid = %d",
			$new_answer, $this->id
		) );

		if( $aids ){
			return 0;
		}

		$cuser_id = get_current_user_id();

		// добавлен из фронта - демократический вариант ответа не важно какой юзер!
		$added_by = $cuser_id ?: IP::get_user_ip();
		$added_by .= ( ! $cuser_id || (int) $this->added_user !== (int) $cuser_id ) ? '-new' : '';

		// если есть порядок, ставим 'max+1'
		$aorder = reset( $this->answers )->aorder > 0
			? max( wp_list_pluck( $this->answers, 'aorder' ) ) + 1
			: 0;

		$inserted = $wpdb->insert( $wpdb->democracy_a, [
			'qid'      => $this->id,
			'answer'   => $new_answer,
			'votes'    => 0,
			'added_by' => $added_by,
			'aorder'   => $aorder,
		] );

		return $inserted ? $wpdb->insert_id : 0;
	}

	/**
	 * Sets the props {@see self::$has_voted} and {@see self::$votedFor}.
	 */
	protected function set_voted_data(): void {
		if( ! $this->id ){
			return;
		}

		// The database takes precedence over cookies, because in one browser you can cancel the vote,
		// but in another browser cookies will still show that you have voted...
		// NOTE: update cookies if they do not match. Because in different browsers they can be different. Does not work,
		// because cookies need to be set before outputting data, and in general, this should not be done, because checking
		// by cookies becomes unnecessary overall...
		if( options()->keep_logs && ( $res = $this->get_user_vote_logs() ) ){
			$this->has_voted = true;
			$this->votedFor = reset( $res )->aids;
		}
		// check cookies
		elseif( isset( $_COOKIE[ $this->cookie_key ] ) && ( $_COOKIE[ $this->cookie_key ] != 'notVote' ) ){
			$this->has_voted = true;
			$this->votedFor = preg_replace( '/[^0-9, ]/', '', $_COOKIE[ $this->cookie_key ] ); // чистим
		}
	}

	/**
	 * Removes votes from the database and deletes the answer if it has 0 or 1 votes.
	 *
	 * @return bool True on success, false on failure.
	 */
	protected function minus_vote(): bool {
		global $wpdb;

		$aids_IN = implode( ',', $this->get_answ_aids_from_str( $this->votedFor ) ); // чистит для БД!

		if( ! $aids_IN ){
			return false;
		}

		// сначала удалим добавленные пользователем ответы, если они есть и у них 0 или 1 голос
		$r1 = $wpdb->query(
			"DELETE FROM $wpdb->democracy_a WHERE added_by != '' AND votes IN (0,1) AND aid IN ($aids_IN) ORDER BY aid DESC LIMIT 1"
		);

		// отнимаем голоса
		$r2 = $wpdb->query(
			"UPDATE $wpdb->democracy_a SET votes = IF( votes>0, votes-1, 0 ) WHERE aid IN ($aids_IN)"
		);

		// отнимаем кол голосовавших
		$r3 = $wpdb->query(
			"UPDATE $wpdb->democracy_q SET users_voted = IF( users_voted>0, users_voted-1, 0 ) WHERE id = " . (int) $this->id
		);

		return $r1 || $r2;
	}

	/**
	 * Gets an array of answer IDs from a passed string, where IDs are separated by commas.
	 * Cleans for DB!
	 *
	 * @param string $aids_str  String with answer IDs
	 *
	 * @return int[]  Answer IDs
	 */
	protected function get_answ_aids_from_str( string $aids_str ): array {
		$arr = explode( ',', $aids_str );
		$arr = array_map( 'trim', $arr );
		$arr = array_map( 'intval', $arr );
		$arr = array_filter( $arr );

		return $arr;
	}

	/**
	 * Time until which the logs will live.
	 *
	 * @return int Timestamp in seconds.
	 */
	public function get_cookie_expire_time(): int {
		return current_time( 'timestamp', $utc = 1 ) + (int) ( (float) options()->cookie_days * DAY_IN_SECONDS );
	}

	/**
	 * Sets the cookie for the current poll.
	 *
	 * @param string $value  Cookie value, defaults to current votes.
	 * @param int    $expire Cookie expiration time.
	 */
	public function set_cookie( $value = '', $expire = false ): void {
		$expire = $expire ?: $this->get_cookie_expire_time();
		$value = $value ?: $this->votedFor;

		setcookie( $this->cookie_key, $value, $expire, COOKIEPATH );

		$_COOKIE[ $this->cookie_key ] = $value;
	}

	public function unset_cookie(): void {
		setcookie( $this->cookie_key, null, strtotime( '-1 day' ), COOKIEPATH );
		$_COOKIE[ $this->cookie_key ] = '';
	}

	/**
	 * Sets the answers in $this->answers prop and sorts them in the required order.
	 */
	protected function set_answers(): void {
		global $wpdb;

		$answers = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $wpdb->democracy_a WHERE qid = %d", $this->id
		) );

		$answers = array_filter( (array) $answers );

		$is_custom_order = (bool) reset( $answers )->aorder;
		if( $is_custom_order ){
			$answers = Helpers::objects_array_sort( $answers, [ 'aorder' => 'asc' ] );
		}
		else{
			$order = $this->answers_order ?: options()->order_answers;

			if( $order === 'by_winner' || $order == 1 ){
				$answers = Helpers::objects_array_sort( $answers, [ 'votes' => 'desc' ] );
			}
			elseif( $order === 'alphabet' ){
				$answers = Helpers::objects_array_sort( $answers, [ 'answer' => 'asc' ] );
			}
			elseif( $order === 'mix' ){
				shuffle( $answers );
			}
			elseif( $order === 'by_id' ){}
		}

		$answers = array_map( static fn( $data ) => new Poll_Answer( $data ), $answers );

		/**
		 * Allows to modify the answers before they are set in the poll object.
		 *
		 * @param Poll_Answer[] $answers The answers to be set for the poll.
		 * @param DemPoll       $poll    The poll object itself.
		 */
		$this->answers = apply_filters( 'dem_set_answers', $answers, $this );
	}

	/**
	 * Gets the log rows by user ID or IP address.
	 *
	 * @return array democracy_log table rows.
	 */
	protected function get_user_vote_logs(): array {
		global $wpdb;

		$WHERE = [
			$wpdb->prepare( 'qid = %d', $this->id ),
			$wpdb->prepare( 'expire > %d', time() )
		];

		$user_id = get_current_user_id();
		// нужно проверять юзера и IP отдельно!
		// Иначе, если юзер не авторизован его id=0 и он будет совпадать с другими пользователями
		if( $user_id ){
			// только для юзеров, IP не учитывается.
			// Если голосовали как не авторизованный, а потом залогинились, то можно голосовать еще раз.
			$WHERE[] = $wpdb->prepare( 'userid = %d', $user_id );
		}
		else {
			$WHERE[] = $wpdb->prepare( 'userid = 0 AND ip = %s', IP::get_user_ip() );
		}

		$WHERE = implode( ' AND ', $WHERE );

		$sql = "SELECT * FROM $wpdb->democracy_log WHERE $WHERE ORDER BY logid DESC";

		return $wpdb->get_results( $sql );
	}

	/**
	 * Deletes voting records from the logs.
	 */
	protected function delete_vote_log(): bool {
		global $wpdb;

		$logs = $this->get_user_vote_logs();
		if( ! $logs ){
			return true;
		}

		$delete_log_ids = wp_list_pluck( $logs, 'logid' );
		$logid_IN = implode( ',', array_map( 'intval', $delete_log_ids ) );

		$sql = "DELETE FROM $wpdb->democracy_log WHERE logid IN ( $logid_IN )";

		return (bool) $wpdb->query( $sql );
	}

	protected function insert_logs() {
		if( ! $this->id ){
			return false;
		}

		global $wpdb;

		$ip = IP::get_user_ip();

		return $wpdb->insert( $wpdb->democracy_log, [
			'qid'     => $this->id,
			'aids'    => $this->votedFor,
			'userid'  => (int) get_current_user_id(),
			'date'    => current_time( 'mysql' ),
			'expire'  => $this->get_cookie_expire_time(),
			'ip'      => $ip,
			'ip_info' => IP::prepared_ip_info( $ip ),
		] );
	}

}


