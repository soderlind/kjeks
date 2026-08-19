<?php
/**
 * Per-site inventory, overrides, and display content.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Inventory;

/**
 * Reads and writes one site's local trackers and display content.
 *
 * A site stores two things:
 *  - local trackers: site-only definitions and imported observations,
 *  - content: display overrides for the banner.
 *
 * Network trackers are authoritative and owned by the network registry; a site
 * does not override them.
 */
final class SiteStore {

	private const LOCAL   = 'kjeks_site_trackers';
	private const CONTENT = 'kjeks_site_content';

	public function __construct( private readonly int $blog_id ) {}

	public function blog_id(): int {
		return $this->blog_id;
	}

	/**
	 * Site-local trackers (including imported observations).
	 *
	 * @return array<string, Tracker>
	 */
	public function local_trackers(): array {
		$raw = $this->get( self::LOCAL, array() );
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
	 * @param array<string, Tracker> $trackers Trackers keyed by id.
	 */
	public function save_local_trackers( array $trackers ): void {
		$raw = array();
		foreach ( $trackers as $tracker ) {
			$raw[ $tracker->id ] = $tracker->to_array();
		}

		$this->set( self::LOCAL, $raw );
	}

	/**
	 * Adds site-local trackers, keeping existing ones as unreviewed unless present.
	 *
	 * Imported observations are always stored unreviewed until an admin reviews.
	 *
	 * @param array<int, Tracker> $incoming Trackers to merge in.
	 */
	public function add_local_trackers( array $incoming ): void {
		$existing = $this->local_trackers();

		foreach ( $incoming as $tracker ) {
			if ( isset( $existing[ $tracker->id ] ) ) {
				$existing[ $tracker->id ] = $existing[ $tracker->id ]->with_last_observed( $tracker->last_observed );
				continue;
			}

			$existing[ $tracker->id ] = Tracker::from_array(
				array_merge( $tracker->to_array(), array( 'reviewed' => false ) )
			);
		}

		$this->save_local_trackers( $existing );
	}

	/**
	 * Per-site display content overrides.
	 *
	 * @return array<string, string>
	 */
	public function content_overrides(): array {
		$raw = $this->get( self::CONTENT, array() );

		return is_array( $raw ) ? array_filter( $raw, static fn ( $v ): bool => '' !== $v && null !== $v ) : array();
	}

	/**
	 * @param array<string, string> $content Content overrides.
	 */
	public function save_content_overrides( array $content ): void {
		$this->set( self::CONTENT, $content );
	}

	private function get( string $key, mixed $fallback ): mixed {
		return get_current_blog_id() === $this->blog_id
			? get_option( $key, $fallback )
			: get_blog_option( $this->blog_id, $key, $fallback );
	}

	private function set( string $key, mixed $value ): void {
		if ( get_current_blog_id() === $this->blog_id ) {
			update_option( $key, $value );
			return;
		}

		update_blog_option( $this->blog_id, $key, $value );
	}
}
