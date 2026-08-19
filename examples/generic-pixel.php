<?php
/**
 * Example: a generic tracking pixel gated behind the marketing category.
 *
 * The pixel is delivered as an inline script so it never fires before consent.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

add_action(
	'kjeks_register_integrations',
	static function (): void {
		if ( ! function_exists( 'kjeks_add_inline_script' ) ) {
			return;
		}

		kjeks_add_inline_script(
			'marketing',
			"(function(){var i=new Image();i.src='https://pixel.example.com/p.gif?id=42&t='+Date.now();})();",
			'example-pixel'
		);
	}
);
