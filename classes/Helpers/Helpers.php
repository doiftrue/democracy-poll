<?php

namespace DemocracyPoll\Helpers;

final class Helpers {

	public static function allowed_answers_orders(): array {
		return [
			'by_id'     => __( 'As it was added (by ID)', 'democracy-poll' ),
			'by_winner' => __( 'Winners at the top', 'democracy-poll' ),
			'mix'       => __( 'Mix', 'democracy-poll' ),
		];
	}

	public static function answers_order_select_options( $selected = '' ): string {
		$options = [];
		foreach( self::allowed_answers_orders() as $val => $title ){
			$options[] = sprintf( '<option value="%s" %s>%s</option>',
				esc_attr( $val ), selected( $selected, $val, 0 ), esc_html( $title )
			);
		}

		return implode( "\n", $options );
	}

	/**
	 * Сортировка массива объектов.
	 * Передаете в $array массив объектов, указываете в $args параметры
	 * сортировки и получаете отсортированный массив объектов.
	 */
	public static function objects_array_sort( $array, $args = [ 'votes' => 'desc' ] ) {

		usort( $array, static function( $a, $b ) use ( $args ) {
			$res = 0;

			if( is_array( $a ) ){
				$a = (object) $a;
				$b = (object) $b;
			}

			foreach( $args as $k => $v ){
				if( $a->$k === $b->$k ){
					continue;
				}

				$res = ( $a->$k < $b->$k ) ? -1 : 1;
				if( $v === 'desc' ){
					$res = -$res;
				}
				break;
			}

			return $res;
		} );

		return $array;
	}

	/**
	 * Retrieves the post objects to which the poll is attached (where the shortcode is used).
	 *
	 * @param object $poll  The current poll object from the database.
	 *
	 * @return \WP_Post[] An array of post objects or an empty array.
	 */
	public static function get_posts_with_poll( $poll ): array {
		global $wpdb;

		if( empty( $poll->in_posts ) || empty( $poll->id ) ){
			return [];
		}

		$pids = explode( ',', $poll->in_posts );

		$posts = [];

		// delete the IDs of posts that no longer exist.
		$delete_pids = [];
		foreach( $pids as $post_id ){
			$post = get_post( $post_id );
			$post
				? ( $posts[] = $post )
				: ( $delete_pids[] = $post_id );
		}

		if( $delete_pids ){
			$new_in_posts = array_diff( $pids, $delete_pids );
			$wpdb->update( $wpdb->democracy_q, [ 'in_posts' => implode( ',', $new_in_posts ) ], [ 'id' => $poll->id ] );
		}

		return $posts;
	}

	/**
	 * Checks if the page caching plugin is being used and active on the site.
	 */
	public static function is_page_cache_plugin_on(): bool {

		// wp total cache
		if(
			class_exists( \W3TC\Dispatcher::class )
			&& \W3TC\Dispatcher::component( 'ModuleStatus' )
			&& \W3TC\Dispatcher::component( 'ModuleStatus' )->is_enabled( 'pgcache' )
		){
			return true;
		}

		// wp super cache
		if( defined( 'WPCACHEHOME' ) && @ $GLOBALS['cache_enabled'] ){
			return true;
		}

		// WordFence
		if( class_exists( \wfConfig::class ) && \wfConfig::get( 'cacheType' ) === 'falcon' ){
			return true;
		}

		// WP Rocket
		if( class_exists( \HyperCache::class ) ){
			return true;
		}

		// Quick Cache
		if( function_exists( '\quick_cache\plugin' ) && \quick_cache\plugin()->options['enable'] ){
			return true;
		}

		// wp-fastest-cache
		// aio-cache

		return false;
	}

}
