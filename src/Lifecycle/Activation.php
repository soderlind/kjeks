<?php
/**
 * Activation and deactivation.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Lifecycle;

/**
 * Handles (network) activation and deactivation.
 *
 * New sites inherit network definitions at read-time, so activation does no
 * expensive per-site copying. Deactivation never deletes data.
 */
final class Activation {

	/**
	 * @param bool $network_wide Whether the plugin was network-activated.
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			// Seed only the network defaults; sites inherit them lazily.
			if ( false === get_site_option( 'kjeks_network_settings', false ) ) {
				update_site_option( 'kjeks_network_settings', array( 'delete_on_uninstall' => false ) );
			}
		}

		SiteInitializer::ensure_site_defaults( get_current_blog_id() );
	}

	public static function deactivate(): void {
		// Intentionally does nothing destructive.
	}
}
