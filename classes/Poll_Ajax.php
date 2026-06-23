<?php

namespace DemocracyPoll;

class Poll_Ajax {

	public string $ajax_url;

	private string $action;
	private int    $poll_id;
	private string $answer_ids;

	public function __construct(){
		$this->ajax_url = admin_url( 'admin-ajax.php' );
	}

	public function init(): void {
		// AJAX requests cannot be registered in frontend_init because they run under is_admin().
		add_action( 'wp_ajax_dem_ajax', [ $this, 'ajax_request_handler' ] );
		add_action( 'wp_ajax_nopriv_dem_ajax', [ $this, 'ajax_request_handler' ] );
	}

	public function ajax_request_handler(): void {
		$this->set_request_vars();

		if( ! $this->action ){
			wp_die( 'error: invalid `act` parameter' );
		}

		if( ! $this->poll_id ){
			wp_die( 'error: invalid `pid` parameter' );
		}

		$actions = [
			'vote'        => 'action__vote',
			'delVoted'    => 'action__delete_vote',
			'viewResults' => 'action__view_results',
			'view'        => 'action__view_results', // legacy
			'voteScreen'  => 'action__vote_screen',
			'vote_screen' => 'action__vote_screen', // legacy
			'getVotedIds' => 'action__get_voted_ids',
		];

		$method = $actions[ $this->action ] ?? null;
		if( ! $method ){
			wp_die( 'error: unknown action' );
		}

		/**
		 * @see self::action__vote()
		 * @see self::action__delete_vote()
		 * @see self::action__view_results()
		 * @see self::action__vote_screen()
		 * @see self::action__get_voted_ids()
		 */
		$this->$method();

		wp_die(); // required exit for AJAX requests
	}

	/**
	 * Does a preliminary sanitization of the passed request variables.
	 */
	private function set_request_vars(): void {
		$this->action = sanitize_text_field( $_POST['dem_act'] ?? '' );
		$this->poll_id = (int) ( $_POST['dem_pid'] ?? 0 );
		$this->answer_ids = wp_unslash( $_POST['answer_ids'] ?? '' );
	}

	protected function create_poll(): Poll {
		return new Poll( $this->poll_id );
	}

	protected function create_renderer( Poll $poll ): Poll_Renderer {
		return new Poll_Renderer( $poll );
	}

	protected function create_voting_service( Poll $poll ): Poll_Voting_Service {
		return new Poll_Voting_Service( $poll );
	}

	/**
	 * Vote and display results.
	 */
	private function action__vote(): void {
		$poll = $this->create_poll();
		$render = $this->create_renderer( $poll );
		$voting = $this->create_voting_service( $poll );

		$voted = $voting->vote( $this->answer_ids );

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
	private function action__delete_vote(): void {
		$poll = $this->create_poll();
		$render = $this->create_renderer( $poll );
		$voting = $this->create_voting_service( $poll );

		$voting->delete_vote();
		echo $render->get_vote_screen();
	}

	// view results
	private function action__view_results(): void {
		$poll = $this->create_poll();
		$render = $this->create_renderer( $poll );

		if( $render->not_show_results ){
			echo $render->get_vote_screen();
		}
		else{
			echo $render->get_result_screen();
		}
	}

	// back to voting
	private function action__vote_screen(): void {
		$poll = $this->create_poll();
		$render = $this->create_renderer( $poll );

		echo $render->get_vote_screen();
	}

	// request is only made if cookies are not set - 'checkAnswDone' not done
	private function action__get_voted_ids(): void {
		$poll = $this->create_poll();

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
