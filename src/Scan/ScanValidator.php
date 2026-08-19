<?php
/**
 * Validates imported scan payloads.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Scan;

use Soderlind\Kjeks\Inventory\Tracker;
use Soderlind\Kjeks\Inventory\TrackerIdentity;

/**
 * Validates and normalizes a scan-import payload into unreviewed trackers.
 *
 * The scanner is observational and untrusted: every observation is coerced
 * into a Tracker with `reviewed = false` and is never classified as
 * necessary. Malformed observations are skipped and reported as warnings.
 */
final class ScanValidator {

	private const STORAGE_TYPES = array(
		'cookie',
		'localstorage',
		'sessionstorage',
		'indexeddb',
		'pixel',
		'beacon',
		'script',
		'iframe',
	);

	/**
	 * Validates a decoded payload.
	 *
	 * @param mixed $payload Decoded JSON payload.
	 * @return array{valid: bool, blog_id: int, trackers: array<int, Tracker>, errors: array<int, string>, warnings: array<int, string>}
	 */
	public function validate( mixed $payload ): array {
		$errors   = array();
		$warnings = array();
		$trackers = array();

		if ( ! is_array( $payload ) ) {
			return $this->result( false, 0, array(), array( 'Payload must be an object.' ), array() );
		}

		$blog_id = isset( $payload['blog_id'] ) ? (int) $payload['blog_id'] : 0;
		if ( $blog_id < 1 ) {
			$errors[] = 'Missing or invalid blog_id.';
		}

		$observations = $payload['observations'] ?? null;
		if ( ! is_array( $observations ) ) {
			$errors[]     = 'Missing observations array.';
			$observations = array();
		}

		foreach ( $observations as $index => $observation ) {
			$tracker = $this->to_tracker( $observation, (string) $index, $warnings );
			if ( null !== $tracker ) {
				$trackers[] = $tracker;
			}
		}

		return $this->result( array() === $errors, $blog_id, $trackers, $errors, $warnings );
	}

	/**
	 * @param mixed                $observation Raw observation.
	 * @param array<int, string>   $warnings    Warnings, by reference.
	 */
	private function to_tracker( mixed $observation, string $index, array &$warnings ): ?Tracker {
		if ( ! is_array( $observation ) ) {
			$warnings[] = "Observation {$index} is not an object; skipped.";
			return null;
		}

		$name = isset( $observation['name'] ) ? trim( (string) $observation['name'] ) : '';
		if ( '' === $name ) {
			$warnings[] = "Observation {$index} has no name; skipped.";
			return null;
		}

		$storage_type = isset( $observation['storage_type'] ) ? (string) $observation['storage_type'] : 'cookie';
		if ( ! in_array( $storage_type, self::STORAGE_TYPES, true ) ) {
			$warnings[]   = "Observation {$index} has unknown storage_type '{$storage_type}'; recorded as 'cookie'.";
			$storage_type = 'cookie';
		}

		$party = ( isset( $observation['party'] ) && Tracker::PARTY_FIRST === $observation['party'] )
			? Tracker::PARTY_FIRST
			: Tracker::PARTY_THIRD;

		// Category is intentionally ignored on import; observations start unclassified.
		return Tracker::from_array(
			array(
				'id'             => $observation['id'] ?? TrackerIdentity::for( $name, $storage_type, (string) ( $observation['domain'] ?? '' ) ),
				'name'           => $name,
				'provider'       => (string) ( $observation['provider'] ?? ( $observation['domain'] ?? '' ) ),
				'party'          => $party,
				'storage_type'   => $storage_type,
				'domain'         => (string) ( $observation['domain'] ?? '' ),
				'path'           => (string) ( $observation['path'] ?? '/' ),
				'retention'      => (string) ( $observation['retention'] ?? '' ),
				'source'         => 'scan',
				'reviewed'       => false,
				'first_observed' => (int) ( $observation['first_observed'] ?? 0 ),
				'last_observed'  => (int) ( $observation['last_observed'] ?? 0 ),
			)
		);
	}

	/**
	 * @param array<int, Tracker> $trackers Trackers.
	 * @param array<int, string>  $errors   Errors.
	 * @param array<int, string>  $warnings Warnings.
	 * @return array{valid: bool, blog_id: int, trackers: array<int, Tracker>, errors: array<int, string>, warnings: array<int, string>}
	 */
	private function result( bool $valid, int $blog_id, array $trackers, array $errors, array $warnings ): array {
		return array(
			'valid'    => $valid,
			'blog_id'  => $blog_id,
			'trackers' => $trackers,
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}
}
