<?php

namespace DemocracyPoll;

use DemPoll;

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
			wp_die( 'error: no parameters have been sent or it is unavailable' );
		}

		if( ! $vars->pid ){
			wp_die( 'error: unknown poll id' );
		}

		$poll = new DemPoll( $vars->pid );
		$render = $poll->renderer;
		$control = $poll->control;

		// vote and display results
		if( 'vote' === $vars->act && $vars->aids ){
			$voted = $control->vote( $vars->aids );

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
		elseif( 'delVoted' === $vars->act ){
			$control->delete_vote();
			echo $render->get_vote_screen();
		}
		// view results
		elseif( 'view' === $vars->act ){
			if( $render->not_show_results ){
				echo $render->get_vote_screen();
			}
			else{
				echo $render->get_result_screen();
			}
		}
		// back to voting
		elseif( 'vote_screen' === $vars->act ){
			echo $render->get_vote_screen();
		}
		/** Get {@see \DemPoll::$voted_for} value */
		elseif( 'getVotedIds' === $vars->act ){
			if( $poll->voted_for ){
				$control->poll_cookie->set(); // request is only made if cookies are not set
				echo $poll->voted_for;
			}
			elseif( $poll->blocked_by_not_logged ){
				echo 'blocked_because_not_logged_note'; // to display a note
			}
			else{
				// Cache a missing vote for half a day to avoid repeating this check.
				$control->poll_cookie->set_not_voted();
			}
		}

		wp_die();
	}

}
