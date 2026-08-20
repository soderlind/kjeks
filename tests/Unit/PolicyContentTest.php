<?php
/**
 * PolicyContent privacy-guide contribution tests.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Soderlind\Kjeks\Privacy\PolicyContent;

it( 'includes each consent category in the suggested text', function (): void {
	$text = ( new PolicyContent() )->suggested_text();

	expect( $text )->toContain( 'Necessary' )
		->and( $text )->toContain( 'Preferences' )
		->and( $text )->toContain( 'Analytics' )
		->and( $text )->toContain( 'Marketing' );
} );

it( 'registers the suggested text with the WordPress privacy guide', function (): void {
	Functions\expect( 'wp_add_privacy_policy_content' )
		->once()
		->with(
			'Kjeks',
			Mockery::on(
				function ( $text ): bool {
					expect( $text )->toBeString()->toContain( 'Necessary' );

					return true;
				}
			)
		);

	( new PolicyContent() )->register();
} );
