<?php

namespace DemocracyPoll;

use WP_Mock;

class Uninstall_Wpdb__Double {

	public string $prefix = 'wp_';
	public array $queries = [];

	public function query( string $query ): void {
		$this->queries[] = $query;
	}

}

class Uninstall__Test extends DemocTestCase {

	/**
	 * @coversNothing
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__uninstall_removes_plugin_data_from_a_single_site(): void {
		$wpdb = $this->set_up_uninstall( false );

		$this->load_uninstall_file();

		$this->assertSame( $this->drop_table_queries(), $wpdb->queries );
	}

	/**
	 * @coversNothing
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__uninstall_removes_plugin_data_from_each_network_site(): void {
		$wpdb = $this->set_up_uninstall( true );
		$sites = [ (object) [ 'blog_id' => 2 ], (object) [ 'blog_id' => 3 ] ];

		WP_Mock::userFunction( 'get_sites' )->andReturn( $sites );
		WP_Mock::userFunction( 'switch_to_blog' )->with( 2 )->once();
		WP_Mock::userFunction( 'switch_to_blog' )->with( 3 )->once();
		WP_Mock::userFunction( 'restore_current_blog' )->twice();

		$this->load_uninstall_file();

		$this->assertSame( array_merge( $this->drop_table_queries(), $this->drop_table_queries() ), $wpdb->queries );
	}

	private function set_up_uninstall( bool $is_multisite ): Uninstall_Wpdb__Double {
		$wpdb = new Uninstall_Wpdb__Double();
		$GLOBALS['wpdb'] = $wpdb;

		WP_Mock::userFunction( 'is_multisite' )->andReturn( $is_multisite );
		foreach( [
			'widget_democracy',
			'democracy_options',
			'democracy_version',
			'democracy_css',
			'democracy_l10n',
			'democracy_migrated',
		] as $option ){
			WP_Mock::userFunction( 'delete_option' )->with( $option );
		}
		WP_Mock::userFunction( 'delete_transient' )->with( 'democracy_referer' );

		return $wpdb;
	}

	private function load_uninstall_file(): void {
		define( 'WP_UNINSTALL_PLUGIN', true );
		require THIS_PLUG_ROOT_DIR . '/uninstall.php';
	}

	private function drop_table_queries(): array {
		return [
			'DROP TABLE IF EXISTS wp_democracy_q',
			'DROP TABLE IF EXISTS wp_democracy_a',
			'DROP TABLE IF EXISTS wp_democracy_log',
		];
	}

}
