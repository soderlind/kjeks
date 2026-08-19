<?php
/**
 * Tracker tests.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

use Soderlind\Kjeks\Inventory\Tracker;

it( 'never classifies an unknown or missing category as necessary', function (): void {
	$missing = Tracker::from_array( [ 'name' => 'Mystery' ] );
	$bogus   = Tracker::from_array( [ 'name' => 'Bogus', 'category' => 'does-not-exist' ] );

	expect( $missing->category )->toBe( 'marketing' )
		->and( $bogus->category )->toBe( 'marketing' )
		->and( $missing->reviewed )->toBeFalse();
} );

it( 'marks a tracker reviewed only via with_review', function (): void {
	$tracker  = Tracker::from_array( [ 'name' => 'Thing', 'category' => 'analytics' ] );
	$reviewed = $tracker->with_review( 'preferences' );

	expect( $tracker->reviewed )->toBeFalse()
		->and( $reviewed->reviewed )->toBeTrue()
		->and( $reviewed->category )->toBe( 'preferences' );
} );

it( 'produces a stable slug from the name', function (): void {
	$tracker = Tracker::from_array( [ 'name' => 'Google Analytics' ] );

	expect( $tracker->id )->toBe( 'google-analytics' );
} );
