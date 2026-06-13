<?php

namespace DemocracyPoll\Admin;

use DemocracyPoll\Helpers\Kses;
use function DemocracyPoll\plugin;

class Admin_Page_l10n implements Admin_Subpage_Interface {

	private Admin_Page $admpage;

	public function __construct( Admin_Page $admin_page ){
		$this->admpage = $admin_page;
	}

	public function load(): void {
	}

	public function request_handler(): void {
		if( ! plugin()->super_access || ! Admin_Page::check_nonce() ){
			return;
		}

		if( isset( $_POST['dem_save_l10n'] ) || isset( $_POST['dem_reset_l10n'] ) ){
			$up = false;

			// Update custom localization.
			if( isset( $_POST['dem_save_l10n'] ) ){
				$up = $this->update_l10n( stripslashes_deep( $_POST['l10n'] ) );
			}

			// Reset custom localization.
			if( isset( $_POST['dem_reset_l10n'] ) ){
				$up = $this->reset_l10n();
			}

			$up
				? plugin()->msg->add_ok( __( 'Updated', 'democracy-poll' ) )
				: plugin()->msg->add_notice( __( 'Nothing was updated', 'democracy-poll' ) );

		}
	}

	public function render(): void {
		if( ! plugin()->super_access ){
			return;
		}

		echo $this->admpage->subpages_menu();
		?>
		<div class="democr_options dempage_l10n">

			<?php Admin_Page_Design::polls_preview(); ?>

			<form method="POST" action="">
				<?php wp_nonce_field( 'dem_adminform', '_demnonce' ); ?>
				<table class="wp-list-table widefat fixed posts">
					<thead>
						<tr>
							<th><?= __( 'Original', 'democracy-poll' ) ?></th>
							<th><?= __( 'Your variant', 'democracy-poll' ) ?></th>
						</tr>
					</thead>
					<tbody id="the-list">
					<?php
					$i = 0;
					$_l10n = get_option( 'democracy_l10n' );
					self::remove_gettext_filter();
					foreach( self::get_front_texts() as $str ){
						$i++;
						$mo_str = _x( $str, 'front', 'democracy-poll' );

						$l10ed_str = ( ! empty( $_l10n[ $str ] ) && $_l10n[ $str ] !== $mo_str ) ? $_l10n[ $str ] : '';

						?>
						<tr class="<?= ( $i % 2 ? 'alternate' : '' ) ?>">
							<td><?= esc_html( $mo_str ) ?></td>
							<td>
								<input type="text" name="l10n[<?= esc_attr( $str ) ?>]" value="<?= esc_attr( $l10ed_str ) ?>"
								       style="width:100%;"  />
							</td>
						</tr>
						<?php
					}
					self::add_gettext_filter();
					?>
					</tbody>
				</table>

				<p>
					<input class="button-primary" type="submit" name="dem_save_l10n"
					       value="<?= esc_attr__( 'Save Text', 'democracy-poll' ) ?>">
					<input class="button" type="submit" name="dem_reset_l10n"
					       value="<?= esc_attr__( 'Reset Options', 'democracy-poll' ) ?>">
				</p>

			</form>

		</div>
		<?php
	}

	public function reset_l10n(): bool {
		$up = update_option( 'democracy_l10n', [] );
		self::handle_front_l10n( 'clear_cache' );

		return $up;
	}

	/**
	 * Gets front-end strings that can be overridden on this settings page.
	 *
	 * @return string[]
	 */
	public static function get_front_texts(): array {
		$source = file_get_contents( dirname( __DIR__ ) . '/Poll_Renderer.php' );
		if( false === $source ){
			return [];
		}

		preg_match_all( '~_x\(\s*[\'](.*?)(?<!\\\\)[\']~', $source, $matches );

		return array_values( array_unique( $matches[1] ) );
	}

	public function update_l10n( array $new_l10n ): bool {
		self::remove_gettext_filter();

		foreach( $new_l10n as $key => & $val ){
			$val = trim( $val );

			// Delete values that do not differ from the original contextual translation.
			if( _x( $key, 'front', 'democracy-poll' ) === $val ){
				unset( $new_l10n[ $key ] );
			}
			// sanitize value: Thanks to http://pluginvulnerabilities.com/?p=2967
			else{
				$val = Kses::kses_html( $val );
			}
		}
		unset( $val );
		self::add_gettext_filter();

		$up = (bool) update_option( 'democracy_l10n', $new_l10n );

		self::handle_front_l10n( 'clear_cache' );

		return $up;
	}

	/**
	 * For front part localization and custom translation setup.
	 */
	public static function add_gettext_filter(): void {
		add_filter( 'gettext_with_context', [ __CLASS__, 'handle_front_l10n' ], 10, 4 );
	}

	public static function remove_gettext_filter(): void {
		remove_filter( 'gettext_with_context', [ __CLASS__, 'handle_front_l10n' ], 10 );
	}

	public static function handle_front_l10n( $text_translated, $text = '', $context = '', $domain = '' ) {
		static $l10n_opt;
		if( $l10n_opt === null || 'clear_cache' === $text_translated ){
			$l10n_opt = get_option( 'democracy_l10n' );
		}

		if( 'democracy-poll' === $domain && 'front' === $context && ! empty( $l10n_opt[ $text ] ) ){
			return $l10n_opt[ $text ];
		}

		return $text_translated;
	}

}
