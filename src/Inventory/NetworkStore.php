<?php
/**
 * Network-wide tracker definitions.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Inventory;

/**
 * Reads and writes the network-wide tracker definitions and display defaults.
 *
 * Network-wide definitions are the common case; individual sites inherit them
 * and may override at read-time (see InventoryResolver).
 */
final class NetworkStore {

	private const TRACKERS = 'kjeks_network_trackers';
	private const CONTENT  = 'kjeks_network_content';
	private const SETTINGS = 'kjeks_network_settings';

	/**
	 * Network-wide tracker definitions.
	 *
	 * @return array<string, Tracker>
	 */
	public function trackers(): array {
		$raw = get_site_option( self::TRACKERS, array() );
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
	 * Replaces the network-wide tracker definitions.
	 *
	 * @param array<string, Tracker> $trackers Trackers keyed by id.
	 */
	public function save_trackers( array $trackers ): void {
		$raw = array();
		foreach ( $trackers as $tracker ) {
			$raw[ $tracker->id ] = $tracker->to_array();
		}

		update_site_option( self::TRACKERS, $raw );
	}

	/**
	 * Aggregates scanned observations into the network registry.
	 *
	 * The same cookie seen on many sites collapses to one entry that records
	 * every site it appeared on. Existing review status and classification are
	 * preserved; new entries are unreviewed and never necessary.
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

	/**
	 * Network default display content (banner copy, privacy URL, accent).
	 *
	 * @return array<string, string>
	 */
	public function content(): array {
		$defaults = array(
			'heading'     => __( 'We value your privacy', 'kjeks' ),
			'body'        => __( 'We use cookies and similar technologies. Choose which categories to allow. Necessary items are always active.', 'kjeks' ),
			'privacy_url' => '',
			'accent'      => '#1e40af',
		);

		$stored = get_site_option( self::CONTENT, array() );

		return is_array( $stored ) ? array_merge( $defaults, $stored ) : $defaults;
	}

	/**
	 * Saves network default display content.
	 *
	 * @param array<string, string> $content Content values.
	 */
	public function save_content( array $content ): void {
		update_site_option( self::CONTENT, $content );
	}

	/**
	 * Whether uninstall should delete all Kjeks data (network opt-in, default off).
	 */
	public function delete_on_uninstall(): bool {
		$settings = get_site_option( self::SETTINGS, array() );

		return is_array( $settings ) && ! empty( $settings['delete_on_uninstall'] );
	}

	/**
	 * Sets the uninstall opt-in.
	 */
	public function set_delete_on_uninstall( bool $enabled ): void {
		$settings = get_site_option( self::SETTINGS, array() );
		$settings = is_array( $settings ) ? $settings : array();

		$settings['delete_on_uninstall'] = $enabled;
		update_site_option( self::SETTINGS, $settings );
	}
}
