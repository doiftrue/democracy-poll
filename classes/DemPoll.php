<?php

use DemocracyPoll\Helpers\Helpers;
use DemocracyPoll\Helpers\IP;
use DemocracyPoll\Helpers\Kses;
use function DemocracyPoll\plugin;
use function DemocracyPoll\options;

/**
 * Display and vote a separate poll.
 * Needs a Dem plugin class.
 */
class DemPoll {

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

	/** @var object[] */
	public $answers = [];

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
	}

	/**
	 * @param int|string $poll_id Poll id to get. Or use 'rand', 'last' strings.
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

		return apply_filters( 'dem_get_poll', $poll_obj, $poll_id );
	}

	/**
	 * Получает HTML опроса.
	 *
	 * @param string $show_screen  Какой экран показывать: vote, voted, force_vote.
	 *
	 * @return string|false HTML.
	 */
	public function get_screen( $show_screen = 'vote', $before_title = '', $after_title = '' ) {

		if( ! $this->id ){
			return false;
		}

		$this->in_archive = ( (int) ( $GLOBALS['post']->ID ?? 0 ) === (int) options()->archive_page_id ) && is_singular();

		if( $this->blockVoting && $show_screen !== 'force_vote' ){
			$show_screen = 'voted';
		}

		$html = '';
		$html .= plugin()->get_minified_styles_once();

		$js_opts = [
			'ajax_url'         => plugin()->poll_ajax->ajax_url,
			'pid'              => $this->id,
			'max_answs'        => (int) ( $this->multiple ?: 0 ),
			'answs_max_height' => options()->answs_max_height,
			'anim_speed'       => options()->anim_speed,
			'line_anim_speed'  => (int) options()->line_anim_speed
		];

		$html .= '<div id="democracy-' . $this->id . '" class="democracy" data-opts=\'' . json_encode( $js_opts ) . '\' >';
		$html .= $before_title ?: options()->before_title;
		$html .= Kses::kses_html( $this->question );
		$html .= $after_title ?: options()->after_title;

		// изменяемая часть
		$html .= $this->get_screen_basis( $show_screen );
		// изменяемая часть

		$html .= $this->note ? '<div class="dem-poll-note">' . wpautop( $this->note ) . '</div>' : '';

		if( plugin()->cuser_can_edit_poll( $this ) ){
			$html .= '<a class="dem-edit-link" href="' . plugin()->edit_poll_url( $this->id ) . '" title="' . __( 'Edit poll', 'democracy-poll' ) . '"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="1.5em" height="100%" viewBox="0 0 1000 1000" enable-background="new 0 0 1000 1000" xml:space="preserve"><path d="M617.8,203.4l175.8,175.8l-445,445L172.9,648.4L617.8,203.4z M927,161l-78.4-78.4c-30.3-30.3-79.5-30.3-109.9,0l-75.1,75.1 l175.8,175.8l87.6-87.6C950.5,222.4,950.5,184.5,927,161z M80.9,895.5c-3.2,14.4,9.8,27.3,24.2,23.8L301,871.8L125.3,696L80.9,895.5z"/></svg></a>';
		}

		// copyright
		if( options()->show_copyright && ( is_home() || is_front_page() ) ){
			$html .= '<a class="dem-copyright" href="http://wp-kama.ru/?p=67" target="_blank" rel="noopener" title="' . __( 'Download the Democracy Poll', 'democracy-poll' ) . '" onmouseenter="var $el = jQuery(this).find(\'span\'); $el.stop().animate({width:\'toggle\'},200); setTimeout(function(){ $el.stop().animate({width:\'toggle\'},200); }, 4000);"> © <span style="display:none;white-space:nowrap;">Kama</span></a>';
		}

		// loader
		if( options()->loader_fname ){
			static $loader; // оптимизация, чтобы один раз выводился код на странице
			if( ! $loader ){
				$loader = '<div class="dem-loader"><div>' . file_get_contents( DEMOC_PATH . 'styles/loaders/' . options()->loader_fname ) . '</div></div>';
				$html .= $loader;
			}
		}

		$html .= "</div><!--democracy-->";

		// for page cache
		// never use poll caching mechanism in admin
		if( ! $this->in_archive && ! is_admin() && plugin()->is_cachegear_on ){
			$html .= '
			<!--noindex-->
			<div class="dem-cache-screens" style="display:none;" data-opt_logs="' . (int) options()->keep_logs . '">';

			// запоминаем
			$voted_for = $this->votedFor;
			$this->votedFor = false;
			$this->for_cache = 1;

			$compress = static function( $str ) {
				return preg_replace( "~[\n\r\t]~u", '', preg_replace( '~\s+~u', ' ', $str ) );
			};

			// voted_screen
			if( ! $this->not_show_results ){
				$html .= $compress( $this->get_screen_basis( 'voted' ) );
			}

			// vote_screen
			if( $this->open ){
				$html .= $compress( $this->get_screen_basis( 'force_vote' ) );
			}

			$this->for_cache = 0;
			$this->votedFor = $voted_for; // возвращаем

			$html .= '
			</div>
			<!--/noindex-->';
		}

		if( ! options()->disable_js ){
			plugin()->add_js_once();
		}

		return $html;
	}

	/**
	 * Получает сердце HTML опроса (изменяемую часть)
	 *
	 * @param bool $show_screen
	 *
	 * @return string HTML
	 */
	protected function get_screen_basis( $show_screen = 'vote' ): string {
		$class_suffix = $this->for_cache ? '-cache' : '';

		if( $this->not_show_results ){
			$show_screen = 'force_vote';
		}

		$screen = ( $show_screen === 'vote' || $show_screen === 'force_vote' ) ? 'vote' : 'voted';

		$html = '<div class="dem-screen' . $class_suffix . ' ' . $screen . '">';
		$html .= ( $screen === 'vote' ) ? $this->get_vote_screen() : $this->get_result_screen();
		$html .= '</div>';

		if( ! $this->for_cache ){
			$html .= '<noscript>Poll Options are limited because JavaScript is disabled in your browser.</noscript>';
		}

		return $html;
	}

	/**
	 * Получает код для голосования
	 *
	 * @return string HTML
	 */
	public function get_vote_screen() {

		if( ! $this->id ){
			return false;
		}

		$auto_vote_on_select = ( ! $this->multiple && $this->revote && options()->hide_vote_button );

		$html = '';

		$html .= '<form method="POST" action="#democracy-' . $this->id . '">';
			$html .= '<ul class="dem-vote">';

				$type = $this->multiple ? 'checkbox' : 'radio';

				foreach( $this->answers as $answer ){
					$answer = apply_filters( 'dem_vote_screen_answer', $answer );

					$auto_vote = $auto_vote_on_select ? 'data-dem-act="vote"' : '';

					$checked = $disabled = '';
					if( $this->votedFor ){
						if( in_array( $answer->aid, explode( ',', $this->votedFor ) ) ){
							$checked = ' checked="checked"';
						}

						$disabled = ' disabled="disabled"';
					}

					$html .= '
					<li data-aid="' . $answer->aid . '">
						<label class="dem__' . $type . '_label">
							<input class="dem__' . $type . '" ' . $auto_vote . ' type="' . $type . '" value="' . $answer->aid . '" name="answer_ids[]"' . $checked . $disabled . '><span class="dem__spot"></span> ' . $answer->answer . '
						</label>
					</li>';
				}

				if( $this->democratic && ! $this->blockVoting ){
					$html .= '<li class="dem-add-answer"><a href="javascript:void(0);" rel="nofollow" data-dem-act="newAnswer" class="dem-link">' . _x( 'Add your answer', 'front', 'democracy-poll' ) . '</a></li>';
				}
			$html .= "</ul>";

		$html .= '<div class="dem-bottom">';
		$html .= '<input type="hidden" name="dem_act" value="vote">';
		$html .= '<input type="hidden" name="dem_pid" value="' . $this->id . '">';

		$btnVoted = '<div class="dem-voted-button"><input class="dem-button ' . options()->btn_class . '" type="submit" value="' . _x( 'Already voted...', 'front', 'democracy-poll' ) . '" disabled="disabled"></div>';
		$btnVote = '<div class="dem-vote-button"><input class="dem-button ' . options()->btn_class . '" type="submit" value="' . _x( 'Vote', 'front', 'democracy-poll' ) . '" data-dem-act="vote"></div>';

		if( $auto_vote_on_select ){
			$btnVote = '';
		}

		$for_users_alert = $this->blockForVisitor ? '<div class="dem-only-users">' . self::registered_only_alert_text() . '</div>' : '';

		// для экша
		if( $this->for_cache ){
			$html .= self::voted_notice_html();

			if( $for_users_alert ){
				$html .= str_replace(
					[ '<div', 'class="' ], [ '<div style="display:none;"', 'class="dem-notice ' ], $for_users_alert
				);
			}

			if( $this->revote ){
				$html .= preg_replace( '/(<[^>]+)/', '$1 style="display:none;"', $this->revote_btn_html(), 1 );
			}
			else{
				$html .= substr_replace( $btnVoted, '<div style="display:none;"', 0, 4 );
			}
			$html .= $btnVote;
		}
		// не для кэша
		else{
			if( $for_users_alert ){
				$html .= $for_users_alert;
			}
			else{
				if( $this->has_voted ){
					$html .= $this->revote ? $this->revote_btn_html() : $btnVoted;
				}
				else{
					$html .= $btnVote;
				}
			}
		}

		if( ! $this->not_show_results && ! options()->dont_show_results_link ){
			$html .= '<a href="javascript:void(0);" class="dem-link dem-results-link" data-dem-act="view" rel="nofollow">' . _x( 'Results', 'front', 'democracy-poll' ) . '</a>';
		}


		$html .= '</div>';

		$html .= '</form>';

		return apply_filters( 'dem_vote_screen', $html, $this );
	}

	/**
	 * Получает код результатов голосования
	 * @return string HTML
	 */
	public function get_result_screen() {

		if( ! $this->id ){
			return false;
		}

		// отсортируем по голосам
		$answers = Helpers::objects_array_sort( $this->answers, [ 'votes' => 'desc' ] );

		$html = '';

		$max = $total = 0;

		foreach( $answers as $answer ){
			$total += $answer->votes;
			if( $max < $answer->votes ){
				$max = $answer->votes;
			}
		}

		$voted_class = 'dem-voted-this';
		$voted_txt = _x( 'This is Your vote.', 'front', 'democracy-poll' );
		$html .= '<ul class="dem-answers" data-voted-class="' . $voted_class . '" data-voted-txt="' . $voted_txt . '">';

		foreach( $answers as $answer ){

			// склонение голосов
			$__sclonenie = function( $number, $titles, $nonum = false ) {
				$titles = explode( ',', $titles );

				if( 2 === count( $titles ) ){
					$titles[2] = $titles[1];
				}

				$cases = [ 2, 0, 1, 1, 1, 2 ];

				return ( $nonum ? '' : "$number " ) . $titles[ ( $number % 100 > 4 && $number % 100 < 20 ) ? 2 : $cases[ min( $number % 10, 5 ) ] ];
			};

			$answer = apply_filters( 'dem_result_screen_answer', $answer );

			$votes = (int) $answer->votes;
			$is_voted_this = ( $this->has_voted && in_array( $answer->aid, explode( ',', $this->votedFor ) ) );
			$is_winner = ( $max == $votes );

			$novoted_class = ( $votes == 0 ) ? ' dem-novoted' : '';
			$li_class = ' class="' . ( $is_winner ? 'dem-winner' : '' ) . ( $is_voted_this ? " $voted_class" : '' ) . $novoted_class . '"';
			$sup = $answer->added_by ? '<sup class="dem-star" title="' . _x( 'The answer was added by a visitor', 'front', 'democracy-poll' ) . '">*</sup>' : '';
			$percent = ( $votes > 0 ) ? round( $votes / $total * 100 ) : 0;

			$percent_txt = sprintf( _x( '%s - %s%% of all votes', 'front', 'democracy-poll' ), $__sclonenie( $votes, _x( 'vote,votes,votes', 'front', 'democracy-poll' ) ), $percent );
			$title = ( $is_voted_this ? $voted_txt : '' ) . ' ' . $percent_txt;
			$title = " title='$title'";

			$votes_txt = $votes . ' ' . '<span class="votxt">' . $__sclonenie( $votes, _x( 'vote,votes,votes', 'front', 'democracy-poll' ), 'nonum' ) . '</span>';

			$html .= '<li' . $li_class . $title . ' data-aid="' . $answer->aid . '">';
			$label_perc_txt = ' <span class="dem-label-percent-txt">' . $percent . '%, ' . $votes_txt . '</span>';
			$percent_txt = '<div class="dem-percent-txt">' . $percent_txt . '</div>';
			$votes_txt = '<div class="dem-votes-txt">
						<span class="dem-votes-txt-votes">' . $votes_txt . '</span>
						' . ( ( $percent > 0 ) ? ' <span class="dem-votes-txt-percent">' . $percent . '%</span>' : '' ) . '
						</div>';

			$html .= '<div class="dem-label">' . $answer->answer . $sup . $label_perc_txt . '</div>';

			// css процент
			$graph_percent = ( ( ! options()->graph_from_total && $percent != 0 ) ? round( $votes / $max * 100 ) : $percent ) . '%';
			if( $graph_percent == 0 ){
				$graph_percent = '1px';
			}

			$html .= '<div class="dem-graph">';
			$html .= '<div class="dem-fill" ' . ( options()->line_anim_speed ? 'data-width="' : 'style="width:' ) . $graph_percent . '"></div>';
			$html .= $votes_txt;
			$html .= $percent_txt;
			$html .= "</div>";
			$html .= "</li>";
		}
		$html .= '</ul>';

		// dem-bottom
		$html .= '<div class="dem-bottom">';
		$html .= '<div class="dem-poll-info">';
		$html .= '<div class="dem-total-votes">' . sprintf( _x( 'Total Votes: %s', 'front', 'democracy-poll' ), $total ) . '</div>';
		$html .= ( $this->multiple ? '<div class="dem-users-voted">' . sprintf( _x( 'Voters: %s', 'front', 'democracy-poll' ), $this->users_voted ) . '</div>' : '' );
		$html .= '
				<div class="dem-date" title="' . _x( 'Begin', 'front', 'democracy-poll' ) . '">
					<span class="dem-begin-date">' . date_i18n( get_option( 'date_format' ), $this->added ) . '</span>
					' . ( $this->end ? ' - <span class="dem-end-date" title="' . _x( 'End', 'front', 'democracy-poll' ) . '">' . date_i18n( get_option( 'date_format' ), $this->end ) . '</span>' : '' ) . '
				</div>';
		$html .= $answer->added_by ? '<div class="dem-added-by-user"><span class="dem-star">*</span>' . _x( ' - added by visitor', 'front', 'democracy-poll' ) . '</div>' : '';
		$html .= ! $this->open ? '<div>' . _x( 'Voting is closed', 'front', 'democracy-poll' ) . '</div>' : '';
		if( ! $this->in_archive && options()->archive_page_id ){
			$html .= '<a class="dem-archive-link dem-link" href="' . get_permalink( options()->archive_page_id ) . '" rel="nofollow">' . _x( 'Polls Archive', 'front', 'democracy-poll' ) . '</a>';
		}
		$html .= '</div>';

		if( $this->open ){
			// заметка для незарегистрированных пользователей
			$for_users_alert = $this->blockForVisitor ? '<div class="dem-only-users">' . self::registered_only_alert_text() . '</div>' : '';

			// вернуться к голосованию
			$vote_btn = '<button type="button" class="dem-button dem-vote-link ' . options()->btn_class . '" data-dem-act="vote_screen">' . _x( 'Vote', 'front', 'democracy-poll' ) . '</button>';

			// для кэша
			if( $this->for_cache ){
				$html .= self::voted_notice_html();

				if( $for_users_alert ){
					$html .= str_replace( [ '<div', 'class="' ], [
						'<div style="display:none;"',
						'class="dem-notice ',
					], $for_users_alert );
				}

				if( $this->revote ){
					$html .= $this->revote_btn_html();
				}
				else{
					$html .= $vote_btn;
				}
			}
			// не для кэша
			else{
				if( $for_users_alert ){
					$html .= $for_users_alert;
				}
				else{
					if( $this->has_voted ){
						if( $this->revote ){
							$html .= $this->revote_btn_html();
						}
					}
					else{
						$html .= $vote_btn;
					}
				}
			}
		}

		$html .= '</div>'; // / dem-bottom

		return apply_filters( 'dem_result_screen', $html, $this );
	}

	protected static function registered_only_alert_text() {
		return str_replace(
			'<a',
			'<a href="' . esc_url( wp_login_url( $_SERVER['REQUEST_URI'] ) ) . '" rel="nofollow"',
			_x( 'Only registered users can vote. <a>Login</a> to vote.', 'front', 'democracy-poll' )
		);
	}

	protected function revote_btn_html(): string {
		return '
		<span class="dem-revote-button-wrap">
		<form action="#democracy-' . $this->id . '" method="POST">
			<input type="hidden" name="dem_act" value="delVoted">
			<input type="hidden" name="dem_pid" value="' . $this->id . '">
			<input type="submit" value="' . _x( 'Revote', 'front', 'democracy-poll' ) . '" class="dem-revote-link dem-revote-button dem-button ' . options()->btn_class . '" data-dem-act="delVoted" data-confirm-text="' . _x( 'Are you sure you want cancel the votes?', 'front', 'democracy-poll' ) . '">
		</form>
		</span>';
	}

	/**
	 * заметка: вы уже голосовали
	 * @return string Текст заметки
	 */
	public static function voted_notice_html( $msg = '' ): string {
		if( ! $msg ){
			return '
			<div class="dem-notice dem-youarevote" style="display:none;">
				<div class="dem-notice-close" onclick="jQuery(this).parent().fadeOut();">&times;</div>
				' . _x( 'You or your IP had already vote.', 'front', 'democracy-poll' ) . '
			</div>';
		}

		return '
		<div class="dem-notice">
			<div class="dem-notice-close" onclick="jQuery(this).parent().fadeOut();">&times;</div>
			' . $msg . '
		</div>';
	}

	/**
	 * Добавляет голос.
	 *
	 * @param string|array $aids  ID ответов через запятую. Там может быть строка,
	 *                            тогда она будет добавлена, как ответ пользователя.
	 *
	 * @return WP_Error|string $aids IDs, separated
	 */
	public function vote( $aids ) {

		if( ! $this->id ){
			return new WP_Error( 'vote_err', 'ERROR: no id' );
		}

		// установим куки повторно, был баг...
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

			// не больше чем разрешено...
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

		$this->set_answers(); // переустановим ответы

		$this->set_cookie(); // установим куки

		if( options()->keep_logs ){
			$this->insert_logs();
		}

		do_action_ref_array( 'dem_voted', [ $this->votedFor, $this ] );

		return $this->votedFor;
	}

	/**
	 * Удаляет данные пользователя о голосовании.
	 * Отменяет установленные $this->has_voted и $this->votedFor
	 * Должна вызываться до вывода данных на экран
	 *
	 * @return void
	 */
	public function delete_vote() {

		if( ! $this->id ){
			return;
		}

		if( ! $this->revote ){
			return;
		}

		// Прежде чем удалять, проверим включена ли опция ведения логов и есть ли записи о голосовании в БД,
		// так как куки могут удалить и тогда, данные о голосовании пойдут в минус
		if( options()->keep_logs ){
			if( $this->get_user_vote_logs() ){
				$this->minus_vote();
				$this->delete_vote_log();
			}
		}
		// если опция логов не включена, то отнимаем по кукам.
		// Тут голоса можно откручивать назад, потому что разные браузеры проверить не получится.
		else {
			$this->minus_vote();
		}

		$this->unset_cookie();

		$this->has_voted = false;
		$this->votedFor = false;
		$this->blockVoting = ! $this->open;

		$this->set_answers(); // переустановим ответы, если добавленный ответ был удален

		do_action_ref_array( 'dem_vote_deleted', [ $this ] );
	}

	private function insert_democratic_answer( $answer ): int {
		global $wpdb;

		$new_answer = Kses::sanitize_answer_data( $answer, 'democratic_answer' );
		$new_answer = wp_unslash( $new_answer );

		// проверим нет ли уже такого ответа
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

	## Устанавливает глобальные переменные $this->has_voted и $this->votedFor
	protected function set_voted_data() {
		if( ! $this->id ){
			return false;
		}

		// база приоритетнее куков, потому что в одном браузере можно отменить голосование, а куки в другом будут показывать что голосовал...
		// ЗАМЕТКА: обновим куки, если не совпадают. Потому что в разных браузерах могут быть разыне. Не работает,
		// потому что куки нужно устанавливать перед выводом данных и вообще так делать не нужно, потмоу что проверка
		// по кукам становится не нужной в целом...
		if( options()->keep_logs && ( $res = $this->get_user_vote_logs() ) ){
			$this->has_voted = true;
			$this->votedFor = reset( $res )->aids;
		}
		// проверяем куки
		elseif( isset( $_COOKIE[ $this->cookie_key ] ) && ( $_COOKIE[ $this->cookie_key ] != 'notVote' ) ){
			$this->has_voted = true;
			$this->votedFor = preg_replace( '/[^0-9, ]/', '', $_COOKIE[ $this->cookie_key ] ); // чистим
		}
	}

	## отнимает голоса в БД и удаляет ответ, если надо
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
	 * Получает массив ID ответов из переданной строки, где id разделены запятой.
	 * Чистит для БД!
	 *
	 * @param string $aids_str  Строка с ID ответов
	 *
	 * @return int[]  ID ответов
	 */
	protected function get_answ_aids_from_str( string $aids_str ): array {
		$arr = explode( ',', $aids_str );
		$arr = array_map( 'trim', $arr );
		$arr = array_map( 'intval', $arr );
		$arr = array_filter( $arr );

		return $arr;
	}

	## время до которого логи будут жить
	public function get_cookie_expire_time() {
		return current_time( 'timestamp', $utc = 1 ) + (int) ( (float) options()->cookie_days * DAY_IN_SECONDS );
	}

	/**
	 * Устанавливает куки для текущего опроса.
	 *
	 * @param string $value   Значение куки, по умолчанию текущие голоса.
	 * @param int    $expire  Время окончания кики.
	 *
	 * @return void
	 */
	public function set_cookie( $value = '', $expire = false ) {
		$expire = $expire ?: $this->get_cookie_expire_time();
		$value = $value ?: $this->votedFor;

		setcookie( $this->cookie_key, $value, $expire, COOKIEPATH );

		$_COOKIE[ $this->cookie_key ] = $value;
	}

	public function unset_cookie() {
		setcookie( $this->cookie_key, null, strtotime( '-1 day' ), COOKIEPATH );
		$_COOKIE[ $this->cookie_key ] = '';
	}

	/**
	 * Устанавливает ответы в $this->answers и сортирует их в нужном порядке.
	 */
	protected function set_answers() {
		global $wpdb;

		$answers = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $wpdb->democracy_a WHERE qid = %d", $this->id
		) );

		if( $answers ){
			// не установлен порядок
			if( ! $answers[0]->aorder ){
				$ord = $this->answers_order ?: options()->order_answers;

				if( $ord === 'by_winner' || $ord == 1 ){
					$answers = Helpers::objects_array_sort( $answers, [ 'votes' => 'desc' ] );
				}
				elseif( $ord === 'mix' ){
					shuffle( $answers );
				}
				elseif( $ord === 'by_id' ){}
			}
			// по порядку
			else{
				$answers = Helpers::objects_array_sort( $answers, [ 'aorder' => 'asc' ] );
			}
		}
		else {
			$answers = [];
		}

		$this->answers = apply_filters( 'dem_set_answers', $answers, $this );
	}

	/**
	 * Получает строку логов по ID или IP пользователя
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
	 * Удаляет записи о голосовании в логах.
	 *
	 * @return bool
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


