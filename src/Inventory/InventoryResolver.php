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
 * Resolves the effective inventory for one site.
 *
 * Scopes the network registry to the sites each tracker applies to. The
 * network registry is the single source of truth for the consent UI and the
 * cookie declaration; results are cached in a transient keyed by the policy
 * version so frontend requests stay cheap.
 */
final class InventoryResolver {

	private const CACHE_PREFIX = 'kjeks_inventory_';

	public function __construct(
		private readonly TrackerRegistry $registry,
		private readonly int $blog_id,
	) {}

	/**
	 * The effective inventory for this site, keyed by tracker id.
	 *
	 * @return array<string, Tracker>
	 */
	public function all(): array {
		return $this->scoped_network_trackers();
	}

	/**
	 * Network trackers that apply to this site.
	 *
	 * A tracker with an empty site list is network-wide; otherwise it applies
	 * only to the sites where it was observed. This is the single home of the
	 * site-scope rule.
	 *
	 * @return array<string, Tracker>
	 */
	public function scoped_network_trackers(): array {
		$blog_id = $this->blog_id;
		$out     = array();

		foreach ( $this->registry->trackers() as $id => $tracker ) {
			if ( array() !== $tracker->sites && ! in_array( $blog_id, $tracker->sites, true ) ) {
				continue;
			}

			$out[ $id ] = $tracker;
		}

		return $out;
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
}
