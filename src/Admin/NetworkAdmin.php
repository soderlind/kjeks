<?php
/**
 * Network admin review screen.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Admin;

/**
 * Registers the network admin page and mounts the React review app.
 *
 * The aggregated tracker registry (one entry per cookie across all sites) is
 * reviewed here once, with search, filtering, and bulk actions.
 */
final class NetworkAdmin {

	public const SLUG = 'kjeks-network';

	public function hooks(): void {
		add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Capability to administer consent: network-wide on multisite, site admin on single-site.
	 */
	public function capability(): string {
		return is_multisite() ? 'manage_network_options' : 'manage_options';
	}

	public function menu(): void {
		add_menu_page(
			__( 'Kjeks Cookie Consent', 'kjeks' ),
			__( 'Cookie Consent', 'kjeks' ),
			$this->capability(),
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-privacy'
		);
	}

	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'kjeks' ) );
		}

		echo '<div class="wrap"><div id="kjeks-network-root"></div></div>';
	}

	public function enqueue( string $hook ): void {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}

		$asset_file = KJEKS_DIR . 'build/network.asset.php';
		$asset      = is_readable( $asset_file )
			? include $asset_file
			: array(
				'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
				'version'      => KJEKS_VERSION,
			);

		wp_enqueue_script(
			'kjeks-network',
			KJEKS_URL . 'build/network.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_enqueue_style( 'wp-components' );
		if ( is_readable( KJEKS_DIR . 'build/network.css' ) ) {
			wp_enqueue_style( 'kjeks-network', KJEKS_URL . 'build/network.css', array( 'wp-components' ), $asset['version'] );
		}
		wp_set_script_translations( 'kjeks-network', 'kjeks' );

		wp_localize_script(
			'kjeks-network',
			'kjeksNetwork',
			array(
				'restUrl'     => esc_url_raw( rest_url( 'kjeks/v1/network-config' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'isMultisite' => is_multisite(),
			)
		);
	}
}
