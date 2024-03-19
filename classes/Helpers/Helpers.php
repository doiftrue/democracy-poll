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

}
