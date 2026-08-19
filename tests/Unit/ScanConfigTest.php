<?php
/**
 * ScanConfig builder tests.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Soderlind\Kjeks\Scan\ScanConfig;

beforeEach(
	function (): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'switch_to_blog' )->justReturn( true );
		Functions\when( 'restore_current_blog' )->justReturn( true );
		Functions\when( 'get_sites' )->justReturn( array( 1, 2, 3 ) );
		Functions\when( 'home_url' )->alias(
			static fn ( string $path = '/' ): string => 'https://site' . ( $GLOBALS['kjeks_current_blog'] ?? 1 ) . '.example' . $path
		);
		// Track the "current" blog via switch_to_blog for home_url.
		Functions\when( 'switch_to_blog' )->alias(
			static function ( int $id ): bool {
				$GLOBALS['kjeks_current_blog'] = $id;
				return true;
			}
		);
	}
);

it( 'builds one entry per site with url, blog id, policy version and paths', function (): void {
	$config = ( new ScanConfig() )->build( array(), array( '/', '/about' ) );

	expect( $config['sites'] )->toHaveCount( 3 )
		->and( $config['sites'][0]['blog_id'] )->toBe( 1 )
		->and( $config['sites'][0]['url'] )->toBe( 'https://site1.example/' )
		->and( $config['sites'][0]['policy_version'] )->toBe( 1 )
		->and( $config['sites'][0]['paths'] )->toBe( array( '/', '/about' ) );
} );

it( 'filters to the included blog ids', function (): void {
	$config = ( new ScanConfig() )->build( array( 2 ), array( '/' ) );

	expect( $config['sites'] )->toHaveCount( 1 )
		->and( $config['sites'][0]['blog_id'] )->toBe( 2 );
} );

it( 'defaults paths to root when none are given', function (): void {
	$config = ( new ScanConfig() )->build( array( 1 ), array() );

	expect( $config['sites'][0]['paths'] )->toBe( array( '/' ) );
} );
