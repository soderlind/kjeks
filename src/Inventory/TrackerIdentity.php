<?php
/**
 * Tracker identity.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Inventory;

/**
 * The single rule for "when are two observations the same tracker".
 *
 * Identity is derived from name, storage type, and domain, then normalised to a
 * stable slug. The registry keys on this id, so aggregation correctness lives
 * here rather than being smeared across the scanner and the value object.
 */
final class TrackerIdentity {

	/**
	 * Canonical id for a tracker with the given identifying attributes.
	 */
	public static function for( string $name, string $storage_type, string $domain = '' ): string {
		return Tracker::slug( $name . '-' . $storage_type . '-' . $domain );
	}
}
