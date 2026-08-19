<?php
/**
 * Consent cookie schema.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Consent;

/**
 * Canonical shape of the stored consent record.
 *
 * Shared by the frontend runtime, the REST surface, and (in Phase 2) the
 * discovery scanner, so producers and consumers never drift. The record is
 * stored client-side only: a first-party cookie mirrored to localStorage.
 *
 * Wire format (compact keys keep the cookie small):
 *   v: policy version (int)
 *   t: consent timestamp, unix seconds (int)
 *   b: blog id (int)
 *   c: category map, optional categories only, slug => 0|1
 */
final class ConsentSchema {

	public const COOKIE_NAME   = 'kjeks_consent';
	public const STORAGE_KEY   = 'kjeks_consent';
	public const COOKIE_MONTHS = 6;

	/**
	 * Encodes a consent state into the wire array.
	 *
	 * @param array<string, bool> $choices  Optional-category choices, slug => granted.
	 * @param int                 $version  Policy version.
	 * @param int                 $blog_id  Blog identifier.
	 * @param int                 $time     Consent timestamp (unix seconds).
	 * @return array{v: int, t: int, b: int, c: array<string, int>}
	 */
	public static function encode( array $choices, int $version, int $blog_id, int $time ): array {
		$map = array();
		foreach ( Categories::optional() as $slug ) {
			$map[ $slug ] = ! empty( $choices[ $slug ] ) ? 1 : 0;
		}

		return array(
			'v' => $version,
			't' => $time,
			'b' => $blog_id,
			'c' => $map,
		);
	}

	/**
	 * Decodes and validates a raw wire value.
	 *
	 * Returns null when the value is malformed, so callers treat it as
	 * "no decision" and re-prompt.
	 *
	 * @param mixed $raw Decoded JSON (array) or JSON string.
	 * @return array{v: int, t: int, b: int, c: array<string, int>}|null
	 */
	public static function decode( mixed $raw ): ?array {
		if ( is_string( $raw ) ) {
			$raw = json_decode( $raw, true );
		}

		if ( ! is_array( $raw ) || ! isset( $raw['v'], $raw['t'], $raw['b'], $raw['c'] ) || ! is_array( $raw['c'] ) ) {
			return null;
		}

		$map = array();
		foreach ( Categories::optional() as $slug ) {
			$map[ $slug ] = ( isset( $raw['c'][ $slug ] ) && 1 === (int) $raw['c'][ $slug ] ) ? 1 : 0;
		}

		return array(
			'v' => (int) $raw['v'],
			't' => (int) $raw['t'],
			'b' => (int) $raw['b'],
			'c' => $map,
		);
	}

	/**
	 * The default, all-optional-denied choice map.
	 *
	 * @return array<string, int>
	 */
	public static function denied_map(): array {
		$map = array();
		foreach ( Categories::optional() as $slug ) {
			$map[ $slug ] = 0;
		}

		return $map;
	}
}
