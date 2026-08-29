<?php
/**
 * Add-on kit: network-or-site option storage.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\AddonKit;

/**
 * Reads and writes an add-on option in the right place for the install type:
 * a network (site) option on Multisite, a regular option on single site.
 *
 * Public, versioned API.
 *
 * @since 1.2.0
 */
final class Options {

	/**
	 * @return array<string, mixed>
	 */
	public static function read( string $key ): array {
		$stored = is_multisite() ? get_site_option( $key, array() ) : get_option( $key, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * @param array<string, mixed> $value Values to store.
	 */
	public static function write( string $key, array $value ): void {
		if ( is_multisite() ) {
			update_site_option( $key, $value );
			return;
		}

		update_option( $key, $value );
	}

	public static function delete( string $key ): void {
		delete_option( $key );
		delete_site_option( $key );
	}
}
