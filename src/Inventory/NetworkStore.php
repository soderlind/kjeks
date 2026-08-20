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

	/**
	 * Whether the consent banner is shown by default until a visitor decides
	 * (network setting, default on). When off, the banner is not auto-shown and
	 * visitors open it from a preferences link; optional categories stay denied
	 * until a choice is made.
	 */
	public function banner_default_visible(): bool {
		$settings = get_site_option( self::SETTINGS, array() );
		if ( is_array( $settings ) && array_key_exists( 'banner_default_visible', $settings ) ) {
			return (bool) $settings['banner_default_visible'];
		}

		return true;
	}

	/**
	 * Sets whether the banner is shown by default until a visitor decides.
	 */
	public function set_banner_default_visible( bool $visible ): void {
		$settings = get_site_option( self::SETTINGS, array() );
		$settings = is_array( $settings ) ? $settings : array();

		$settings['banner_default_visible'] = $visible;
		update_site_option( self::SETTINGS, $settings );
	}

	/**
	 * Whether the cookie declaration is auto-appended to the site's privacy
	 * policy page (network setting, opt-in, default off).
	 */
	public function privacy_page_declaration(): bool {
		$settings = get_site_option( self::SETTINGS, array() );

		return is_array( $settings ) && ! empty( $settings['privacy_page_declaration'] );
	}

	/**
	 * Sets whether the cookie declaration is auto-appended to the privacy page.
	 */
	public function set_privacy_page_declaration( bool $enabled ): void {
		$settings = get_site_option( self::SETTINGS, array() );
		$settings = is_array( $settings ) ? $settings : array();

		$settings['privacy_page_declaration'] = $enabled;
		update_site_option( self::SETTINGS, $settings );
	}
}
