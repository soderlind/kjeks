<?php
/**
 * Shared-secret authentication for the scanner REST endpoints.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Scan;

use WP_REST_Request;

/**
 * Authenticates the discovery scanner with a shared key instead of a WordPress
 * application password.
 *
 * The scanner presents the key in the `X-Kjeks-Key` request header (preferred)
 * or the `scan_key` query argument (a fallback for proxies that strip custom
 * headers). The expected key is a network option managed with
 * `wp kjeks scan-key`; when no key is stored, key auth is disabled and callers
 * must fall back to a capability check.
 */
final class ScanKeyAuth {

	public const OPTION    = 'kjeks_scan_key';
	public const HEADER    = 'X-Kjeks-Key';
	public const QUERY_ARG = 'scan_key';

	/**
	 * Returns the stored key, or an empty string when none is set.
	 */
	public static function stored_key(): string {
		$key = is_multisite()
			? get_site_option( self::OPTION, '' )
			: get_option( self::OPTION, '' );

		return is_string( $key ) ? $key : '';
	}

	/**
	 * Generates, stores, and returns a new key.
	 */
	public static function generate(): string {
		$key = bin2hex( random_bytes( 32 ) );
		self::store( $key );

		return $key;
	}

	public static function store( string $key ): void {
		if ( is_multisite() ) {
			update_site_option( self::OPTION, $key );
		} else {
			update_option( self::OPTION, $key, false );
		}
	}

	public static function clear(): void {
		if ( is_multisite() ) {
			delete_site_option( self::OPTION );
		} else {
			delete_option( self::OPTION );
		}
	}

	/**
	 * Extracts the presented key from the request: header first, query fallback.
	 */
	public static function presented_key( WP_REST_Request $request ): string {
		$header = $request->get_header( self::HEADER );
		if ( is_string( $header ) && '' !== trim( $header ) ) {
			return trim( $header );
		}

		$query = $request->get_param( self::QUERY_ARG );

		return is_string( $query ) ? trim( $query ) : '';
	}

	/**
	 * True when the request presents a key matching the stored one.
	 *
	 * Uses a timing-safe comparison and denies when no key is configured.
	 */
	public static function is_authorized( WP_REST_Request $request ): bool {
		$stored = self::stored_key();
		if ( '' === $stored ) {
			return false;
		}

		$presented = self::presented_key( $request );
		if ( '' === $presented ) {
			return false;
		}

		return hash_equals( $stored, $presented );
	}
}
