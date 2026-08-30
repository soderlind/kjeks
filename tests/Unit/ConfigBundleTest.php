<?php
/**
 * ConfigBundle tests (export / apply of the portable config bundle).
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Soderlind\Kjeks\Config\ConfigBundle;

beforeEach(
	function (): void {
		$GLOBALS['kjeks_test_options']      = array();
		$GLOBALS['kjeks_test_site_options'] = array();

		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'home_url' )->justReturn( 'https://example.test' );
		Functions\when( 'network_home_url' )->justReturn( 'https://example.test' );
		// No add-on sections by default: return the core sections unchanged.
		Functions\when( 'apply_filters' )->returnArg( 2 );
	}
);

it( 'exports a schema-1 envelope with the core sections', function (): void {
	$bundle = ( new ConfigBundle() )->export();

	expect( $bundle['schema'] )->toBe( 1 )
		->and( $bundle['sections'] )->toHaveKey( 'kjeks_network_content' )
		->and( $bundle['sections']['kjeks_network_content'] )->toHaveKey( 'data' )
		->and( $bundle['sections']['kjeks_network_content']['version'] )->toBe( 1 );
} );

it( 'rejects an unsupported schema on apply', function (): void {
	expect( fn () => ( new ConfigBundle() )->apply( array( 'schema' => 99, 'sections' => array() ) ) )
		->toThrow( RuntimeException::class );
} );

it( 'applies known sections, skips unknown, and bumps the policy version', function (): void {
	$GLOBALS['kjeks_test_options']['kjeks_policy_version'] = 3;

	$result = ( new ConfigBundle() )->apply(
		array(
			'schema'   => 1,
			'sections' => array(
				'kjeks_network_content' => array( 'version' => 1, 'data' => array( 'heading' => 'Hi', 'body' => 'B', 'privacy_url' => '', 'accent' => '#000' ) ),
				'kjeks_unknown_addon'   => array( 'version' => 1, 'data' => array( 'x' => 1 ) ),
			),
		)
	);

	expect( $result['applied'] )->toContain( 'kjeks_network_content' )
		->and( $result['skipped'] )->toContain( 'kjeks_unknown_addon' )
		->and( $result['policy_version'] )->toBe( 4 )
		->and( $GLOBALS['kjeks_test_site_options']['kjeks_network_content']['heading'] )->toBe( 'Hi' );
} );

it( 'skips a section whose version is newer than this install understands', function (): void {
	$result = ( new ConfigBundle() )->apply(
		array(
			'schema'   => 1,
			'sections' => array(
				'kjeks_network_content' => array( 'version' => 99, 'data' => array( 'heading' => 'Nope' ) ),
			),
		)
	);

	expect( $result['skipped'] )->toContain( 'kjeks_network_content' )
		->and( $result['warnings'] )->not->toBe( array() )
		->and( $GLOBALS['kjeks_test_site_options'] )->not->toHaveKey( 'kjeks_network_content' );
} );

it( 'lets an add-on contribute a section through the filter', function (): void {
	$captured = null;

	Functions\when( 'apply_filters' )->alias(
		static function ( string $hook, $value ) use ( &$captured ) {
			if ( 'kjeks_config_sections' !== $hook ) {
				return $value;
			}
			$value['kjeks_addon'] = array(
				'version' => 1,
				'export'  => static fn (): array => array( 'k' => 'v' ),
				'apply'   => static function ( array $data ) use ( &$captured ): void {
					$captured = $data;
				},
			);
			return $value;
		}
	);

	$exported = ( new ConfigBundle() )->export();
	expect( $exported['sections'] )->toHaveKey( 'kjeks_addon' );

	( new ConfigBundle() )->apply(
		array(
			'schema'   => 1,
			'sections' => array(
				'kjeks_addon' => array( 'version' => 1, 'data' => array( 'k' => 'v2' ) ),
			),
		)
	);

	expect( $captured )->toBe( array( 'k' => 'v2' ) );
} );
