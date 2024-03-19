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

}
