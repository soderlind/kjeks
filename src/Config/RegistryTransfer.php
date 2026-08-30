<?php
/**
 * Registry normalization and merge for config bundles.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Config;

use Soderlind\Kjeks\Inventory\Tracker;
use Soderlind\Kjeks\Inventory\TrackerRegistry;

/**
 * Moves the authored layer of the tracker Registry between installs (ADR 0007).
 *
 * Export ships only manual and reviewed entries, keyed by Identity, with
 * per-install observation noise (site lists, timestamps) stripped. Apply merges
 * by Identity: the target keeps its own entries and observations, the incoming
 * bundle only overlays classification, so local review work is never lost.
 */
final class RegistryTransfer {

	public function __construct(
		private readonly TrackerRegistry $registry = new TrackerRegistry(),
	) {}

	/**
	 * Normalized authored slice for export.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function export(): array {
		$out = array();

		foreach ( $this->registry->trackers() as $tracker ) {
			if ( ! $tracker->reviewed && 'manual' !== $tracker->source ) {
				continue;
			}

			$data                   = $tracker->to_array();
			$data['sites']          = array();
			$data['first_observed'] = 0;
			$data['last_observed']  = 0;
			$out[]                  = $data;
		}

		return $out;
	}

	/**
	 * Merges an incoming authored slice into the target Registry by Identity.
	 *
	 * Existing entries keep their observations (sites, timestamps) and only take
	 * the incoming classification; unknown entries are added network-wide.
	 *
	 * @param array<array-key, mixed> $incoming Normalized entries from a bundle.
	 * @return array{added: int, updated: int} Counts.
	 */
	public function merge( array $incoming ): array {
		$trackers = $this->registry->trackers();
		$added    = 0;
		$updated  = 0;

		foreach ( $incoming as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}

			$in = Tracker::from_array( $raw );
			if ( '' === $in->id ) {
				continue;
			}

			if ( isset( $trackers[ $in->id ] ) ) {
				$target                      = $trackers[ $in->id ]->to_array();
				$target['category']          = $in->category;
				$target['reviewed']          = $in->reviewed;
				$target['provider']          = $in->provider;
				$target['purpose']           = $in->purpose;
				$target['party']             = $in->party;
				$target['documentation_url'] = $in->documentation_url;
				if ( '' !== $in->retention ) {
					$target['retention'] = $in->retention;
				}
				$trackers[ $in->id ] = Tracker::from_array( $target );
				++$updated;
			} else {
				$data          = $in->to_array();
				$data['sites'] = array();
				if ( '' === $data['source'] ) {
					$data['source'] = 'import';
				}
				$trackers[ $in->id ] = Tracker::from_array( $data );
				++$added;
			}
		}

		$this->registry->save_trackers( $trackers );

		return array(
			'added'   => $added,
			'updated' => $updated,
		);
	}
}
