<?php
/**
 * Restricted Site Access compatibility.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Integration;

use Soderlind\Kjeks\Scan\ScanKeyAuth;

/**
 * Lets the discovery scanner reach a site protected by the Restricted Site
 * Access plugin.
 *
 * RSA redirects unauthenticated visitors (including REST API requests) to the
 * login screen on `parse_request`, before Kjeks' own REST auth runs. This
 * bypasses that redirect for any request that carries a valid scanner key, so
 * the scanner can both fetch its config/import results and load the front-end
 * pages it scans. Requests without a valid key stay restricted, and the REST
 * endpoints still validate the key themselves.
 *
 * The filter is a no-op when Restricted Site Access is not installed.
 */
final class RestrictedSiteAccess {

	public function hooks(): void {
		add_filter( 'restricted_site_access_is_restricted', array( $this, 'allow_scanner' ), 10, 2 );
	}

	/**
	 * @param bool      $is_restricted Whether RSA will restrict this request.
	 * @param \WP|mixed $wp            The main WordPress request object.
	 */
	public function allow_scanner( $is_restricted, $wp ): bool {
		unset( $wp );

		if ( ! $is_restricted ) {
			return (bool) $is_restricted;
		}

		if ( ! ScanKeyAuth::is_valid_raw_key() ) {
			return (bool) $is_restricted;
		}

		// Prevent a page cache/CDN from storing this unrestricted response and
		// later serving it to a keyless (public) visitor.
		nocache_headers();

		return false;
	}
}
