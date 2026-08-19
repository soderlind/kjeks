<?php
/**
 * Network-wide banner content and settings.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Inventory;

/**
 * Reads and writes the network-wide banner content and settings.
 *
 * The tracker registry lives in TrackerRegistry; this module holds only the
 * display defaults and the uninstall opt-in.
 */
final class NetworkStore {

	private const CONTENT  = 'kjeks_network_content';
	private const SETTINGS = 'kjeks_network_settings';

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
