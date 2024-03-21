<?php

namespace DemocracyPoll;

class Poll_Ajax {

	public $ajax_url;

	public function __construct(){
		$this->ajax_url = admin_url( 'admin-ajax.php' );
	}

	public function init(){
		// ajax request во frontend_init нельзя, потому что срабатывает только как is_admin()
		add_action( 'wp_ajax_dem_ajax', [ $this, 'ajax_request_handler' ] );
		add_action( 'wp_ajax_nopriv_dem_ajax', [ $this, 'ajax_request_handler' ] );

		// to work without AJAX
		if( isset( $_POST['dem_act'] ) &&
		    ( ! isset( $_POST['action'] ) || 'dem_ajax' !== $_POST['action'] )
		){
			add_action( 'init', [ $this, 'not_ajax_request_handler' ], 99 );
		}
	}

	# Делает предваритеьную проверку передавемых переменных запроса
	public function sanitize_request_vars(): array {

		return [
			'act'  => sanitize_text_field( $_POST['dem_act'] ?? '' ),
			'pid'  => (int) ( $_POST['dem_pid'] ?? 0 ),
			'aids' => wp_unslash( $_POST['answer_ids'] ?? '' ),
		];
	}

	# обрабатывает запрос AJAX
	public function ajax_request_handler() {

		$vars = (object) $this->sanitize_request_vars();

		if( ! $vars->act ){
			wp_die( 'error: no parameters have been sent or it is unavailable' );
		}

		if( ! $vars->pid ){
			wp_die( 'error: unknown poll id' );
		}

		$poll = new \DemPoll( $vars->pid );

		// switch
		// голосуем и выводим результаты
		if( 'vote' === $vars->act && $vars->aids ){
			$voted = $poll->vote( $vars->aids );

			if( is_wp_error( $voted ) ){
				echo $poll::voted_notice_html( $voted->get_error_message() );
				echo $poll->get_vote_screen();
			}
			elseif( $poll->not_show_results ){
				echo $poll->get_vote_screen();
			}
			else{
				echo $poll->get_result_screen();
			}
		}
		// удаляем результаты
		elseif( 'delVoted' === $vars->act ){
			$poll->delete_vote();
			echo $poll->get_vote_screen();
		}
		// смотрим результаты
		elseif( 'view' === $vars->act ){
			if( $poll->not_show_results ){
				echo $poll->get_vote_screen();
			}
			else{
				echo $poll->get_result_screen();
			}
		}
		// вернуться к голосованию
		elseif( 'vote_screen' === $vars->act ){
			echo $poll->get_vote_screen();
		}
		elseif( 'getVotedIds' === $vars->act ){
			if( $poll->votedFor ){
				$poll->set_cookie(); // Установим куки, т.к. этот запрос делается только если куки не установлены
				echo $poll->votedFor;
			}
			elseif( $poll->blockForVisitor ){
				echo 'blockForVisitor'; // чтобы вывести заметку
			}
			else{
				// если не голосовал ставим куки на пол дня, чтобы не делать эту проверку каждый раз
				$poll->set_cookie( 'notVote', ( time() + ( DAY_IN_SECONDS / 2 ) ) );
			}
		}

		wp_die();
	}

	/**
	 * To work without AJAX.
	 */
	public function not_ajax_request_handler() {

		$vars = (object) $this->sanitize_request_vars();

		if( ! $vars->act || ! $vars->pid || ! isset( $_SERVER['HTTP_REFERER'] ) ){
			return;
		}

		$poll = new \DemPoll( $vars->pid );

		if( 'vote' === $vars->act && $vars->aids ){
			$poll->vote( $vars->aids );
			wp_safe_redirect( remove_query_arg( [ 'dem_act', 'dem_pid' ], $_SERVER['HTTP_REFERER'] ) );

			exit;
		}

		if( 'delVoted' === $vars->act ){
			$poll->delete_vote();
			wp_safe_redirect( remove_query_arg( [ 'dem_act', 'dem_pid' ], $_SERVER['HTTP_REFERER'] ) );

			exit;
		}
	}

}
