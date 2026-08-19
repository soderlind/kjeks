<?php
/**
 * Per-site policy version.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Consent;

/**
 * Manages the per-site policy version.
 *
 * The policy version is a manual integer. Bumping it invalidates prior
 * consent and re-prompts visitors (see ADR 0004). The value is stored per
 * site so each blog controls its own re-prompt cadence.
 */
final class PolicyVersion {

	private const OPTION = 'kjeks_policy_version';

	/**
	 * Current policy version for the given (or current) site.
	 */
	public static function current( ?int $blog_id = null ): int {
		if ( null !== $blog_id && get_current_blog_id() !== $blog_id ) {
			$value = get_blog_option( $blog_id, self::OPTION, 1 );
		} else {
			$value = get_option( self::OPTION, 1 );
		}

		return max( 1, (int) $value );
	}

	/**
	 * Sets the policy version for the current site.
	 */
	public static function set( int $version ): void {
		update_option( self::OPTION, max( 1, $version ) );
	}

	/**
	 * Bumps the policy version by one and returns the new value.
	 */
	public static function bump(): int {
		$next = self::current() + 1;
		self::set( $next );

		return $next;
	}
}
