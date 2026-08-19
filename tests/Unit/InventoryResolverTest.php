<?php
/**
 * InventoryResolver tests: per-site scope and network authority.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

use Soderlind\Kjeks\Inventory\InventoryResolver;
use Soderlind\Kjeks\Inventory\TrackerRegistry;

/**
 * @param array<string, array<string, mixed>> $trackers Network trackers keyed by id.
 */
function kjeks_seed_network( array $trackers ): void {
	$GLOBALS['kjeks_test_site_options']['kjeks_network_trackers'] = $trackers;
	$GLOBALS['kjeks_test_options']                               = array();
}

it( 'includes a reviewed network tracker on all sites when it has no site scope', function (): void {
	kjeks_seed_network(
		array(
			'ga' => array( 'id' => 'ga', 'name' => 'GA', 'category' => 'analytics', 'reviewed' => true ),
		)
	);

	$resolver = new InventoryResolver( new TrackerRegistry(), 1 );

	expect( $resolver->all() )->toHaveKey( 'ga' )
		->and( $resolver->reviewed() )->toHaveKey( 'ga' );
} );

it( 'scopes a network tracker to only the sites where it was observed', function (): void {
	kjeks_seed_network(
		array(
			'ga' => array( 'id' => 'ga', 'name' => 'GA', 'category' => 'analytics', 'reviewed' => true, 'sites' => array( 2, 3 ) ),
		)
	);

	$on_site1 = ( new InventoryResolver( new TrackerRegistry(), 1 ) )->scoped_network_trackers();
	$on_site2 = ( new InventoryResolver( new TrackerRegistry(), 2 ) )->scoped_network_trackers();

	expect( $on_site1 )->not->toHaveKey( 'ga' )
		->and( $on_site2 )->toHaveKey( 'ga' );
} );

it( 'serves a reviewed network tracker as-is (network is authoritative)', function (): void {
	kjeks_seed_network(
		array(
			'ga' => array( 'id' => 'ga', 'name' => 'GA', 'category' => 'analytics', 'reviewed' => true ),
		)
	);

	$trackers = ( new InventoryResolver( new TrackerRegistry(), 1 ) )->all();

	expect( $trackers )->toHaveKey( 'ga' )
		->and( $trackers['ga']->category )->toBe( 'analytics' );
} );

it( 'keeps only reviewed trackers in the public inventory', function (): void {
	kjeks_seed_network(
		array(
			'ga'      => array( 'id' => 'ga', 'name' => 'GA', 'category' => 'analytics', 'reviewed' => true ),
			'pending' => array( 'id' => 'pending', 'name' => 'Pending', 'category' => 'marketing', 'reviewed' => false ),
		)
	);

	$resolver = new InventoryResolver( new TrackerRegistry(), 1 );

	expect( $resolver->all() )->toHaveKeys( array( 'ga', 'pending' ) )
		->and( $resolver->reviewed() )->toHaveKey( 'ga' )
		->and( $resolver->reviewed() )->not->toHaveKey( 'pending' );
} );

it( 'derives one identity for the same cookie', function (): void {
	$id = \Soderlind\Kjeks\Inventory\TrackerIdentity::for( '_ga', 'cookie', 'ga.com' );

	expect( $id )->toBe( \Soderlind\Kjeks\Inventory\Tracker::slug( '_ga-cookie-ga.com' ) )
		->and( $id )->not->toBe( '' );
} );
