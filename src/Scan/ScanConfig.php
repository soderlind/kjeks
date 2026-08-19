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

	/** @var callable(int): array<int, string> */
	private $sampler;

	/**
	 * @param null|callable(int): array<int, string> $sampler Per-site path selector (testing seam).
	 */
	public function __construct( ?callable $sampler = null ) {
		$this->sampler = $sampler ?? static fn ( int $cap ): array => ( new PageSampler( $cap ) )->paths();
	}

	/**
	 * Builds the site list.
	 *
	 * When $paths is null the paths are auto-selected per site (representative
	 * URLs via WP_Query). Passing an array overrides that with explicit paths
	 * for every site; an empty array falls back to root.
	 *
	 * @param array<int, int>         $include_ids Blog ids to include (empty = all public).
	 * @param null|array<int, string> $paths       Explicit paths, or null to auto-select.
	 * @param int                     $cap         Max auto-selected paths per site.
	 * @return array{sites: array<int, array{url: string, blog_id: int, policy_version: int, paths: array<int, string>}>}
	 */
	public function build( array $include_ids = array(), ?array $paths = null, int $cap = 10 ): array {
		$cap = (int) apply_filters( 'kjeks_scan_url_cap', $cap );

		$sites = array();
		foreach ( $this->site_ids() as $blog_id ) {
			if ( array() !== $include_ids && ! in_array( $blog_id, $include_ids, true ) ) {
				continue;
			}

			switch_to_blog( $blog_id );

			if ( null === $paths ) {
				$site_paths = ( $this->sampler )( $cap );
			} else {
				$site_paths = array() === $paths ? array( '/' ) : array_values( $paths );
			}

			/**
			 * Filters the representative paths for a site before scanning.
			 *
			 * @param array<int, string> $site_paths Selected paths.
			 * @param int                $blog_id    Blog id.
			 */
			$site_paths = (array) apply_filters( 'kjeks_scan_paths', $site_paths, $blog_id );

			$sites[] = array(
				'url'            => home_url( '/' ),
				'blog_id'        => $blog_id,
				'policy_version' => PolicyVersion::current(),
				'paths'          => array_values( $site_paths ),
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
