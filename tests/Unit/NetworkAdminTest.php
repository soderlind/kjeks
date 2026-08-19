<?php
/**
 * NetworkAdmin capability tests: single-site vs multisite.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Soderlind\Kjeks\Admin\NetworkAdmin;

it( 'requires the site-admin capability on single-site installs', function (): void {
	Functions\when( 'is_multisite' )->justReturn( false );

	expect( ( new NetworkAdmin() )->capability() )->toBe( 'manage_options' );
} );

it( 'requires a network capability on multisite', function (): void {
	Functions\when( 'is_multisite' )->justReturn( true );

	expect( ( new NetworkAdmin() )->capability() )->toBe( 'manage_network_options' );
} );
