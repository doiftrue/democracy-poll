<?php

namespace DemocracyPoll\Admin;

use DemocracyPoll\Plugin;

class Admin_Page_Polls implements Admin_Subpage_Interface {

	public List_Table_Polls $list_table;

	private Admin_Page $admpage;
	private Plugin $plugin;

	public function __construct( Plugin $plugin, Admin_Page $admin_page ){
		$this->admpage = $admin_page;
		$this->plugin = $plugin;
	}

	public function load(): void {
		$this->list_table = new List_Table_Polls( $this );
	}

	public function request_handler(): void {
		if( ! $this->plugin->admin_access ){
			return;
		}
	}

	public function render(): void {
		echo $this->admpage->subpages_menu();
		?>
		<div class="demoptions dempage-polls">
			<?php
			$this->list_table->search_box( __( 'Search', 'democracy-poll' ), 'style="margin:1em 0 -1em;"' );
			$this->list_table->display();
			?>
		</div>
		<?php
	}

}
