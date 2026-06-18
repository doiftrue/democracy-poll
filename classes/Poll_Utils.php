<?php

namespace DemocracyPoll;

/**
 * Provides helper methods for accessing poll data, checking permissions,
 * formatting results, and other non-core functionality to support the {@see \DemPoll} class.
 */
class Poll_Utils {

	/**
	 * Gets the URL to edit a poll in the admin panel.
	 *
	 * @param int $poll_id  Poll ID
	 */
	public static function edit_poll_url( $poll_id ): string {
		return plugin()->admin_page_url . '&edit_poll=' . (int) $poll_id;
	}

	/**
	 * Check whether current user can edit a specified poll.
	 *
	 * @param \DemPoll|object|int $poll  Poll object or poll id.
	 */
	public static function cuser_can_edit_poll( $poll ): bool {
		if( plugin()->super_access ){
			return true;
		}

		if( ! plugin()->admin_access ){
			return false;
		}

		if( is_numeric( $poll ) ){
			$poll = Poll_Storage::get_db_data( $poll );
		}

		return $poll && (int) $poll->added_user === (int) get_current_user_id();
	}

	public static function get_minified_styles_once(): string {
		static $once = 0;
		if( $once++ ){
			return '';
		}

		$demcss = get_option( 'democracy_css' );
		$minified = $demcss['minify'] ?? '';

		return $minified
			? "\n" . '<style id="democracy-poll-css">' . $minified . '</style>' . "\n"
			: '';
	}

	public static function enqueue_js_once(): void {
		static $once = 0;
		if( $once++ ){
			return;
		}

		// inline HTML
		wp_enqueue_script( 'democracy', plugin()->url . '/assets/js/democracy.min.js', [], plugin()->ver, [
			'in_footer' => true,
			'strategy'  => 'defer',
		] );
	}

}
