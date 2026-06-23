<?php

namespace DemocracyPoll;

class Poll_Ajax {

	public string $ajax_url;

	public function __construct(){
		$this->ajax_url = admin_url( 'admin-ajax.php' );
	}

	public function init(): void {
		// AJAX requests cannot be registered in frontend_init because they run under is_admin().
		add_action( 'wp_ajax_dem_ajax', [ $this, 'ajax_request_handler' ] );
		add_action( 'wp_ajax_nopriv_dem_ajax', [ $this, 'ajax_request_handler' ] );
	}

	/**
	 * Does a preliminary sanitization of the passed request variables.
	 */
	public function sanitize_request_vars(): array {
		return [
			'act'  => sanitize_text_field( $_POST['dem_act'] ?? '' ),
			'pid'  => (int) ( $_POST['dem_pid'] ?? 0 ),
			'aids' => wp_unslash( $_POST['answer_ids'] ?? '' ),
		];
	}

	public function ajax_request_handler(): void {
		$vars = (object) $this->sanitize_request_vars();

		if( ! $vars->act ){
			wp_die( 'error: invalid `act` parameter' );
		}

		if( ! $vars->pid ){
			wp_die( 'error: invalid `pid` parameter' );
		}

		$poll = new Poll( $vars->pid );
		$render = new Poll_Renderer( $poll );
		$voting = new Poll_Voting_Service( $poll );

		if( 'vote' === $vars->act ){
			$this->act__vote( $render, $voting, $vars->aids );
		}
		elseif( 'viewResults' === $vars->act || 'view' === $vars->act /* legacy */ ){
			$this->act__view_results( $render );
		}
		elseif( 'voteScreen' === $vars->act || 'vote_screen' === $vars->act /* legacy */ ){
			$this->act__vote_screen( $render );
		}
		elseif( 'delVoted' === $vars->act ){
			$this->act__delete_vote( $render, $voting );
		}
		elseif( 'getVotedIds' === $vars->act ){
			$this->act__get_voted_ids( $poll );
		}
		else{
			wp_die( 'error: unknown action' );
		}

		wp_die(); // required exit for AJAX requests
	}

	/**
	 * Vote and display results.
	 *
	 * @param Poll_Renderer       $render
	 * @param Poll_Voting_Service $voting
	 * @param array|string        $aids
	 */
	private function act__vote( Poll_Renderer $render, Poll_Voting_Service $voting, $aids ): void {
		$voted = $voting->vote( $aids );

		if( is_wp_error( $voted ) ){
			echo $render::voted_notice_html( $voted->get_error_message() );
			echo $render->get_vote_screen();
		}
		elseif( $render->not_show_results ){
			echo $render->get_vote_screen();
		}
		else{
			echo $render->get_result_screen();
		}
	}

	// delete results
	private function act__delete_vote( Poll_Renderer $render, Poll_Voting_Service $voting ): void {
		$voting->delete_vote();
		echo $render->get_vote_screen();
	}

	// view results
	private function act__view_results( Poll_Renderer $render ): void {
		if( $render->not_show_results ){
			echo $render->get_vote_screen();
		}
		else{
			echo $render->get_result_screen();
		}
	}

	// back to voting
	private function act__vote_screen( Poll_Renderer $render ): void {
		echo $render->get_vote_screen();
	}

	// request is only made if cookies are not set - 'checkAnswDone' not done
	private function act__get_voted_ids( Poll $poll ): void {
		$ustate = $poll->user_state;
		if( $ustate->voted_for ){
			$ustate->set_vote_cookie();
			echo $ustate->voted_for;
		}
		elseif( $ustate->blocked_by_not_logged ){
			echo 'blocked_because_not_logged_note'; // to display a note
		}
		else{
			// Cache a missing vote for half a day to avoid repeating this check.
			$ustate->set_not_voted_cookie();
		}
	}

}
