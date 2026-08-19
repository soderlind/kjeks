<?php
/**
 * Network-wide tracker registry.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Inventory;

/**
 * The network-wide registry of tracker definitions and aggregated discoveries.
 *
 * One entry per cookie across the whole network; the same cookie seen on many
 * sites collapses to a single entry that records every site it appeared on.
 * This is the aggregation model's home — separate from banner content and
 * network settings, which live in NetworkStore.
 */
final class TrackerRegistry {

	private const OPTION = 'kjeks_network_trackers';

	/**
	 * All registered trackers, keyed by id.
	 *
	 * @return array<string, Tracker>
	 */
	public function trackers(): array {
		$raw = get_site_option( self::OPTION, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $item ) {
			if ( is_array( $item ) ) {
				$tracker             = Tracker::from_array( $item );
				$out[ $tracker->id ] = $tracker;
			}
		}

		return $out;
	}

	/**
	 * Replaces the registry.
	 *
	 * @param array<string, Tracker> $trackers Trackers keyed by id.
	 */
	public function save_trackers( array $trackers ): void {
		$raw = array();
		foreach ( $trackers as $tracker ) {
			$raw[ $tracker->id ] = $tracker->to_array();
		}

		update_site_option( self::OPTION, $raw );
	}

	/**
	 * Aggregates scanned observations into the registry.
	 *
	 * Existing review status and classification are preserved; new entries are
	 * unreviewed and never necessary.
	 *
	 * @param array<int, Tracker> $observations Observations from one site.
	 * @return array{added: int, updated: int} Counts.
	 */
	public function merge_observations( int $blog_id, array $observations ): array {
		$existing = $this->trackers();
		$added    = 0;
		$updated  = 0;

		foreach ( $observations as $observation ) {
			if ( isset( $existing[ $observation->id ] ) ) {
				$existing[ $observation->id ] = $existing[ $observation->id ]->with_site( $blog_id, $observation->last_observed );
				++$updated;
				continue;
			}

			$existing[ $observation->id ] = $observation->with_site( $blog_id, $observation->last_observed );
			++$added;
		}

		$this->save_trackers( $existing );

		return array(
			'added'   => $added,
			'updated' => $updated,
		);
	}
}
