<?php
/**
 * Builds the scanner site configuration.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Scan;

use Soderlind\Kjeks\Consent\PolicyVersion;

/**
 * Enumerates the network's sites into the shape the discovery scanner consumes.
 *
 * Shared by the WP-CLI `scan-config` command and the REST scan-config endpoint,
 * so both produce identical output.
 */
final class ScanConfig {

	/**
	 * Builds the site list.
	 *
	 * @param array<int, int>    $include_ids Blog ids to include (empty = all public).
	 * @param array<int, string> $paths       Representative paths for every site.
	 * @return array{sites: array<int, array{url: string, blog_id: int, policy_version: int, paths: array<int, string>}>}
	 */
	public function build( array $include_ids = array(), array $paths = array( '/' ) ): array {
		if ( array() === $paths ) {
			$paths = array( '/' );
		}

		$sites = array();
		foreach ( $this->site_ids() as $blog_id ) {
			if ( array() !== $include_ids && ! in_array( $blog_id, $include_ids, true ) ) {
				continue;
			}

			switch_to_blog( $blog_id );
			$sites[] = array(
				'url'            => home_url( '/' ),
				'blog_id'        => $blog_id,
				'policy_version' => PolicyVersion::current(),
				'paths'          => array_values( $paths ),
			);
			restore_current_blog();
		}

		return array( 'sites' => $sites );
	}

	/**
	 * Public, non-archived, non-deleted, non-spam blog ids.
	 *
	 * @return array<int, int>
	 */
	private function site_ids(): array {
		if ( ! is_multisite() ) {
			return array( get_current_blog_id() );
		}

		$sites = get_sites(
			array(
				'number'   => 0,
				'public'   => 1,
				'archived' => 0,
				'deleted'  => 0,
				'spam'     => 0,
				'fields'   => 'ids',
			)
		);

		return array_map( 'intval', $sites );
	}
}
