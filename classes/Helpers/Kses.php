<?php

namespace DemocracyPoll\Helpers;

use function DemocracyPoll\plugin;

class Kses {

	// The tags allowed in questions and answers. Will be added to global $allowedtags.
	private static $allowed_tags = [
		'a'      => [ 'href' => true, 'rel' => true, 'name' => true, 'target' => true, ],
		'b'      => [],
		'strong' => [],
		'i'      => [],
		'em'     => [],
		'span'   => [ 'class' => true ],
		'code'   => [],
		'var'    => [],
		'del'    => [ 'datetime' => true, ],
		'img'    => [ 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'align' => true ],
		'h2'     => [],
		'h3'     => [],
		'h4'     => [],
		'h5'     => [],
		'h6'     => [],
	];

	public static function set_allowed_tags() {
		global $allowedtags;

		self::$allowed_tags = array_merge( $allowedtags, array_map( '_wp_add_global_attributes', self::$allowed_tags ) );

		self::$allowed_tags = apply_filters( 'democracy__allowed_tags', self::$allowed_tags );
	}

	/**
	 * wp_kses() value with democracy allowed tags. For esc output strings...
	 */
	public static function kses_html( $value ): string {
		return wp_kses( $value, self::$allowed_tags );
	}

	/**
	 * Очищает данные ответа
	 *
	 * @param string|array $data  Что очистить? Если передана строка, удалить из нее недопустимые HTML теги.
	 *
	 * @return string|array Чистые данные.
	 */
	public static function sanitize_answer_data( $data, $filter_type = '' ) {

		if( is_string( $data ) ){
			$value = trim( $data );
			$data = plugin()->admin_access ? Kses::kses_html( $value ) : wp_kses( $value, 'strip' );
		}
		else{
			foreach( $data as $key => & $val ){

				if( is_string( $val ) ){
					$val = trim( $val );
				}

				// допустимые теги
				if( $key === 'answer' ){
					$val = plugin()->admin_access ? Kses::kses_html( $val ) : wp_kses( $val, 'strip' );
				}
				// числа
				elseif( in_array( $key, [ 'qid', 'aid', 'votes' ] ) ){
					$val = (int) $val;
				}
				// остальное
				else{
					$val = wp_kses( $val, 'strip' );
				}
			}
		}

		return apply_filters( 'dem_sanitize_answer_data', $data, $filter_type );
	}

}
