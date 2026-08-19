<?php
/**
 * Scan validation and import tests.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

use Soderlind\Kjeks\Inventory\TrackerRegistry;
use Soderlind\Kjeks\Scan\ScanImporter;
use Soderlind\Kjeks\Scan\ScanValidator;

it( 'rejects a payload without a blog id or observations', function (): void {
	$result = ( new ScanValidator() )->validate( array( 'observations' => 'nope' ) );

	expect( $result['valid'] )->toBeFalse()
		->and( $result['errors'] )->not->toBeEmpty();
} );

it( 'coerces every observation to unreviewed and never necessary', function (): void {
	$result = ( new ScanValidator() )->validate(
		array(
			'blog_id'      => 2,
			'observations' => array(
				array( 'name' => '_ga', 'storage_type' => 'cookie', 'domain' => 'example.com', 'category' => 'necessary' ),
			),
		)
	);

	expect( $result['valid'] )->toBeTrue()
		->and( $result['blog_id'] )->toBe( 2 )
		->and( $result['trackers'] )->toHaveCount( 1 )
		->and( $result['trackers'][0]->reviewed )->toBeFalse()
		->and( $result['trackers'][0]->category )->toBe( 'marketing' )
		->and( $result['trackers'][0]->source )->toBe( 'scan' );
} );

it( 'skips nameless observations with a warning', function (): void {
	$result = ( new ScanValidator() )->validate(
		array(
			'blog_id'      => 1,
			'observations' => array(
				array( 'storage_type' => 'cookie' ),
				array( 'name' => 'ok' ),
			),
		)
	);

	expect( $result['trackers'] )->toHaveCount( 1 )
		->and( $result['warnings'] )->not->toBeEmpty();
} );

it( 'aggregates observations into the network registry, unreviewed', function (): void {
	$GLOBALS['kjeks_test_site_options'] = array();

	$result   = ( new ScanValidator() )->validate(
		array(
			'blog_id'      => 5,
			'observations' => array(
				array( 'name' => 'fbp', 'storage_type' => 'cookie', 'domain' => 'facebook.com', 'party' => 'third' ),
			),
		)
	);
	$imported = ( new ScanImporter() )->import( 5, $result['trackers'] );

	$stored = ( new TrackerRegistry() )->trackers();

	expect( $imported )->toBe( 1 )
		->and( $stored )->toHaveCount( 1 );

	$tracker = array_values( $stored )[0];
	expect( $tracker->reviewed )->toBeFalse()
		->and( $tracker->sites )->toBe( array( 5 ) );
} );

it( 'collapses the same cookie from multiple sites into one entry', function (): void {
	$GLOBALS['kjeks_test_site_options'] = array();

	$obs = ( new ScanValidator() )->validate(
		array(
			'blog_id'      => 1,
			'observations' => array( array( 'name' => '_ga', 'storage_type' => 'cookie', 'domain' => 'ga.com' ) ),
		)
	)['trackers'];

	( new ScanImporter() )->import( 1, $obs );
	$added_again = ( new ScanImporter() )->import( 2, $obs );

	$stored = ( new TrackerRegistry() )->trackers();

	expect( $stored )->toHaveCount( 1 )
		->and( $added_again )->toBe( 0 );

	$tracker = array_values( $stored )[0];
	expect( $tracker->sites )->toBe( array( 1, 2 ) );
} );

it( 'preserves a network review when the cookie is re-observed', function (): void {
	$GLOBALS['kjeks_test_site_options']['kjeks_network_trackers'] = array(
		'_ga-cookie-ga-com' => array(
			'id'       => '_ga-cookie-ga-com',
			'name'     => '_ga',
			'category' => 'analytics',
			'reviewed' => true,
			'sites'    => array( 1 ),
		),
	);

	$obs = ( new ScanValidator() )->validate(
		array(
			'blog_id'      => 2,
			'observations' => array( array( 'id' => '_ga-cookie-ga-com', 'name' => '_ga', 'storage_type' => 'cookie', 'domain' => 'ga.com' ) ),
		)
	)['trackers'];

	( new ScanImporter() )->import( 2, $obs );

	$tracker = array_values( ( new TrackerRegistry() )->trackers() )[0];

	expect( $tracker->reviewed )->toBeTrue()
		->and( $tracker->category )->toBe( 'analytics' )
		->and( $tracker->sites )->toBe( array( 1, 2 ) );
} );
