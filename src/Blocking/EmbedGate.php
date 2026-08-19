<?php
/**
 * Embed gate.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\Blocking;

use Soderlind\Kjeks\Consent\Categories;

/**
 * Renders iframes/embeds behind an accessible, consent-gated placeholder.
 *
 * The real `src` is withheld (kept in `data-kjeks-src`) until the matching
 * category is granted, at which point banner.js swaps it in. Until then a
 * keyboard-focusable placeholder with an explicit "load" control is shown.
 */
final class EmbedGate {

	/**
	 * Builds gated embed markup.
	 *
	 * @param string               $src      The embed URL.
	 * @param string               $category Consent category.
	 * @param array<string, mixed> $args     title, width, height, provider.
	 */
	public function render( string $src, string $category, array $args = array() ): string {
		if ( ! Categories::exists( $category ) ) {
			$category = Categories::MARKETING;
		}

		$title    = isset( $args['title'] ) ? (string) $args['title'] : __( 'Embedded content', 'kjeks' );
		$provider = isset( $args['provider'] ) ? (string) $args['provider'] : '';
		$width    = isset( $args['width'] ) ? (int) $args['width'] : 560;
		$height   = isset( $args['height'] ) ? (int) $args['height'] : 315;

		$message = $provider
			/* translators: %s: provider name. */
			? sprintf( __( 'This %s content is blocked until you accept the matching cookies.', 'kjeks' ), $provider )
			: __( 'This content is blocked until you accept the matching cookies.', 'kjeks' );

		$markup = sprintf(
			'<div class="kjeks-embed" data-kjeks-embed data-kjeks-category="%1$s" data-kjeks-src="%2$s" data-kjeks-width="%3$d" data-kjeks-height="%4$d" data-kjeks-title="%5$s" style="aspect-ratio:%3$d/%4$d">'
			. '<div class="kjeks-embed__placeholder" role="group" aria-label="%5$s">'
			. '<p class="kjeks-embed__message">%6$s</p>'
			. '<button type="button" class="kjeks-embed__load" data-kjeks-embed-load>%7$s</button>'
			. '<button type="button" class="kjeks-embed__prefs" data-kjeks-open>%8$s</button>'
			. '</div></div>',
			esc_attr( $category ),
			esc_url( $src ),
			$width,
			$height,
			esc_attr( $title ),
			esc_html( $message ),
			esc_html__( 'Load content', 'kjeks' ),
			esc_html__( 'Cookie settings', 'kjeks' )
		);

		/**
		 * Filters the gated embed markup.
		 *
		 * @param string $markup   The placeholder markup.
		 * @param string $src      The embed URL.
		 * @param string $category Consent category.
		 * @param array<string, mixed> $args Rendering arguments.
		 */
		return (string) apply_filters( 'kjeks_embed_html', $markup, $src, $category, $args );
	}
}
