<?php
/**
 * NetworkStore settings tests.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

use Soderlind\Kjeks\Inventory\NetworkStore;

beforeEach(
	function (): void {
		$GLOBALS['kjeks_test_site_options'] = array();
	}
);

it( 'defaults the banner to visible when the setting is unset', function (): void {
	expect( ( new NetworkStore() )->banner_default_visible() )->toBeTrue();
} );

it( 'persists the banner-visible setting off and reads it back', function (): void {
	( new NetworkStore() )->set_banner_default_visible( false );

	expect( ( new NetworkStore() )->banner_default_visible() )->toBeFalse();
} );

it( 'treats banner visibility and uninstall opt-in as independent settings', function (): void {
	$store = new NetworkStore();
	$store->set_delete_on_uninstall( true );
	$store->set_banner_default_visible( false );

	expect( $store->delete_on_uninstall() )->toBeTrue()
		->and( $store->banner_default_visible() )->toBeFalse();
} );
