<?php
/**
 * Per-site admin settings screen.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Admin;

/**
 * Registers the per-site settings page and mounts the React admin app.
 */
final class SiteSettings {

	public const SLUG = 'kjeks-settings';

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function menu(): void {
		add_options_page(
			__( 'Kjeks Cookie Consent', 'kjeks' ),
			__( 'Cookie Consent', 'kjeks' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		echo '<div class="wrap"><div id="kjeks-admin-root"></div></div>';
	}

	public function enqueue( string $hook ): void {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}

		$asset_file = KJEKS_DIR . 'build/admin.asset.php';
		$asset      = is_readable( $asset_file )
			? include $asset_file
			: array(
				'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
				'version'      => KJEKS_VERSION,
			);

		wp_enqueue_script(
			'kjeks-admin',
			KJEKS_URL . 'build/admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_enqueue_style( 'wp-components' );
		if ( is_readable( KJEKS_DIR . 'build/admin.css' ) ) {
			wp_enqueue_style( 'kjeks-admin', KJEKS_URL . 'build/admin.css', array( 'wp-components' ), $asset['version'] );
		}
		wp_set_script_translations( 'kjeks-admin', 'kjeks' );

		wp_localize_script(
			'kjeks-admin',
			'kjeksAdmin',
			array(
				'restUrl' => esc_url_raw( rest_url( 'kjeks/v1/site-config' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}
