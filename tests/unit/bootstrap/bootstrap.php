<?php

define( 'WP_ROOT_DIR', dirname( __DIR__, 6 ) ); // WP directory
const WP_ROOT_URL = 'http://test.loc';

// WP constants
const ABSPATH         = WP_ROOT_DIR . '/core/';
const WP_CONTENT_DIR  = WP_ROOT_DIR . '/wp-content';
const WP_CONTENT_URL  = WP_ROOT_URL . '/wp-content';
const WP_PLUGIN_DIR   = WP_CONTENT_DIR . '/mu-plugins';
const WPMU_PLUGIN_DIR = WP_CONTENT_DIR . '/plugins';

define( 'THIS_PLUG_ROOT_DIR', dirname( __DIR__, 3 ) );
define( 'THIS_PLUG_ROOT_URL', str_replace( WP_ROOT_DIR, WP_ROOT_URL, THIS_PLUG_ROOT_DIR ) );

// load

require_once THIS_PLUG_ROOT_DIR . '/vendor/autoload.php';
require_once dirname( WP_ROOT_DIR ) . '/tests/phpunit-wp-copy/zero.php'; // TODO use composer package
require_once __DIR__ . '/DemocTestCase.php';

// global setup

putenv( 'WP_ENVIRONMENT_TYPE=local' );
$GLOBALS['timestart'] = microtime( true );

// init bootstrap

WP_Mock::bootstrap();

// run plugin

require_once THIS_PLUG_ROOT_DIR . '/autoload.php';




