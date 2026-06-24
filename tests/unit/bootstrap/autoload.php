<?php

namespace DemocracyPoll;

/**
 * PSR-4 compatible autoloader.
 */
spl_autoload_register( static function( $class ) {
	if( str_starts_with( $class, __NAMESPACE__ . '\\' ) ){
		$folder = dirname( __DIR__ );
		$path = str_replace( [ __NAMESPACE__, '\\' ], [ $folder, '/' ], $class );

		if( file_exists( "$path.php" ) ){
			require "$path.php";
		}
	}
} );
