<?php
/**
 * Imports validated scan observations into the network registry.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Scan;

use Soderlind\Kjeks\Inventory\InventoryResolver;
use Soderlind\Kjeks\Inventory\Tracker;
use Soderlind\Kjeks\Inventory\TrackerRegistry;

/**
 * Aggregates scan observations into the network-wide registry.
 *
 * The same cookie discovered across many sites collapses to a single
 * unreviewed entry that records every site it appeared on, so a network admin
 * reviews it once. Nothing is auto-classified as necessary, and existing
 * network reviews are preserved.
 */
final class ScanImporter {

	/**
	 * Imports observations seen on a site into the network registry.
	 *
	 * @param array<int, Tracker> $trackers Observations to import.
	 * @return int Number of new trackers added to the network registry.
	 */
	public function import( int $blog_id, array $trackers ): int {
		if ( array() === $trackers ) {
			return 0;
		}

		$result = ( new TrackerRegistry() )->merge_observations( $blog_id, $trackers );

		InventoryResolver::flush_cache();

		return $result['added'];
	}
}
