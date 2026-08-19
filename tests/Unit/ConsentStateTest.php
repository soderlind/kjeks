<?php
/**
 * ConsentState tests.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

use Soderlind\Kjeks\Consent\ConsentSchema;
use Soderlind\Kjeks\Consent\ConsentState;

afterEach(
	function (): void {
		unset( $_COOKIE[ ConsentSchema::COOKIE_NAME ] );
	}
);

it( 'defaults to denied when there is no consent cookie', function (): void {
	$state = new ConsentState();

	expect( $state->has_decision() )->toBeFalse()
		->and( $state->is_granted( 'analytics' ) )->toBeFalse()
		->and( $state->is_granted( 'necessary' ) )->toBeTrue();
} );

it( 'reads a valid, current-version cookie', function (): void {
	$_COOKIE[ ConsentSchema::COOKIE_NAME ] = json_encode(
		[
			'v' => 1,
			't' => 123,
			'b' => 1,
			'c' => [ 'analytics' => 1, 'marketing' => 0, 'preferences' => 0 ],
		]
	);

	$state = new ConsentState();

	expect( $state->has_decision() )->toBeTrue()
		->and( $state->is_granted( 'analytics' ) )->toBeTrue()
		->and( $state->is_granted( 'marketing' ) )->toBeFalse();
} );

it( 'invalidates consent when the policy version differs', function (): void {
	$_COOKIE[ ConsentSchema::COOKIE_NAME ] = json_encode(
		[
			'v' => 99,
			't' => 123,
			'b' => 1,
			'c' => [ 'analytics' => 1 ],
		]
	);

	$state = new ConsentState();

	expect( $state->has_decision() )->toBeFalse()
		->and( $state->is_granted( 'analytics' ) )->toBeFalse();
} );
