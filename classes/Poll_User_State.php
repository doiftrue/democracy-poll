<?php

namespace DemocracyPoll;

use DemPoll;

/**
 * Represents the current visitor state for a specific poll.
 */
class Poll_User_State {

	private DemPoll $poll;

	public Poll_Cookies $poll_cookie; /* readonly */

	public Poll_Logs $poll_logs; /* readonly */

	/**
	 * Flag that means the poll is closed because the user
	 * is not logged and the voting is allowed only for logged users.
	 *
	 * We need this separate property to display a note.
	 */
	private ?bool $blocked_by_not_logged = null;

	private ?bool $voting_blocked = null;

	private ?bool $has_voted = null;

	private ?string $voted_for = null;

	public function __construct( DemPoll $poll ) {
		$this->poll = $poll;
		$this->poll_cookie = new Poll_Cookies( $poll );
		$this->poll_logs = new Poll_Logs( $poll );
	}

	public function blocked_by_not_logged(): bool {
		if( null === $this->blocked_by_not_logged ){
			$this->blocked_by_not_logged = ( options()->only_for_users || $this->poll->forusers ) && ! is_user_logged_in();
		}

		return $this->blocked_by_not_logged;
	}

	public function voting_blocked(): bool {
		if( null === $this->voting_blocked ){
			$blocked = ( $this->blocked_by_not_logged() || ! $this->poll->open );
			if( ! $blocked ){
				$blocked = $this->has_voted();
			}

			$this->voting_blocked = $blocked;
		}

		return $this->voting_blocked;
	}

	public function has_voted(): bool {
		$this->has_voted ??= (bool) $this->voted_for();

		return $this->has_voted;
	}

	public function voted_for(): string {
		$this->voted_for ??= $this->resolve_voted_for();

		return $this->voted_for;
	}

	public function set_blocked_by_not_logged( bool $blocked ): void {
		$this->blocked_by_not_logged = $blocked;
		if( $blocked ){
			$this->voting_blocked = true;
		}
	}

	public function set_voting_blocked( bool $blocked ): void {
		$this->voting_blocked = $blocked;
	}

	public function set_has_voted( bool $has_voted ): void {
		$this->has_voted = $has_voted;
	}

	public function set_voted_for( string $voted_for ): void {
		$this->voted_for = $voted_for;
		$this->has_voted = (bool) $voted_for;
	}

	public function sync_vote_cookie(): void {
		$this->poll_cookie->set();
	}

	public function set_not_voted_cookie(): void {
		$this->poll_cookie->set_not_voted();
	}

	private function resolve_voted_for(): string {
		if( ! $this->poll->id ){
			return '';
		}

		// The database takes precedence over cookies, because in one browser you can cancel the vote,
		// but in another browser cookies will still show that you have voted...
		// NOTE: update cookies if they do not match. Because in different browsers they can be different. Does not work,
		// because cookies need to be set before outputting data, and in general, this should not be done, because checking
		// by cookies becomes unnecessary overall...
		if( options()->keep_logs && ( $logs = $this->poll_logs->get_user_vote_logs() ) ){
			return (string) reset( $logs )->aids;
		}

		if( ! $this->poll_cookie->is_not_voted() ){
			return $this->poll_cookie->get();
		}

		return '';
	}

}
