<?php
/**
 * New-site initialization.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Lifecycle;

use WP_Site;

/**
 * Initializes per-site defaults when a new subsite is created.
 *
 * Uses inherit-at-read: a new site stores only a starting policy version.
 * Network tracker definitions are resolved lazily, so there is no per-site
 * copy and no expensive work on site creation.
 */
final class SiteInitializer {

	public function hooks(): void {
		add_action( 'wp_initialize_site', array( $this, 'on_new_site' ), 20, 1 );
	}

	public function on_new_site( WP_Site $site ): void {
		self::ensure_site_defaults( (int) $site->blog_id );
	}

	/**
	 * Ensures a site has the minimal starting options.
	 */
	public static function ensure_site_defaults( int $blog_id ): void {
		$apply = static function (): void {
			if ( false === get_option( 'kjeks_policy_version', false ) ) {
				add_option( 'kjeks_policy_version', 1 );
			}
		};

		if ( get_current_blog_id() === $blog_id ) {
			$apply();
			return;
		}

		switch_to_blog( $blog_id );
		$apply();
		restore_current_blog();
	}
}
