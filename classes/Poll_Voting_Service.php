<?php

namespace DemocracyPoll;

use DemPoll;
use WP_Error;

/**
 * Handles voting use-cases for a poll.
 */
class Poll_Voting_Service {

	private DemPoll $poll;

	public function __construct( DemPoll $poll ) {
		$this->poll = $poll;
	}

	/**
	 * @param string|array $a.ids Answer IDs separated by "~". May contain a string,
	 *                           which will be added as a user answer.
	 *
	 * @return WP_Error|string IDs separated by commas.
	 */
	public function vote( $aids ) {
		$poll = $this->poll;
		$ustate = $poll->user_state;
		if( ! $poll->id ){
			return new WP_Error( 'vote_err', 'ERROR: no id' );
		}

		// Set the cookie again, there was a bug...
		if( $ustate->has_voted && $ustate->poll_cookie->is_not_voted() ){
			$ustate->poll_cookie->set();
		}

		// Run after the has_voted check; a voted user always has voting_blocked set.
		if( $ustate->voting_blocked ){
			return new WP_Error( 'vote_err', 'ERROR: voting is blocked...' );
		}

		$aids = $this->parse_request_aids( $aids );

		// Check the quantity
		if( $poll->multiple > 1 && count( $aids ) > $poll->multiple ){
			return new WP_Error( 'vote_err', __( 'ERROR: You select more number of answers than it is allowed...', 'democracy-poll' ) );
		}

		// Add user free-answer
		if( $poll->democratic ){
			$aids = $this->add_democratic_answer_if_present( $aids );
		}

		$voted_for = $this->prepare_voted_for( $aids );
		if( is_wp_error( $voted_for ) ){
			return $voted_for;
		}

		if( ! $poll->storage->increment_votes( $voted_for ) ){
			return new WP_Error( 'vote_err', 'Internal ERROR: Vote was not saved. Contact developer.' );
		}

		$poll->users_voted++;
		is_object( $poll->dbdata ) && $poll->dbdata->users_voted++; // just in case

		$ustate->voted_for = $voted_for;
		$ustate->has_voted = true;
		$ustate->voting_blocked = true;

		$poll->set_answers();

		$ustate->poll_cookie->set();

		if( options()->keep_logs ){
			$ustate->poll_logs->insert_logs();
		}

		/**
		 * Allows to perform actions after the user has voted.
		 *
		 * @param string  $voted_for Comma-separated IDs of the answers the user voted for. Or custom answer as string.
		 * @param DemPoll $poll      The current poll object.
		 */
		do_action( 'dem_voted', $ustate->voted_for, $poll );

		return $ustate->voted_for;
	}

	/**
	 * Deletes the user's voting data.
	 */
	public function delete_vote(): void {
		$poll = $this->poll;
		$ustate = $poll->user_state;

		if( ! $poll->id || ! $poll->revote || ! options()->keep_logs ){
			return;
		}

		$logs = $ustate->poll_logs->get_user_vote_logs();
		if( ! $logs ){
			return;
		}

		// Use only server-side voting data. The public cookie is user-controlled.
		$ustate->voted_for = (string) reset( $logs )->aids;
		$ustate->has_voted = (bool) $ustate->voted_for;
		$poll->storage->decrement_votes();
		$ustate->poll_logs->delete_vote_log( $logs );

		$ustate->poll_cookie->delete();

		$ustate->has_voted = false;
		$ustate->voted_for = '';
		$ustate->voting_blocked = ! $poll->open;

		$poll->set_answers(); // if an added answer was deleted

		/**
		 * Allows to perform actions after the user's vote has been deleted.
		 *
		 * @param DemPoll $poll The current poll object.
		 */
		do_action( 'dem_vote_deleted', $poll );
	}

	private function parse_request_aids( $aids ): array {
		if( ! is_array( $aids ) ){
			$aids = trim( $aids );
			$aids = explode( '~', $aids );
		}

		$aids = array_map( 'trim', $aids );

		return array_filter( $aids );
	}

	private function add_democratic_answer_if_present( array $aids ): array {
		$poll = $this->poll;
		$new_free_answer = '';

		foreach( $aids as $k => $id ){
			if( is_numeric( $id ) ){
				continue;
			}

			$new_free_answer = $id;
			unset( $aids[ $k ] ); // remove from the common array, so that there is no this answer

			if( ! $poll->multiple ){
				$aids = [];
			}

			//break; // IMP!!!: NO break here
		}

		if( $new_free_answer && ( $aid = $poll->storage->insert_democratic_answer( $new_free_answer ) ) ){
			$aids[] = $aid;
		}

		return $aids;
	}

	/**
	 * @return string|WP_Error
	 */
	private function prepare_voted_for( array $aids ) {
		$poll = $this->poll;

		$aids = array_filter( $aids );
		if( ! $aids ){
			return new WP_Error( 'vote_err', 'ERROR: no answer ids after parse request. Contact developer.' );
		}

		if( count( $aids ) === 1 ){
			$aid = (int) reset( $aids );

			return $aid ? (string) $aid : new WP_Error( 'vote_err', 'ERROR: no answer id after parse request. Contact developer.' );
		}

		if( ! $poll->multiple ){
			return new WP_Error( 'vote_err', 'ERROR: Vote allowed for one answer only.' );
		}

		$aids = array_map( 'intval', $aids );
		if( count( $aids ) > $poll->multiple ){
			$aids = array_slice( $aids, 0, $poll->multiple );
		}

		$voted_for = implode( ',', array_filter( $aids ) );

		return $voted_for ?: new WP_Error( 'vote_err', 'ERROR: no answer ids after parse request. Contact developer.' );
	}

}
