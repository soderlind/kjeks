<?php
/**
 * Server-side view of the current visitor's consent.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Consent;

/**
 * Resolves the current request's consent state from the consent cookie.
 *
 * Gating is primarily client-side (ADR 0003), but the server needs a
 * read-only view for the opt-in conditional-enqueue path and for filters.
 * Consent is treated as "denied for all optional categories" whenever there
 * is no valid, current-version decision.
 */
final class ConsentState {

	/**
	 * @var array<string, int>|null
	 */
	private ?array $granted = null;

	private bool $has_decision = false;

	public function __construct() {
		$this->resolve();
	}

	private function resolve(): void {
		$denied = ConsentSchema::denied_map();

		$raw = isset( $_COOKIE[ ConsentSchema::COOKIE_NAME ] )
			? sanitize_text_field( wp_unslash( $_COOKIE[ ConsentSchema::COOKIE_NAME ] ) )
			: '';

		if ( '' === $raw ) {
			$this->granted      = $denied;
			$this->has_decision = false;

			return;
		}

		$decoded = ConsentSchema::decode( $raw );

		if ( null === $decoded || PolicyVersion::current() !== $decoded['v'] || get_current_blog_id() !== $decoded['b'] ) {
			$this->granted      = $denied;
			$this->has_decision = false;

			return;
		}

		$this->granted      = $decoded['c'];
		$this->has_decision = true;
	}

	/**
	 * Whether the visitor has made an explicit, still-valid choice.
	 */
	public function has_decision(): bool {
		return $this->has_decision;
	}

	/**
	 * Whether a given category is currently granted.
	 *
	 * `necessary` is always granted; unknown categories are denied.
	 */
	public function is_granted( string $category ): bool {
		if ( Categories::is_required( $category ) ) {
			return true;
		}

		$granted = ( isset( $this->granted[ $category ] ) && 1 === $this->granted[ $category ] );

		/**
		 * Filters whether a category is granted for the current request.
		 *
		 * @param bool         $granted  Whether the category is granted.
		 * @param string       $category Category slug.
		 * @param ConsentState $state    The consent state instance.
		 */
		return (bool) apply_filters( 'kjeks_is_granted', $granted, $category, $this );
	}

	/**
	 * Granted optional categories as a slug => 0|1 map.
	 *
	 * @return array<string, int>
	 */
	public function map(): array {
		return $this->granted ?? ConsentSchema::denied_map();
	}
}
