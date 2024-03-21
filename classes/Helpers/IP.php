<?php

namespace DemocracyPoll\Helpers;

use function DemocracyPoll\options;

class IP {

	public static function get_user_ip(): string {

		if( options()->soft_ip_detect ){
			$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''; // cloudflare

			filter_var( $ip, FILTER_VALIDATE_IP ) || ( $ip = $_SERVER['HTTP_CLIENT_IP'] ?? '' );
			filter_var( $ip, FILTER_VALIDATE_IP ) || ( $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '' );
			filter_var( $ip, FILTER_VALIDATE_IP ) || ( $ip = $_SERVER['REMOTE_ADDR'] ?? '' );
		}
		else{
			$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		}

		$ip = apply_filters( 'dem_get_ip', $ip );

		if( ! filter_var( $ip, FILTER_VALIDATE_IP ) ){
			/** @noinspection NonSecureUniqidUsageInspection */
			$ip = 'no_IP__' . uniqid();
		}

		return $ip;
	}

	/**
	 * Получает строку: Формат ip_info для таблицы логов.
	 *
	 * @param array|string $ip_info  IP или уже полученные данные IP в массиве.
	 *
	 * @return string Формат: 'название_страны,код_страны,город' или 'текущее UNIX метка'.
	 */
	public static function prepared_ip_info( $ip_info ) {

		// если передан IP
		if( filter_var( $ip_info, FILTER_VALIDATE_IP ) ){

			$parts = array_map( 'intval', explode( '.', $ip_info ) );
			$is_localhost = (
				127 === $parts[0] || 10 === $parts[0] || 0 === $parts[0]
				|| ( 172 === $parts[0] && 16 <= $parts[1] && 31 >= $parts[1] )
				|| ( 192 === $parts[0] && 168 === $parts[1] )
			);

			if( $is_localhost ){
				return time() + YEAR_IN_SECONDS * 10;
			}

			$ip_info = self::get_ip_info( (string) $ip_info );
		}

		/**
		  [city] =>
		  [state] =>
		  [country] => Uzbekistan
		  [country_code] => UZ
		  [continent] => Asia
		  [continent_code] => AS
		*/
		if( isset( $ip_info['country'] ) ){
			return $ip_info['country'] . ',' . $ip_info['country_code'] . ',' . $ip_info['city'];
		}

		return time();
	}

	/**
	 * Получает данные локации переданного IP.
	 *
	 * @param string $ip       IP для проверки. По умолчанию текущий IP.
	 * @param string $purpose  Какие данные нужно получить. Может быть: location address city state region country countrycode.
	 *
	 * @return array Данные в виде массива.
	 */
	public static function get_ip_info( string $ip = '' ): array {

		if( ! $ip ){
			$ip = self::get_user_ip();
		}

		if( ! filter_var( $ip, FILTER_VALIDATE_IP ) ){
			return [];
		}

		//$ipdat = json_decode( wp_remote_retrieve_body( wp_remote_get("http://www.geoplugin.net/json.gp?ip=$ip") ) ); // wp_remote_get отдает forbiden 403 !!!
		$json = @ file_get_contents( "http://www.geoplugin.net/json.gp?ip=$ip" );
		$ipdat = json_decode( $json );
		if( ! $ipdat ){
			return [];
		}

		$continent_code = trim( $ipdat->geoplugin_continentCode ?? '' );
		$country_code   = trim( $ipdat->geoplugin_countryCode ?? '' );
		$country_name   = trim( $ipdat->geoplugin_countryName ?? '' );
		$region_name    = trim( $ipdat->geoplugin_regionName ?? '' );
		$city           = trim( $ipdat->geoplugin_city ?? '' );

		if( strlen( $country_code ) !== 2 ){
			return [];
		}

		$continent = [
			'AF' => 'Africa',
			'AN' => 'Antarctica',
			'AS' => 'Asia',
			'EU' => 'Europe',
			'OC' => 'Australia (Oceania)',
			'NA' => 'North America',
			'SA' => 'South Americ',
		][ strtoupper( $continent_code ) ] ?? '';

		return [
			'city'           => $city,
			'state'          => $region_name,
			'country'        => $country_name,
			'country_code'   => $country_code,
			'continent'      => $continent,
			'continent_code' => $continent_code,
		];

	}

}
