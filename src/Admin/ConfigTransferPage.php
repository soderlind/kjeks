<?php
/**
 * Admin import / export screen for config bundles.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Admin;

use RuntimeException;
use Soderlind\Kjeks\Config\ConfigBundle;

/**
 * A submenu under Cookie Consent to download and upload Config bundles.
 *
 * Export streams a JSON attachment; import parses an uploaded bundle and
 * applies it. Both require the same capability as the review screen.
 */
final class ConfigTransferPage {

	public const SLUG = 'kjeks-config-transfer';

	private const EXPORT_ACTION = 'kjeks_export_config';
	private const IMPORT_ACTION = 'kjeks_import_config';

	public function hooks(): void {
		add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', array( $this, 'menu' ), 12 );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( $this, 'handle_export' ) );
		add_action( 'admin_post_' . self::IMPORT_ACTION, array( $this, 'handle_import' ) );
	}

	/**
	 * Capability to administer consent: network-wide on multisite, site admin on single-site.
	 */
	private function capability(): string {
		return is_multisite() ? 'manage_network_options' : 'manage_options';
	}

	public function menu(): void {
		add_submenu_page(
			NetworkAdmin::SLUG,
			__( 'Import / Export', 'kjeks' ),
			__( 'Import / Export', 'kjeks' ),
			$this->capability(),
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'kjeks' ) );
		}

		$action_url = is_multisite() ? network_admin_url( 'admin-post.php' ) : admin_url( 'admin-post.php' );
		// Notice only, no state change; nonce not required for a read-only banner.
		$notice = isset( $_GET['kjeks_notice'] ) ? sanitize_key( wp_unslash( $_GET['kjeks_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice flag after a redirect; sanitized, no state change, nonce not applicable.

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Import / Export', 'kjeks' ) . '</h1>';
		$this->notice( $notice );

		echo '<h2>' . esc_html__( 'Export', 'kjeks' ) . '</h2>';
		echo '<p>' . esc_html__( 'Download this install\'s settings as a portable Config bundle. Scan discoveries, secrets, and visitor consent are never included.', 'kjeks' ) . '</p>';
		echo '<form method="post" action="' . esc_url( $action_url ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::EXPORT_ACTION ) . '" />';
		wp_nonce_field( self::EXPORT_ACTION );
		submit_button( __( 'Download config bundle', 'kjeks' ), 'primary', 'submit', false );
		echo '</form>';

		echo '<hr />';

		echo '<h2>' . esc_html__( 'Import', 'kjeks' ) . '</h2>';
		echo '<p>' . esc_html__( 'Apply a Config bundle to this install. Settings are replaced; reviewed trackers are merged, keeping local review work. Applying re-prompts visitors.', 'kjeks' ) . '</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( $action_url ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::IMPORT_ACTION ) . '" />';
		wp_nonce_field( self::IMPORT_ACTION );
		echo '<input type="file" name="kjeks_bundle" accept="application/json,.json" required /> ';
		submit_button( __( 'Apply config bundle', 'kjeks' ), 'secondary', 'submit', false );
		echo '</form>';
		echo '</div>';
	}

	public function handle_export(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'kjeks' ) );
		}
		check_admin_referer( self::EXPORT_ACTION );

		$bundle   = ( new ConfigBundle() )->export();
		$json     = (string) wp_json_encode( $bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		$filename = 'kjeks-config-' . gmdate( 'Ymd-His' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $json ) );

		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON attachment payload, not HTML.
		exit;
	}

	public function handle_import(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'kjeks' ) );
		}
		check_admin_referer( self::IMPORT_ACTION );

		$tmp = isset( $_FILES['kjeks_bundle']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['kjeks_bundle']['tmp_name'] ) ) : '';
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			$this->redirect( 'nofile' );
		}

		// Reading a just-uploaded temp file after nonce + capability checks.
		$raw     = (string) file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Verified upload temp path; not remote, WP_Filesystem not warranted for a read.
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			$this->redirect( 'invalid' );
		}

		try {
			( new ConfigBundle() )->apply( $decoded );
		} catch ( RuntimeException $e ) {
			$this->redirect( 'schema' );
		}

		$this->redirect( 'applied' );
	}

	private function notice( string $notice ): void {
		$messages = array(
			'applied' => array( 'success', __( 'Config bundle applied. Visitors will be re-prompted.', 'kjeks' ) ),
			'nofile'  => array( 'error', __( 'No file was uploaded.', 'kjeks' ) ),
			'invalid' => array( 'error', __( 'The uploaded file is not a valid Config bundle.', 'kjeks' ) ),
			'schema'  => array( 'error', __( 'The bundle uses an unsupported schema version.', 'kjeks' ) ),
		);

		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $notice ][0] ),
			esc_html( $messages[ $notice ][1] )
		);
	}

	private function redirect( string $notice ): void {
		$base = is_multisite() ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => self::SLUG,
					'kjeks_notice' => $notice,
				),
				$base
			)
		);
		exit;
	}
}
