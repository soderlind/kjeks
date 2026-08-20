<?php
/**
 * Privacy Policy Guide contribution.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Privacy;

use Soderlind\Kjeks\Consent\Categories;

/**
 * Adds suggested text to the WordPress Privacy Policy Guide.
 *
 * The guide content is copied once by the admin into their policy, so it holds
 * only stable prose (category descriptions and how consent works). The live,
 * always-current cookie table is provided separately by PrivacyPageDeclaration
 * and the `kjeks/cookie-declaration` block/shortcode.
 */
final class PolicyContent {

	public function hooks(): void {
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	public function register(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		wp_add_privacy_policy_content( 'Kjeks', $this->suggested_text() );
	}

	public function suggested_text(): string {
		$out  = '<h2>' . esc_html__( 'Cookies and similar technologies', 'kjeks' ) . '</h2>';
		$out .= '<p>' . esc_html__( 'This site asks for consent before setting non-essential cookies. Necessary items are always active; every other category stays off until you allow it. You can change your choice at any time via the cookie settings.', 'kjeks' ) . '</p>';
		$out .= '<p>' . esc_html__( 'The categories below describe what each group of cookies is used for. The current list of individual cookies and trackers is shown on this page.', 'kjeks' ) . '</p>';

		foreach ( Categories::all() as $meta ) {
			$out .= '<h3>' . esc_html( $meta['label'] ) . '</h3>';
			$out .= '<p>' . esc_html( $meta['description'] ) . '</p>';
		}

		return $out;
	}
}
