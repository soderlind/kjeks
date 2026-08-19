<?php
/**
 * Example: Plausible Analytics — a no-consent-needed reference integration.
 *
 * Plausible is cookieless and stores no persistent identifier, so it is NOT
 * gated: it loads unconditionally. This example is deliberately outside the
 * blocking layer to show how a genuinely consent-free tool is wired.
 *
 * See https://plausible.io/data-policy — no cookies, no personal data, IP not
 * stored. Whether analytics needs consent is a per-jurisdiction decision; an
 * admin who is unsure should instead register Plausible under the analytics
 * category and gate it like any other integration.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$domain = 'example.com'; // Your Plausible "data-domain".

		wp_enqueue_script(
			'plausible-analytics',
			'https://plausible.io/js/script.js',
			array(),
			null,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		add_filter(
			'script_loader_tag',
			static function ( string $tag, string $handle ) use ( $domain ): string {
				if ( 'plausible-analytics' !== $handle ) {
					return $tag;
				}

				return str_replace(
					' src=',
					' data-domain="' . esc_attr( $domain ) . '" src=',
					$tag
				);
			},
			10,
			2
		);
	}
);
