<?php
/**
 * Example: YouTube / Vimeo embeds gated behind the marketing category.
 *
 * Use the [kjeks_youtube] / [kjeks_vimeo] shortcodes, or call kjeks_embed()
 * directly. The real iframe is withheld behind an accessible placeholder
 * until consent is granted.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

add_shortcode(
	'kjeks_youtube',
	static function ( array $atts ): string {
		if ( ! function_exists( 'kjeks_embed' ) ) {
			return '';
		}

		$atts = shortcode_atts( array( 'id' => '' ), $atts, 'kjeks_youtube' );
		$id   = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $atts['id'] );
		if ( '' === $id ) {
			return '';
		}

		return kjeks_embed(
			'https://www.youtube-nocookie.com/embed/' . $id,
			'marketing',
			array(
				'title'    => __( 'YouTube video', 'kjeks' ),
				'provider' => 'YouTube',
			)
		);
	}
);

add_shortcode(
	'kjeks_vimeo',
	static function ( array $atts ): string {
		if ( ! function_exists( 'kjeks_embed' ) ) {
			return '';
		}

		$atts = shortcode_atts( array( 'id' => '' ), $atts, 'kjeks_vimeo' );
		$id   = preg_replace( '/[^0-9]/', '', (string) $atts['id'] );
		if ( '' === $id ) {
			return '';
		}

		return kjeks_embed(
			'https://player.vimeo.com/video/' . $id,
			'marketing',
			array(
				'title'    => __( 'Vimeo video', 'kjeks' ),
				'provider' => 'Vimeo',
			)
		);
	}
);
