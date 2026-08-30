<?php
/**
 * RegistryTransfer tests (config bundle registry normalize + merge).
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

use Soderlind\Kjeks\Config\RegistryTransfer;
use Soderlind\Kjeks\Inventory\TrackerRegistry;

beforeEach(
	function (): void {
		$GLOBALS['kjeks_test_site_options'] = array();
	}
);

/**
 * Seeds the registry option directly with raw tracker arrays.
 *
 * @param array<int, array<string, mixed>> $trackers Raw entries.
 */
function kjeks_seed_registry( array $trackers ): void {
	$keyed = array();
	foreach ( $trackers as $tracker ) {
		$keyed[ $tracker['id'] ] = $tracker;
	}
	$GLOBALS['kjeks_test_site_options']['kjeks_network_trackers'] = $keyed;
}

it( 'exports only reviewed and manual entries, stripping observations', function (): void {
	kjeks_seed_registry(
		array(
			array( 'id' => 'ga', 'name' => 'GA', 'category' => 'analytics', 'reviewed' => true, 'sites' => array( 2, 3 ), 'last_observed' => 111 ),
			array( 'id' => 'manual-x', 'name' => 'X', 'category' => 'marketing', 'reviewed' => false, 'source' => 'manual', 'sites' => array( 4 ) ),
			array( 'id' => 'pending', 'name' => 'Pending', 'category' => 'marketing', 'reviewed' => false, 'source' => '', 'sites' => array( 5 ) ),
		)
	);

	$export = ( new RegistryTransfer() )->export();
	$ids    = array_column( $export, 'id' );

	expect( $ids )->toContain( 'ga' )
		->and( $ids )->toContain( 'manual-x' )
		->and( $ids )->not->toContain( 'pending' );

	foreach ( $export as $entry ) {
		expect( $entry['sites'] )->toBe( array() )
			->and( $entry['last_observed'] )->toBe( 0 );
	}
} );

it( 'merges classification into an existing entry while keeping its observations', function (): void {
	kjeks_seed_registry(
		array(
			array( 'id' => 'ga', 'name' => 'GA', 'category' => 'marketing', 'reviewed' => false, 'sites' => array( 7, 9 ), 'last_observed' => 555 ),
		)
	);

	( new RegistryTransfer() )->merge(
		array(
			array( 'id' => 'ga', 'name' => 'GA', 'category' => 'analytics', 'reviewed' => true, 'provider' => 'Google', 'sites' => array() ),
		)
	);

	$saved = ( new TrackerRegistry() )->trackers()['ga'];

	expect( $saved->category )->toBe( 'analytics' )
		->and( $saved->reviewed )->toBeTrue()
		->and( $saved->provider )->toBe( 'Google' )
		->and( $saved->sites )->toBe( array( 7, 9 ) )
		->and( $saved->last_observed )->toBe( 555 );
} );

it( 'adds an unknown incoming entry network-wide', function (): void {
	kjeks_seed_registry( array() );

	$result = ( new RegistryTransfer() )->merge(
		array(
			array( 'id' => 'newcookie', 'name' => 'New', 'category' => 'preferences', 'reviewed' => true ),
		)
	);

	$saved = ( new TrackerRegistry() )->trackers()['newcookie'];

	expect( $result )->toBe( array( 'added' => 1, 'updated' => 0 ) )
		->and( $saved->sites )->toBe( array() )
		->and( $saved->category )->toBe( 'preferences' );
} );
