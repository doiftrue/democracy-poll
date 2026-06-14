<?php

namespace DemocracyPoll;

use DemocracyPoll\Helpers\IP;
use DemocracyPoll\Helpers\Kses;
use DemPoll;
use WP_Error;

/**
 * Handles voting logic for a poll.
 * Provides methods to vote, delete votes, manage logs etc.
 */
class Poll_Controller {

	private DemPoll $poll;

	public Poll_Cookies $poll_cookie;
	public Poll_Logs $poll_logs;

	public function __construct( DemPoll $poll ) {
		$this->poll = $poll;
		$this->poll_cookie = new Poll_Cookies( $poll );
		$this->poll_logs = new Poll_Logs( $poll );
	}

	/**
	 * @param string|array $aids  Answer IDs separated by commas. May contain a string,
	 *                            which will be added as a user answer.
	 *
	 * @return WP_Error|string $aids IDs, separated
	 */
	public function vote( $aids ) {
		global $wpdb;

		$poll = $this->poll;

		if( ! $poll->id ){
			return new WP_Error( 'vote_err', 'ERROR: no id' );
		}

		// set the cookie again, there was a bug...
		if( $poll->has_voted && $this->poll_cookie->is_not_voted() ){
			$this->poll_cookie->set();
		}

		// must run after "$poll->has_voted" check, because if $poll->has_voted then $poll->voting_blocked always true
		if( $poll->voting_blocked ){
			return new WP_Error( 'vote_err', 'ERROR: voting is blocked...' );
		}

		if( ! is_array( $aids ) ){
			$aids = trim( $aids );
			$aids = explode( '~', $aids );
		}

		$aids = array_map( 'trim', $aids ); // could have string (free answer)
		$aids = array_filter( $aids );

		// check the quantity
		if( $poll->multiple > 1 && count( $aids ) > $poll->multiple ){
			return new WP_Error( 'vote_err', __( 'ERROR: You select more number of answers than it is allowed...', 'democracy-poll' ) );
		}

		// Add user-free answer
		// Checks values of $aids array, trying to find string, if has - it's free answer
		if( $poll->democratic ){
			$new_free_answer = '';

			foreach( $aids as $k => $id ){
				if( ! is_numeric( $id ) ){
					$new_free_answer = $id;
					unset( $aids[ $k ] ); // remove from the common array, so that there is no this answer

					// clear array because multiple voting is blocked
					if( ! $poll->multiple ){
						$aids = [];
					}
					//break; !!!!NO
				}
			}

			// if there is a free answer, add it and vote
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

		if( count( $aids ) === 1 ){
			$aids = reset( $aids );
			$AND_clause = $wpdb->prepare( ' AND aid = %d LIMIT 1', $aids );
		}
		elseif( $poll->multiple ){
			$aids = array_map( 'intval', $aids );

			// no more than allowed...
			if( count( $aids ) > (int) $poll->multiple ){
				$aids = array_slice( $aids, 0, $poll->multiple );
			}

			$aids = implode( ',', $aids ); // must be separate!
			$AND_clause = ' AND aid IN (' . $aids . ')';
		}
		else {
			return new WP_Error( 'vote_err', 'ERROR: You can not vote for more than one answer.' );
		}

		// update in DB
		$wpdb->query( $wpdb->prepare(
			"UPDATE $wpdb->democracy_a SET votes = (votes+1) WHERE qid = %d $AND_clause", $poll->id
		) );
		$wpdb->query( $wpdb->prepare(
			"UPDATE $wpdb->democracy_q SET users_voted = (users_voted+1) WHERE id = %d", $poll->id
		) );

		$poll->users_voted++;
		$poll->dbdata->users_voted++; // just in case

		$poll->voting_blocked = true;
		$poll->has_voted = true;
		$poll->voted_for = $aids;

		$poll->re_set_answers();

		$this->poll_cookie->set();

		if( options()->keep_logs ){
			$this->poll_logs->insert_logs();
		}

		/**
		 * Allows to perform actions after the user has voted.
		 *
		 * @param string  $voted_for Comma-separated IDs of the answers the user voted for. Or custom answer as string.
		 * @param DemPoll $poll      The current poll object.
		 */
		do_action( 'dem_voted', $poll->voted_for, $poll );

		return $poll->voted_for;
	}

	/**
	 * Deletes the user's voting data.
	 * Resets the {@see self::$has_voted} and {@see self::$voted_for} properties.
	 * Should be called before outputting data to the screen.
	 */
	public function delete_vote(): void {
		$poll = $this->poll;

		if( ! $poll->id || ! $poll->revote || ! options()->keep_logs ){
			return;
		}

		$logs = $this->poll_logs->get_user_vote_logs();
		if( ! $logs ){
			return;
		}

		// Use only server-side voting data. The public cookie is user-controlled.
		$poll->voted_for = (string) reset( $logs )->aids;
		$this->minus_vote();
		$this->poll_logs->delete_vote_log( $logs );

		$this->poll_cookie->delete();

		$poll->has_voted = false;
		$poll->voted_for = '';
		$poll->voting_blocked = ! $poll->open;

		$poll->re_set_answers(); // if an added answer was deleted

		/**
		 * Allows to perform actions after the user's vote has been deleted.
		 *
		 * @param DemPoll $poll The current poll object.
		 */
		do_action( 'dem_vote_deleted', $poll );
	}

	/**
	 * Removes votes from the database and deletes the answer if it has 0 or 1 votes.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function minus_vote(): bool {
		global $wpdb;
		$poll = $this->poll;

		$aids = $this->get_answer_ids_from_str( $poll->voted_for );
		if( ! $aids ){
			return false;
		}

		$aids_IN = implode( ',', array_map( 'intval', $aids ) );

		// first, delete user-added answers if exist and have 0 or 1 votes
		$r1 = $wpdb->query(
			"DELETE FROM $wpdb->democracy_a WHERE qid = $poll->id AND added_by != '' AND votes IN (0,1) AND aid IN ($aids_IN) ORDER BY aid DESC LIMIT 1"
		);

		// subtract votes
		$r2 = $wpdb->query(
			"UPDATE $wpdb->democracy_a SET votes = IF( votes>0, votes-1, 0 ) WHERE qid = $poll->id AND aid IN ($aids_IN)"
		);

		// subtract number of voted users
		$r3 = $wpdb->query(
			"UPDATE $wpdb->democracy_q SET users_voted = IF( users_voted>0, users_voted-1, 0 ) WHERE id = $poll->id"
		);

		return $r1 || $r2;
	}

	private function insert_democratic_answer( string $answer ): int {
		global $wpdb;
		$poll = $this->poll;

		$new_answer = Kses::sanitize_answer_data( $answer, 'democratic_answer' );
		$new_answer = wp_unslash( $new_answer );

		// check if the answer already exists
		$aids = $wpdb->query( $wpdb->prepare(
			"SELECT aid FROM $wpdb->democracy_a WHERE answer = %s AND qid = %d",
			$new_answer, $poll->id
		) );

		if( $aids ){
			return 0;
		}

		$cuser_id = get_current_user_id();

		// Added from the front end as a democratic answer, regardless of the user.
		$added_by = $cuser_id ?: IP::get_user_ip();
		$added_by .= ( ! $cuser_id || (int) $poll->added_user !== (int) $cuser_id ) ? '-new' : '';

		// if order specified, set 'max+1'
		$aorder = $poll->answers[0]->aorder > 0
			? max( wp_list_pluck( $poll->answers, 'aorder' ) ) + 1
			: 0;

		$inserted = $wpdb->insert( $wpdb->democracy_a, [
			'qid'      => $poll->id,
			'answer'   => $new_answer,
			'votes'    => 0,
			'added_by' => $added_by,
			'aorder'   => $aorder,
		] );

		return $inserted ? $wpdb->insert_id : 0;
	}

	/**
	 * Gets the voting data for the current user.
	 */
	public function get_voted_for(): string {
		// The database takes precedence over cookies, because in one browser you can cancel the vote,
		// but in another browser cookies will still show that you have voted...
		// NOTE: update cookies if they do not match. Because in different browsers they can be different. Does not work,
		// because cookies need to be set before outputting data, and in general, this should not be done, because checking
		// by cookies becomes unnecessary overall...
		if( options()->keep_logs && ( $res = $this->poll_logs->get_user_vote_logs() ) ){
			$voted_for = reset( $res )->aids;
		}
		// check cookies
		elseif( ! $this->poll_cookie->is_not_voted() ){
			$voted_for = $this->poll_cookie->get();
		}

		return $voted_for ?? '';
	}

	/**
	 * Gets an array of answer IDs from a passed string, where IDs are separated by commas.
	 * Cleans for DB!
	 *
	 * @param string $aids_str  String with answer IDs
	 *
	 * @return int[]  Answer IDs
	 */
	protected function get_answer_ids_from_str( string $aids_str ): array {
		$aids = explode( ',', $aids_str );
		$aids = array_map( 'trim', $aids );
		$aids = array_map( 'intval', $aids );
		$aids = array_filter( $aids );

		return $aids;
	}

}
