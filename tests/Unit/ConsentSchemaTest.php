<?php
/**
 * Consent schema tests.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

use Soderlind\Kjeks\Consent\ConsentSchema;

it( 'defaults every optional category to denied', function (): void {
	$map = ConsentSchema::denied_map();

	foreach ( $map as $value ) {
		expect( $value )->toBe( 0 );
	}
	expect( $map )->not->toHaveKey( 'necessary' );
} );

it( 'encodes choices into the compact wire format', function (): void {
	$record = ConsentSchema::encode( [ 'analytics' => true ], 3, 7, 1000 );

	expect( $record['v'] )->toBe( 3 )
		->and( $record['b'] )->toBe( 7 )
		->and( $record['t'] )->toBe( 1000 )
		->and( $record['c']['analytics'] )->toBe( 1 )
		->and( $record['c']['marketing'] )->toBe( 0 );
} );

it( 'round-trips through decode', function (): void {
	$encoded = ConsentSchema::encode( [ 'marketing' => true ], 2, 5, 500 );
	$decoded = ConsentSchema::decode( wp_json_encode_shim( $encoded ) );

	expect( $decoded )->toEqual( $encoded );
} );

it( 'returns null for malformed input', function (): void {
	expect( ConsentSchema::decode( 'not-json' ) )->toBeNull()
		->and( ConsentSchema::decode( [ 'v' => 1 ] ) )->toBeNull();
} );

/**
 * Local JSON helper (avoids depending on wp_json_encode in unit context).
 */
function wp_json_encode_shim( array $data ): string {
	return json_encode( $data );
}
