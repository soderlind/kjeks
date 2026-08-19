<?php
/**
 * Uninstall behavior.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Lifecycle;

/**
 * Deletes Kjeks data only when the network opt-in is enabled.
 *
 * Default is to preserve all data. The opt-in lives in the network settings
 * (`kjeks_network_settings['delete_on_uninstall']`).
 */
final class Uninstall {

	private const SITE_OPTIONS = array(
		'kjeks_policy_version',
		'kjeks_site_overrides',
		'kjeks_site_trackers',
		'kjeks_site_content',
	);

	private const NETWORK_OPTIONS = array(
		'kjeks_network_trackers',
		'kjeks_network_content',
		'kjeks_network_settings',
	);

	public static function run(): void {
		if ( is_multisite() ) {
			$settings = get_site_option( 'kjeks_network_settings', array() );
			$opt_in   = is_array( $settings ) && ! empty( $settings['delete_on_uninstall'] );

			if ( ! $opt_in ) {
				return;
			}

			foreach ( get_sites(
				array(
					'number' => 0,
					'fields' => 'ids',
				)
			) as $blog_id ) {
				switch_to_blog( (int) $blog_id );
				self::delete_site_options();
				restore_current_blog();
			}

			foreach ( self::NETWORK_OPTIONS as $option ) {
				delete_site_option( $option );
			}

			return;
		}

		// Single-site fallback: honor a same-named local flag.
		$settings = get_option( 'kjeks_network_settings', array() );
		if ( is_array( $settings ) && ! empty( $settings['delete_on_uninstall'] ) ) {
			self::delete_site_options();
			foreach ( self::NETWORK_OPTIONS as $option ) {
				delete_option( $option );
			}
		}
	}

	private static function delete_site_options(): void {
		foreach ( self::SITE_OPTIONS as $option ) {
			delete_option( $option );
		}
	}
}
