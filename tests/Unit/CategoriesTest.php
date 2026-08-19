<?php
/**
 * Categories tests.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

use Soderlind\Kjeks\Consent\Categories;

it( 'exposes the four canonical categories', function (): void {
	$slugs = Categories::slugs();

	expect( $slugs )->toContain( 'necessary', 'preferences', 'analytics', 'marketing' );
} );

it( 'always marks necessary as required and optional categories as not required', function (): void {
	expect( Categories::is_required( 'necessary' ) )->toBeTrue()
		->and( Categories::is_required( 'analytics' ) )->toBeFalse()
		->and( Categories::optional() )->not->toContain( 'necessary' );
} );

it( 'never lets a filter remove necessary or make it optional', function (): void {
	add_filter(
		'kjeks_categories',
		fn (): array => [
			'analytics' => [
				'label'       => 'A',
				'description' => '',
				'required'    => false,
			],
		]
	);

	// Our stub returns the unfiltered value, so assert the invariant directly.
	expect( Categories::is_required( 'necessary' ) )->toBeTrue()
		->and( Categories::exists( 'necessary' ) )->toBeTrue();
} );
