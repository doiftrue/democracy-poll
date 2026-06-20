<?php

namespace DemocracyPoll\Admin;

use DemocracyPoll\Poll_Object;
use DemocracyPoll\Poll_Renderer;
use DemocracyPoll\Poll_Storage;
use function DemocracyPoll\plugin;
use function DemocracyPoll\options;

class Admin_Page_Design implements Admin_Subpage_Interface {

	private Admin_Page $admpage;

	public function __construct( Admin_Page $admin_page ) {
		$this->admpage = $admin_page;
	}

	public function load(): void {
		// Iris Color Picker
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_style( 'wp-color-picker' );

		// CodeMirror
		if( function_exists( 'wp_enqueue_code_editor' ) ){
			add_action( 'admin_enqueue_scripts', static function() {
				$settings = wp_enqueue_code_editor( [ 'type' => 'text/css' ] );

				wp_add_inline_script( 'code-editor', sprintf(
					'addEventListener( "DOMContentLoaded", () => wp.codeEditor.initialize( document.querySelector("textarea[name=additional_css]"), %s ) );', wp_json_encode( $settings )
				) );
			}, 99 );
		}
	}

	public function request_handler(): void {
		if( ! plugin()->super_access || ! Admin_Page::check_nonce() ){
			return;
		}

		$up = null;
		if( isset( $_POST['dem_save_design_options'] ) ){
			$up = options()->update_options( 'design' );
		}
		if( isset( $_POST['dem_reset_design_options'] ) ){
			$up = options()->reset_options( 'design' );
		}

		if( $up !== null ){
			$up
				? plugin()->msg->add_ok( __( 'Updated', 'democracy-poll' ) )
				: plugin()->msg->add_notice( __( 'Nothing was updated', 'democracy-poll' ) );
		}

		// hack to immediately apply the option change
		if( $up ){
			options()->toolbar_menu
				? add_action( 'admin_bar_menu', [ plugin()->initor, 'add_toolbar_node', ], 99 )
				: remove_action( 'admin_bar_menu', [ plugin()->initor, 'add_toolbar_node' ], 99 );
		}
	}

	public function render(): void {
		if( ! plugin()->super_access ){
			return;
		}

		$opt = options();
		$demcss = get_option( 'democracy_css' );
		$additional = $demcss['additional_css'];
		if( ! $demcss['base_css'] && $additional ){
			$demcss['base_css'] = $additional; // Use additional CSS when no theme is selected.
		}

		echo $this->admpage->subpages_menu();

		require __DIR__ . '/tpl/design.php';
	}

	private static function color_picker_html( $args ): void {
		$class = $args['class'] ?? '';
		$name  = $args['name'] ?? '';
		$value = $args['value'] ?? '';
		$title = $args['title'] ?? '';
		?>
		<div style="display: flex; align-items: center; gap: .5rem;">
			<input type="text" class="iris_color <?= esc_attr( $class ) ?>" name="<?= esc_attr( $name ) ?>" value="<?= esc_attr( $value ) ?>">
			<span><?= esc_html( $title ) ?></span>
		</div>
		<?php
	}

	protected function _get_styles_files(): array {
		$arr = [];
		foreach( glob( plugin()->dir . '/assets/styles/*.css' ) as $file ){
			if( preg_match( '~\.min~', basename( $file ) ) ){
				continue;
			}
			$arr[] = $file;
		}

		return $arr;
	}

	public static function polls_preview( bool $show_colorpicker = false ): void {
		?>
		<section class="demoptions__group">
			<?php
			if( $show_colorpicker ){
				self::color_picker_html( [
					'title' => __( 'Bg color', 'democracy-poll' ),
					'class' => 'preview-bg',
				] );
			}
			?>
			<div class="demoptions__block polls-preview">
				<?php
				$poll = new Poll_Object( Poll_Storage::get_db_data( 'rand' ) );
				$render = new Poll_Renderer( $poll );

				if( $poll->id ){
					$answers = wp_list_pluck( $poll->answers, 'aid' );
					$poll->user_state->voted_for = (string) ( $answers ? $answers[ array_rand( $answers ) ] : '' );

					$rm_disabled = static function( $val ) {
						return str_replace( 'disabled="disabled"', '', $val );
					};

					$html = <<<HTML
						<div class="poll"><p class="tit">{RESULTS_TXT}</p>{VOTED_SCREEN}</div>
						<div class="poll"><p class="tit">{VOTE_TXT}</p>{FORCE_VOTE_SCREEN}</div>
						<div class="poll show-loader"><p class="tit">{AJAX_TXT}</p>{VOTE_SCREEN}</div>
						HTML;

					echo strtr( $html, [
						'{RESULTS_TXT}'       => __( 'Results view:', 'democracy-poll' ),
						'{VOTE_TXT}'          => __( 'Vote view:', 'democracy-poll' ),
						'{AJAX_TXT}'          => __( 'AJAX loader view:', 'democracy-poll' ),
						'{VOTED_SCREEN}'      => $rm_disabled( $render->get_screen( 'voted' ) ),
						'{FORCE_VOTE_SCREEN}' => $rm_disabled( $render->get_screen( 'force_vote' ) ),
						'{VOTE_SCREEN}'       => $rm_disabled( $render->get_screen( 'vote' ) ),
					] );
				}
				else{
					echo 'no data or no active polls...';
				}
				?>
			</div>
		</section>
		<?php
	}

}
