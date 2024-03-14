<?php /** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */

namespace DemocracyPoll\Admin;

class List_Table_Polls extends \WP_List_Table {

	public function __construct() {

		parent::__construct( [
			'singular' => 'dempoll',
			'plural'   => 'dempolls',
			'ajax'     => false,
		] );

		// per_page опция для страницы
		add_screen_option( 'per_page', [
			'label'   => __( 'Show on page', 'democracy-poll' ),
			'default' => 10,
			'option'  => 'dem_polls_per_page',
		] );

		$this->prepare_items();
	}

	public function prepare_items() {
		global $wpdb;

		$per_page = get_user_meta( get_current_user_id(), get_current_screen()->get_option( 'per_page', 'option' ), true ) ?: 10;

		$where = 'WHERE 1';
		if( $s = @ $_GET['s'] ){
			$like = '%' . $wpdb->esc_like( $s ) . '%';
			$where .= $wpdb->prepare( " AND ( question LIKE %s OR id IN (SELECT qid from $wpdb->democracy_a WHERE answer LIKE %s) ) ", $like, $like );
		}

		// pagination
		$this->set_pagination_args( [
			'total_items' => $wpdb->get_var( "SELECT count(*) FROM $wpdb->democracy_q $where" ),
			'per_page'    => $per_page,
		] );
		$cur_page = $this->get_pagenum(); // после set_pagination_args()

		// order by
		$offset = ( $cur_page - 1 ) * $per_page;
		$OFFSET = "LIMIT $offset,$per_page";
		$order = ( isset( $_GET['order'] ) && $_GET['order'] === 'asc' ) ? 'ASC' : 'DESC';
		$orderby = sanitize_key( empty( $_GET['orderby'] ) ? 'id' : $_GET['orderby'] );
		$ORDER_BY = sprintf( "ORDER BY %s %s", $orderby, $order );

		$sql = "SELECT * FROM $wpdb->democracy_q $where $ORDER_BY $OFFSET";

		$this->items = $wpdb->get_results( $sql );
	}

	public function get_columns(): array {
		$columns = [
			//'cb'        => '<input type="checkbox" />',
			'id'         => __( 'ID', 'democracy-poll' ),
			'question'   => __( 'Question', 'democracy-poll' ),
			'open'       => '<span class="dashicons dashicons-yes" title="' . __( 'Poll Opened', 'democracy-poll' ) . '"></span>',
			'active'     => '<span class="dashicons dashicons-controls-play" title="' . __( 'Active polls', 'democracy-poll' ) . '"></span>',
			'usersvotes' => '<span class="dashicons dashicons-admin-users" title="' . __( 'Users vote', 'democracy-poll' ) . '"></span>',
			'answers'    => __( 'Answers', 'democracy-poll' ),
			'in_posts'   => __( 'In posts', 'democracy-poll' ),
			'added'      => __( 'Added', 'democracy-poll' ),
		];

		return $columns;
	}

	public function get_hidden_columns(): array {
		return [];
	}

	public function get_sortable_columns(): array {
		return [
			'id'         => [ 'id', 'asc' ],
			'question'   => [ 'question', 'asc' ],
			'open'       => [ 'open', 'asc' ],
			'active'     => [ 'active', 'asc' ],
			'usersvotes' => [ 'users_voted', 'asc' ],
			'added'      => [ 'added', 'asc' ],
		];
	}

	public function column_default( $poll, $col ) {
		global $wpdb;
		static $cache;

		if( ! isset( $cache[ $poll->id ] ) ){
			$cache[ $poll->id ] = $wpdb->get_results( "SELECT * FROM $wpdb->democracy_a WHERE qid = " . (int) $poll->id );
		}

		$answ = & $cache[ $poll->id ];

		$admurl = democr()->admin_page_url();
		$date_format = get_option( 'date_format' );

		// вывод
		if( $col === 'question' ){
			$statuses =
				'<span class="statuses">' .
				( $poll->democratic ? '<span class="dashicons dashicons-megaphone" title="' . __( 'Users can add answers (democracy).', 'democracy-poll' ) . '"></span>' : '' ) .
				( $poll->revote ? '<span class="dashicons dashicons-update" title="' . __( 'Users can revote', 'democracy-poll' ) . '"></span>' : '' ) .
				( $poll->forusers ? '<span class="dashicons dashicons-admin-users" title="' . __( 'Only for registered user.', 'democracy-poll' ) . '"></span>' : '' ) .
				( $poll->multiple ? '<span class="dashicons dashicons-image-filter" title="' . __( 'Users can choose many answers (multiple).', 'democracy-poll' ) . '"></span>' : '' ) .
				( $poll->show_results ? '<span class="dashicons dashicons-visibility" title="' . __( 'Allow to watch the results of the poll.', 'democracy-poll' ) . '"></span>' : '' ) .
				'</span>';

			// actions
			$actions = [];
			// user can edit
			if( democr()->cuser_can_edit_poll( $poll ) ){
				// edit
				$actions[] = sprintf(
					'<span class="edit"><a href="%s">%s</a> | </span>',
					democr()->edit_poll_url( $poll->id ),
					__( 'Edit', 'democracy-poll' )
				);

				// logs
				$has_logs = demopt()->keep_logs && $wpdb->get_var( $wpdb->prepare( "SELECT qid FROM $wpdb->democracy_log WHERE qid=%d LIMIT 1", $poll->id ) );
				if( $has_logs ){
					$actions[] = sprintf(
						'<span class="edit"><a href="%s">%s</a> | </span>',
						add_query_arg( [ 'subpage' => 'logs', 'poll' => $poll->id ], $admurl ),
						__( 'Logs', 'democracy-poll' )
					);
				}

				// delete
				$actions[] = '<span class="delete"><a href="' . dem__add_nonce( add_query_arg( [ 'delete_poll' => $poll->id ], $admurl ) ) . '" onclick="return confirm(\'' . __( 'Are you sure?', 'democracy-poll' ) . '\');">' . __( 'Delete', 'democracy-poll' ) . '</a> | </span>';
			}

			// shortcode
			$actions[] = '<span style="color:#999">' . \DemPoll::shortcode_html( $poll->id ) . '</span>';

			return $statuses . democr()->kses_html( $poll->question ) . '<div class="row-actions">' . implode( " ", $actions ) . '</div>';
		}

		if( $col === 'usersvotes' ){
			$votes_sum = array_sum( wp_list_pluck( (array) $answ, 'votes' ) );

			return $poll->multiple ? '<span title="' . __( 'voters / votes', 'democracy-poll' ) . '">' . $poll->users_voted . ' <small>/ ' . $votes_sum . '</small></span>' : $votes_sum;
		}

		if( $col === 'in_posts' ){
			if( ! $posts = democr()->get_in_posts_posts( $poll ) ){
				return '';
			}

			$out = [];

			$__substr = function_exists( 'mb_substr' ) ? 'mb_substr' : 'substr';
			foreach( $posts as $post ){
				$out[] = '<a href="' . get_permalink( $post ) . '">' . $__substr( $post->post_title, 0, 80 ) . ' ...</a>';
			}

			$_style = ' style="margin-bottom:0; line-height:1.4;"';

			return ( count( $out ) > 1 )
				? '<ol class="in__posts" style="margin:0 0 0 1em;"><li' . $_style . '>' . implode( '</li><li' . $_style . '>', $out ) . '</li></ol>'
				: $out[0];
		}

		if( $col === 'answers' ){
			if( ! $answ ){
				return 'Нет';
			}

			usort( $answ, static function( $a, $b ) {
				return $b->votes <=> $a->votes;
			} );

			$_answ = [];
			foreach( $answ as $ans ){
				$_answ[] = '<small>' . $ans->votes . '</small> ' . $ans->answer;
			}

			return '<div class="compact-answ">' . implode( '<br>', $_answ ) . '</div>';
		}

		if( $col === 'active' ){
			return democr()->cuser_can_edit_poll( $poll ) ? dem_activatation_buttons( $poll, 'reverse' ) : '';
		}

		if( $col === 'open' ){
			return democr()->cuser_can_edit_poll( $poll ) ? dem_opening_buttons( $poll, 'reverse' ) : '';
		}

		if( $col === 'added' ){
			$date = date( $date_format, $poll->added );
			$end = $poll->end ? date( $date_format, $poll->end ) : '';

			return "$date<br>$end";
		}

		return $poll->$col ?? print_r( $poll, true );
	}

	public function column_cb( $item ) {
		echo '<label><input id="cb-select-' . @ $item->id . '" type="checkbox" name="delete[]" value="' . @ $item->id . '" /></label>';
	}

	public function search_box( $text, $wrap_attr = '' ) {

		if( empty( $_REQUEST['s'] ) && ! $this->has_items() ){
			return;
		}

		$query = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY );
		parse_str( $query, $arr );

		$inputs = '';
		foreach( $arr as $k => $v ){
			$inputs .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		?>
		<form action="" method="get" class="search-form">
			<?= $inputs ?>
			<p class="polls-search-box" <?= $wrap_attr ?>>
				<label class="screen-reader-text"><?= $text ?>:</label>
				<input type="search" name="s" value="<?php _admin_search_query() ?>"/>
				<?php submit_button( $text, 'button', '', false, [ 'id' => 'search-submit' ] ) ?>
			</p>
		</form>
		<?php
	}

}