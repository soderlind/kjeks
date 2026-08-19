<?php
/**
 * Pest bootstrap and shared WordPress function stubs.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;

uses()
	->beforeEach(
		function (): void {
			Monkey\setUp();
			kjeks_stub_wp_functions();
		}
	)
	->afterEach(
		function (): void {
			Monkey\tearDown();
		}
	)
	->in( 'Unit' );

/**
 * Stubs the WordPress functions the unit under test relies on.
 */
function kjeks_stub_wp_functions(): void {
	Functions\when( '__' )->returnArg( 1 );
	Functions\when( 'esc_url_raw' )->returnArg( 1 );
	Functions\when( 'esc_url' )->returnArg( 1 );
	Functions\when( 'wp_unslash' )->returnArg( 1 );
	Functions\when( 'wp_parse_url' )->alias(
		static fn ( string $url, int $component = -1 ) => parse_url( $url, $component )
	);
	Functions\when( 'sanitize_text_field' )->returnArg( 1 );
	Functions\when( 'sanitize_textarea_field' )->returnArg( 1 );
	Functions\when( 'sanitize_key' )->alias(
		static fn ( string $key ): string => strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) )
	);
	Functions\when( 'get_current_blog_id' )->justReturn( 1 );
	Functions\when( 'get_option' )->alias(
		static fn ( string $name, $default = false ) => $GLOBALS['kjeks_test_options'][ $name ] ?? $default
	);
	Functions\when( 'get_blog_option' )->alias(
		static fn ( int $id, string $name, $default = false ) => $GLOBALS['kjeks_test_options'][ $name ] ?? $default
	);
	Functions\when( 'get_site_option' )->alias(
		static fn ( string $name, $default = false ) => $GLOBALS['kjeks_test_site_options'][ $name ] ?? $default
	);
	Functions\when( 'update_site_option' )->alias(
		static function ( string $name, $value ): bool {
			$GLOBALS['kjeks_test_site_options'][ $name ] = $value;
			return true;
		}
	);
	Functions\when( 'update_option' )->alias(
		static function ( string $name, $value ): bool {
			$GLOBALS['kjeks_test_options'][ $name ] = $value;
			return true;
		}
	);
	Functions\when( 'update_blog_option' )->alias(
		static function ( int $id, string $name, $value ): bool {
			$GLOBALS['kjeks_test_options'][ $name ] = $value;
			return true;
		}
	);
	Functions\when( 'get_transient' )->justReturn( false );
	Functions\when( 'set_transient' )->justReturn( true );
	Functions\when( 'delete_transient' )->justReturn( true );

	$GLOBALS['kjeks_test_options']      = [];
	$GLOBALS['kjeks_test_site_options'] = [];
}
