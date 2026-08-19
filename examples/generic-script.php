<?php
/**
 * Example: a generic third-party script gated behind the analytics category.
 *
 * Copy into an mu-plugin or your theme's functions.php and adapt.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

add_action(
	'kjeks_register_integrations',
	static function (): void {
		if ( ! function_exists( 'kjeks_register_integration' ) ) {
			return;
		}

		kjeks_register_integration(
			'acme-widget',
			array(
				'category'    => 'analytics',
				'label'       => 'Acme Widget',
				'src_scripts' => array(
					array(
						'src'   => 'https://cdn.example.com/acme.js',
						'attrs' => array( 'data-site' => 'ABC123' ),
					),
				),
			)
		);
	}
);
