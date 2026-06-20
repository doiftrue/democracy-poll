<?php

namespace DemocracyPoll\Admin;

use function DemocracyPoll\plugin;

class Admin_Page_Polls implements Admin_Subpage_Interface {

	public List_Table_Polls $list_table;

	private Admin_Page $admpage;

	public function __construct( Admin_Page $admin_page ){
		$this->admpage = $admin_page;
	}

	public function load(): void {
		$this->list_table = new List_Table_Polls( $this );
	}

	public function request_handler(): void {
		if( ! plugin()->admin_access ){
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
