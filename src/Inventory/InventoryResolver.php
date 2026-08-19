<?php
/**
 * Resolves the effective inventory for a site.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Inventory;

use Soderlind\Kjeks\Consent\PolicyVersion;

/**
 * Merges network-wide definitions with a site's overrides and local trackers.
 *
 * Sites inherit network definitions at read-time (no per-site copy). The
 * resolved, reviewed inventory is the single source of truth for the consent
 * UI and the cookie declaration. Results are cached in a transient keyed by
 * the policy version so frontend requests stay cheap.
 */
final class InventoryResolver {

	private const CACHE_PREFIX = 'kjeks_inventory_';

	public function __construct(
		private readonly NetworkStore $network,
		private readonly SiteStore $site,
	) {}

	/**
	 * The effective, merged inventory keyed by tracker id.
	 *
	 * Network trackers are scoped to the sites they were observed on (an empty
	 * site list means network-wide). Reviewed network trackers are authoritative
	 * and ignore per-site overrides.
	 *
	 * @return array<string, Tracker>
	 */
	public function all(): array {
		$overrides = $this->site->overrides();
		$blog_id   = $this->site->blog_id();
		$effective = array();

		foreach ( $this->network->trackers() as $id => $tracker ) {
			if ( array() !== $tracker->sites && ! in_array( $blog_id, $tracker->sites, true ) ) {
				continue;
			}

			if ( $tracker->reviewed ) {
				$effective[ $id ] = $tracker;
				continue;
			}

			$override = $overrides[ $id ] ?? array();
			if ( isset( $override['enabled'] ) && false === (bool) $override['enabled'] ) {
				continue;
			}

			$effective[ $id ] = $this->apply_override( $tracker, $override );
		}

		foreach ( $this->site->local_trackers() as $id => $tracker ) {
			$effective[ $id ] = $tracker;
		}

		return $effective;
	}

	/**
	 * Only reviewed trackers — what the public consent UI may display.
	 *
	 * @return array<string, Tracker>
	 */
	public function reviewed(): array {
		return array_filter( $this->all(), static fn ( Tracker $t ): bool => $t->reviewed );
	}

	/**
	 * Reviewed trackers grouped by category slug.
	 *
	 * @return array<string, list<Tracker>>
	 */
	public function reviewed_by_category(): array {
		$grouped = array();
		foreach ( $this->reviewed() as $tracker ) {
			$grouped[ $tracker->category ][] = $tracker;
		}

		return $grouped;
	}

	/**
	 * Cached reviewed inventory as plain arrays, for frontend localisation.
	 *
	 * @return array<string, list<array<string, mixed>>>
	 */
	public function cached_declaration(): array {
		$key    = self::CACHE_PREFIX . PolicyVersion::current();
		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$out = array();
		foreach ( $this->reviewed_by_category() as $category => $trackers ) {
			$out[ $category ] = array_map( static fn ( Tracker $t ): array => $t->to_array(), $trackers );
		}

		set_transient( $key, $out, HOUR_IN_SECONDS );

		return $out;
	}

	/**
	 * Clears the cached declaration for the current site.
	 */
	public static function flush_cache(): void {
		$max = PolicyVersion::current() + 1;
		for ( $version = 1; $version <= $max; $version++ ) {
			delete_transient( self::CACHE_PREFIX . $version );
		}
	}

	/**
	 * @param array<string, mixed> $override Override data for a network tracker.
	 */
	private function apply_override( Tracker $tracker, array $override ): Tracker {
		if ( array() === $override ) {
			return $tracker;
		}

		$data = array_merge( $tracker->to_array(), array_diff_key( $override, array( 'enabled' => true ) ) );

		return Tracker::from_array( $data );
	}
}
