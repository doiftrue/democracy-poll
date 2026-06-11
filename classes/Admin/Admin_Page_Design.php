<?php

namespace DemocracyPoll\Admin;

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
			$demcss['base_css'] = $additional; // если не используется тема
		}

		echo $this->admpage->subpages_menu();
		?>
		<div class="democr_options dempage_design">
			<?php self::polls_preview(); ?>

			<form action="" method="post">
				<?php wp_nonce_field( 'dem_adminform', '_demnonce' ); ?>

				<ul class="group">
					<li class="title"><?= esc_html__( 'Choose Theme', 'democracy-poll' ); ?></li>
					<li class="block selectable_els">
						<label>
							<input type="radio" name="dem[css_file_name]"
							       value="" <?php checked( $opt->css_file_name, '' ) ?> />
							<span class="radio_content"><?= esc_html__( 'No theme', 'democracy-poll' ) ?></span>
						</label>
						<?php
						foreach( $this->_get_styles_files() as $file ){
							$filename = basename( $file );
							?>
							<label>
								<input type="radio" name="dem[css_file_name]"
								       value="<?= $filename ?>" <?php checked( $opt->css_file_name, $filename ) ?> />
								<span class="radio_content"><?= $filename ?></span>
							</label>
							<?php
						}
						?>
					</li>
				</ul>

				<!-- Other settings -->
				<ul class="group">
					<li class="title"><?= esc_html__( 'Other settings', 'democracy-poll' ); ?></li>
					<li class="block">
						<input type="text" min="-1" style="width:7em;" name="dem[answs_max_height]" value="<?= esc_attr( $opt->answs_max_height ) ?>">
						<?= esc_html__( 'Max height of the poll in pixels (if no unit is set). When a poll has many answers, collapsing it improves readability. `-1` - disables this option. Default: 500.', 'democracy-poll' ) ?>
					</li>
					<li class="block">
						<input type="number" min="0" style="width:7em;" name="dem[anim_speed]" value="<?= esc_attr( $opt->anim_speed ) ?>">
						<?= esc_html__( 'Animation speed (in milliseconds).', 'democracy-poll' ) ?>
					</li>

				</ul>

				<!--Progress line-->
				<ul class="group">
					<li class="title"><?= esc_html__( 'Progress line', 'democracy-poll' ); ?></li>
					<li class="block">
						<p><?= esc_html__( 'How to fill (paint) the progress of each answer:', 'democracy-poll' ) ?></p>
						<label style="margin-left:1em;">
							<input type="radio" name="dem[graph_from_total]"
							       value="0" <?php checked( $opt->graph_from_total, 0 ) ?> />
							<?= esc_html__( 'winner - 100%, others as % of the winner', 'democracy-poll' ) ?>
						</label>
						<br>
						<label style="margin-left:1em;">
							<input type="radio" name="dem[graph_from_total]"
							       value="1" <?php checked( $opt->graph_from_total, 1 ) ?> />
							<?= esc_html__( 'as percent of all votes', 'democracy-poll' ) ?>
						</label>

						<br><br>

						<?php
						self::color_picker_html( [
							'title' => __( 'Line Color', 'democracy-poll' ),
							'name'  => 'dem[line_fill]',
							'value' => $opt->line_fill
						] );

						self::color_picker_html( [
							'title' => __( 'Line color (for voted user)', 'democracy-poll' ),
							'name'  => 'dem[line_fill_voted]',
							'value' => $opt->line_fill_voted
						] );

						self::color_picker_html( [
							'title' => __( 'Background color', 'democracy-poll' ),
							'name'  => 'dem[line_bg]',
							'value' => $opt->line_bg
						] );
						?>

						<br>

						<label>
							<input type="text" style="width:90px" name="dem[line_height]" value="<?= $opt->line_height ?>"/>
							<?= esc_html__( 'Line height (in px if unit not set)', 'democracy-poll' ) ?>
						</label>
						<br><br>

						<label>
							<input type="number" style="width:90px" name="dem[line_anim_speed]"
							       value="<?= (int) $opt->line_anim_speed ?>"/>
							<?= esc_html__( 'Progress line animation effect speed (default 1500). Set 0 to disable animation.', 'democracy-poll' ) ?>
						</label>

					</li>
				</ul>

				<!-- checkbox, radio -->
				<ul class="group">
					<li class="title">checkbox, radio</li>
					<li class="block check_radio_wrap selectable_els">
						<div style="float:left;">
							<label style="padding:0em 3em 1em;">
								<input type="radio" value=""
								       name="dem[checkradio_fname]" <?php checked( $opt->checkradio_fname, '' ) ?>>
								<span class="radio_content">
								<div style="padding:1.25em;"></div>
								<?= esc_html__( 'No (default)', 'democracy-poll' ); ?>
							</span>
							</label>
						</div>
						<?php
						$data = [];
						foreach( glob( plugin()->dir . '/assets/styles/checkbox-radio/*' ) as $file ){
							if( is_dir( $file ) ){
								continue;
							}
							$data[ basename( $file ) ] = $file;
						}
						foreach( $data as $fname => $file ){
							$styles = file_get_contents( $file );

							// поправим стили
							$unique = 'unique' . rand( 1, 9999 ) . '_';
							$styles = str_replace( '.dem__radio_label', ".{$unique}dem__radio_label", $styles );
							$styles = str_replace( '.dem__checkbox_label', ".{$unique}dem__checkbox_label", $styles );
							$styles = str_replace( '.dem__radio', ".{$unique}dem__radio", $styles );
							$styles = str_replace( '.dem__checkbox', ".{$unique}dem__checkbox", $styles );
							$styles = str_replace( ':disabled', ':disabled__', $styles ); // отменим действие :disabled

							?>
							<div style="float:left;">
								<style><?= $styles ?></style>
								<label style="padding:0 3em 1em;">
									<input type="radio" value="<?= $fname ?>" name="dem[checkradio_fname]" <?= checked( $opt->checkradio_fname, $fname, 0 ) ?>>
									<span class="radio_content">
										<div style="padding:.5em;">
											<label class="<?= $unique ?>dem__radio_label">
												<input disabled class="<?= $unique ?>dem__radio demdummy" type="radio" /><span class="dem__spot"></span>
											</label>
											<label class="<?= $unique ?>dem__radio_label">
												<input disabled class="<?= $unique ?>dem__radio demdummy" checked type="radio" /><span class="dem__spot"></span>
											</label>
											<label class="<?= $unique ?>dem__checkbox_label">
												<input disabled class="<?= $unique ?>dem__checkbox demdummy" type="checkbox" /><span class="dem__spot"></span>
											</label>
											<label class="<?= $unique ?>dem__checkbox_label demdummy">
												<input disabled class="<?= $unique ?>dem__checkbox" checked type="checkbox" /><span class="dem__spot"></span>
											</label>
										</div>

										<?= $fname ?>
									<span>
								</label>

							</div>
							<?php
						}
						?>
					</li>
				</ul>


				<!--Button-->
				<div class="group">
					<div class="title">Button</div>
					<div class="block buttons">
						<div class="btn_select_wrap selectable_els">
							<label>
								<input type="radio" value="" name="dem[css_button]" <?php checked( $opt->css_button, '' ) ?> />
								<span class="radio_content">
									<input type="button" value="<?= esc_attr__( 'No (default)', 'democracy-poll' ); ?>"/>
								</span>
							</label>

							<?php
							$i = 0;
							foreach( glob( plugin()->dir . '/assets/styles/buttons/*' ) as $file ){
								if( is_dir( $file ) ){
									continue;
								}

								$fname = basename( $file );
								$button_class = 'dem-button' . ++$i;
								$css = ".$button_class{ position: relative; display:inline-block; text-decoration:none; user-select: none; outline:none; line-height:1; border:0; }\n"; // reset
								$css .= str_replace( '.dem-button', ".$button_class", file_get_contents( $file ) );

								if( $opt->css_button ){
									$bbg     = $opt->btn_bg_color;
									$bcolor  = $opt->btn_color;
									$bbcolor = $opt->btn_border_color;
									// hover
									$bh_bg     = $opt->btn_hov_bg;
									$bh_color  = $opt->btn_hov_color;
									$bh_bcolor = $opt->btn_hov_border_color;

									$button_vars = array_filter( [
										$bbg       ? "--dem-button-bg: $bbg"                       : '',
										$bcolor    ? "--dem-button-color: $bcolor"                 : '',
										$bbcolor   ? "--dem-button-border-color: $bbcolor"         : '',
										$bh_bg     ? "--dem-button-hover-bg: $bh_bg"               : '',
										$bh_color  ? "--dem-button-hover-color: $bh_color"         : '',
										$bh_bcolor ? "--dem-button-hover-border-color: $bh_bcolor" : '',
									] );

									if( $button_vars ){
										$css .= "\n.$button_class{ " . implode( "; ", $button_vars ) . "; }\n";
									}
								}
								?>
								<style><?= $css ?></style>

								<label>
									<input type="radio" value="<?= esc_attr( $fname ) ?>"
									       name="dem[css_button]" <?php checked( $opt->css_button, $fname ) ?> />
									<span class="radio_content">
										<input type="button" value="<?= esc_attr( $fname ) ?>"
										       class="<?= $button_class ?>">
									</span>
								</label>
								<?php
							}
							?>
							<br><br>
							<em>
								<?= esc_html__( 'The colors correctly affects NOT for all buttons. You can change styles completely in "additional styles" field bellow.', 'democracy-poll' ) ?>
							</em>
						</div>

						<div style="display:flex; gap:1rem;">
							<div style="flex-basis: 33%">
								<p><?= esc_html__( 'Button colors', 'democracy-poll' ) ?></p>
								<?php
								self::color_picker_html( [
									'title' => __( 'Bg color', 'democracy-poll' ),
									'name'  => 'dem[btn_bg_color]',
									'value' => $opt->btn_bg_color
								] );

								self::color_picker_html( [
									'title' => __( 'Text Color', 'democracy-poll' ),
									'name'  => 'dem[btn_color]',
									'value' => $opt->btn_color
								] );

								self::color_picker_html( [
									'title' => __( 'Border Color', 'democracy-poll' ),
									'name'  => 'dem[btn_border_color]',
									'value' => $opt->btn_border_color
								] );
								?>
							</div>
							<div style="flex-basis: 33%">
								<p><?= esc_html__( 'Hover button colors', 'democracy-poll' ) ?></p>
								<?php
								self::color_picker_html( [
									'title' => __( 'Bg color', 'democracy-poll' ),
									'name'  => 'dem[btn_hov_bg]',
									'value' => $opt->btn_hov_bg
								] );

								self::color_picker_html( [
									'title' => __( 'Text Color', 'democracy-poll' ),
									'name'  => 'dem[btn_hov_color]',
									'value' => $opt->btn_hov_color
								] );

								self::color_picker_html( [
									'title' => __( 'Border Color', 'democracy-poll' ),
									'name'  => 'dem[btn_hov_border_color]',
									'value' => $opt->btn_hov_border_color
								] );
								?>
							</div>
							<div style="flex-basis: 33%">
								<!--<hr>-->
								<label style="margin-top:3em;">
									<input type="text" name="dem[btn_class]" value="<?= $opt->btn_class ?>">
									<em><?= esc_html__( 'An additional css class for all buttons in the poll. When the template has a special class for buttons, for example:', 'democracy-poll' ) ?> <code>btn btn-info</code></em>
								</label>
							</div>

						</div><!--flex-->

					</div>

				</div>


				<!-- AJAX loader -->
				<div class="group">
					<div class="title">Loader</div>
					<div class="block loaders" style="text-align:center;">
						<div class="selectable_els">
							<label class="lo_item" style="display: block; height:30px;">
								<input type="radio" value="" name="dem[loader_fname]" <?php checked( $opt->loader_fname, '' ) ?>>
								<span class="radio_content"><?= esc_html__( 'No (dots...)', 'democracy-poll' ); ?></span>
							</label>
							<br>
							<?php
							$data = [];
							foreach( glob( plugin()->dir . '/assets/styles/loaders/*' ) as $file ){
								if( is_dir( $file ) ){
									continue;
								}
								$fname = basename( $file );
								$ex = preg_replace( '~.*\.~', '', $fname );
								$data[ $ex ][ $fname ] = $file;
							}
							foreach( $data as $ex => $val ){
								echo '<div class="clearfix"></div>' . "<h2 style='text-align:center;'>$ex</h2>"; //'';

								// поправим стили
								if( $opt->loader_fill ){
									$loader_css = ".loader{ --dem-loader-color: " . $opt->loader_fill . "; }";
									echo "<style>$loader_css</style>";
								}

								foreach( $val as $fname => $file ){
									?>
									<label class="lo_item <?= $ex ?>">
										<input type="radio" value="<?= $fname ?>"
										       name="dem[loader_fname]" <?php checked( $opt->loader_fname, $fname ) ?>>
										<span class="radio_content">
											<div class="loader"><?= file_get_contents( $file ) ?></div>
											<?php //echo $ex
											?>
										</span>
									</label>
									<?php
								}
							}
							?>

						</div>

						<em>
							<?= esc_html__( 'AJAX Loader. If choose "NO", loader replaces by dots "..." which appends to a link/button text. SVG images animation don\'t work in IE 11 or lower, other browsers are supported at  90% (according to caniuse.com statistics).', 'democracy-poll' ) ?>
						</em>

						<input class="iris_color fill" name="dem[loader_fill]" type="text" value="<?= $opt->loader_fill ?>">

					</div>

				</div>

				<!-- Custom styles -->
				<ul class="group">
					<li class="title"><?= esc_html__( 'Custom/Additional CSS styles', 'democracy-poll' ) ?></li>

					<li class="block" style="width:98%;">
						<p>
							<i><?php
								echo esc_html__( 'In this field you can add some additional css properties or completely replace current css theme. Write here css and it will be added at the bottom of current Democracy css. To complete replace styles, check "No theme" and describe all styles.', 'democracy-poll' );
								echo esc_html__( 'This field cleaned manually, if you reset options of this page or change/set another theme, the field will not be touched.', 'democracy-poll' );
								?></i>
						</p>
						<textarea name="additional_css" style="width:100%; min-height:50px; height:<?= $additional ? '300px' : '50px' ?>;"><?= esc_textarea( $additional ) ?></textarea>
					</li>
				</ul>

				<!-- Connected styles -->
				<p style="margin:2em 0; margin-top:5em; position:fixed; bottom:0; z-index:99;">
					<input type="submit" name="dem_save_design_options" class="button-primary"
					       value="<?= esc_attr__( 'Save All Changes', 'democracy-poll' ) ?>"
					>

					<input type="submit" name="dem_reset_design_options" class="button"
					       value="<?= esc_attr__( 'Reset Options', 'democracy-poll' ) ?>"
					       onclick="return confirm('<?= esc_attr__( 'are you sure?', 'democracy-poll' ) ?>');"
					       style="margin-left:4em;"
					>
				</p>

				<ul class="group">
					<li class="title"><?= esc_html__( 'All CSS styles that uses now', 'democracy-poll' ) ?></li>
					<li class="block">
						<script>
							function select_kdfgu( that ){
								var sel = ( !! document.getSelection) ? document.getSelection() : ( !! window.getSelection) ? window.getSelection() : document.selection.createRange().text;
								if( sel == '' ) that.select();
							}
						</script>
						<em style="__opacity: 0.8;">
							<?= esc_html__( 'It\'s all collected css styles: theme, button, options. You can copy this styles to the "Custom/Additional CSS styles:" field, disable theme and change copied styles by itself.', 'democracy-poll' ) ?>
						</em>
						<textarea onmouseup="select_kdfgu(this);" onfocus="this.style.height = '700px';"
						          onblur="this.style.height = '100px';" readonly="true"
						          style="width:100%;min-height:100px;"><?php
							echo $demcss['base_css'] . "\n\n\n/* custom styles ------------------------------ */\n" . $demcss['additional_css'];
							?></textarea>

						<p>
							<?= esc_html__( 'Minified version (uses to include it in HTML)', 'democracy-poll' ) ?>
							<button type="button" onclick="const el = this.parentElement.nextElementSibling; el.hidden = ! el.hidden">show</button>
						</p>
						<pre hidden style="width:auto; white-space:pre-wrap; overflow-wrap:anywhere; word-break:break-word; padding: 1em; background:rgba(0 0 0 / .1)"><?= $demcss['minify'] ?></pre>
					</li>
				</ul>

			</form>

		</div>
		<?php
	}

	private static function color_picker_html( $args ): void {
		?>
		<div style="display: flex; align-items: center; gap: .5rem;">
			<input type="text" class="iris_color" name="<?= esc_attr( $args['name'] ) ?>" value="<?= esc_attr( $args['value'] ) ?>">
			<span><?= esc_html__( $args['title'] ) ?></span>
		</div>
		<?php
	}

	/**
	 * Получает существующие полные css файлы из каталога плагина.
	 *
	 * @return array Возвращает массив имен (путей) к файлам.
	 */
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

	public static function polls_preview(): void {
		?>
		<ul class="group">
			<li class="block polls-preview">
				<?php
				$poll = new \DemPoll( \DemPoll::get_db_data( 'rand' ) );
				$render = $poll->renderer;

				if( $poll->id ){
					$answers = wp_list_pluck( $poll->answers, 'aid' );
					$poll->voted_for = (string) ( $answers ? $answers[ array_rand( $answers ) ] : '' );

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

				if( ( $_GET['subpage'] ?? '' ) === 'design' ){
					echo '<input type="text" class="iris_color preview-bg">';
				}
				?>
			</li>
		</ul>
		<?php
	}

}
