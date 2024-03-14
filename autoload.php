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
			$class === \DemPoll::class ||
			$class === \Democracy_Poll::class
		){
			require_once __DIR__ . "/classes/$class.php";
		}
	}
);


