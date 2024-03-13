<?php

namespace DemocracyPoll;

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/theme-functions.php';

/**
 * PSR-4 compatible autoloader.
 */
spl_autoload_register( static function( $class ) {

	if( str_starts_with( $class, __NAMESPACE__ . '\\' ) ){
		$class = str_replace( __NAMESPACE__ . '\\', '', $class );
		$class = str_replace( '\\', '/', $class );

		require __DIR__ . "/classes/$class.php";
	}
} );

/**
 * We can not use PSR-4 compatible autoloader here because of legacy reason.
 */
spl_autoload_register(
	static function( $class ) {
		if(
			$class !== 'DemPoll'
			&&
			! str_starts_with( $class, 'Democracy_' )
		){
			return;
		}

		$paths = [
			__DIR__ . "/classes/$class.php",
			__DIR__ . "/classes/Admin/$class.php",
			__DIR__ . "/classes/Utils/$class.php",
		];

		foreach( $paths as $index => $path ){
			// include last path without check to get error if file not found.
			if( ! isset( $path[ $index + 1 ] ) ){
				require_once $path;
			}

			if( file_exists( $path ) ){
				require_once $path;
			}
		}
	}
);


