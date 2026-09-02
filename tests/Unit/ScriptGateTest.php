<?php
/**
 * ScriptGate tests: enqueue-based inert rewriting and inline output.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Soderlind\Kjeks\Blocking\IntegrationRegistry;
use Soderlind\Kjeks\Blocking\ScriptGate;

beforeEach(
	function (): void {
		IntegrationRegistry::reset();
		$GLOBALS['kjeks_enqueued']   = array();
		$GLOBALS['kjeks_ss_gating']  = false;

		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value ) {
				if ( 'kjeks_server_side_gating' === $hook ) {
					return $GLOBALS['kjeks_ss_gating'];
				}
				return $value;
			}
		);
		Functions\when( 'wp_enqueue_script' )->alias(
			static function ( string $handle, string $src, array $deps, $ver, $args ): void {
				$GLOBALS['kjeks_enqueued'][ $handle ] = compact( 'src', 'ver', 'args' );
			}
		);
	}
);

it( 'enqueues each external src script with a null version', function (): void {
	IntegrationRegistry::register(
		'plausible',
		array(
			'category'    => 'analytics',
			'src_scripts' => array(
				array( 'src' => 'https://plausible.io/js/script.js', 'attrs' => array( 'domain' => 'ex.test' ) ),
			),
		)
	);

	( new ScriptGate() )->enqueue_src_scripts();

	expect( $GLOBALS['kjeks_enqueued'] )->toHaveKey( 'kjeks-plausible-src-0' )
		->and( $GLOBALS['kjeks_enqueued']['kjeks-plausible-src-0']['src'] )->toBe( 'https://plausible.io/js/script.js' )
		->and( $GLOBALS['kjeks_enqueued']['kjeks-plausible-src-0']['ver'] )->toBeNull();
} );

it( 'rewrites an enqueued src handle inert with integration id and data-attr-*', function (): void {
	IntegrationRegistry::register(
		'plausible',
		array(
			'category'    => 'analytics',
			'src_scripts' => array(
				array( 'src' => 'https://plausible.io/js/script.js', 'attrs' => array( 'domain' => 'ex.test' ) ),
			),
		)
	);

	$gate = new ScriptGate();
	$gate->enqueue_src_scripts();

	$tag = '<script src="https://plausible.io/js/script.js" id="kjeks-plausible-src-0-js"></script>';
	$out = $gate->make_handle_inert( $tag, 'kjeks-plausible-src-0', 'https://plausible.io/js/script.js' );

	expect( $out )->toContain( 'type="text/plain"' )
		->toContain( 'data-kjeks-category="analytics"' )
		->toContain( 'data-kjeks-integration="plausible"' )
		->toContain( 'data-kjeks-src="https://plausible.io/js/script.js"' )
		->toContain( 'data-attr-domain="ex.test"' );
} );

it( 'rewrites a registered handle inert with only its category', function (): void {
	IntegrationRegistry::register(
		'ga',
		array( 'category' => 'marketing', 'handles' => array( 'google-analytics' ) )
	);

	$tag = '<script src="https://gtag.js" id="google-analytics-js"></script>';
	$out = ( new ScriptGate() )->make_handle_inert( $tag, 'google-analytics', 'https://gtag.js' );

	expect( $out )->toContain( 'type="text/plain"' )
		->toContain( 'data-kjeks-category="marketing"' )
		->not->toContain( 'data-kjeks-integration=' );
} );

it( 'leaves a non-gated handle untouched', function (): void {
	$tag = '<script src="https://theme.js" id="theme-js"></script>';
	$out = ( new ScriptGate() )->make_handle_inert( $tag, 'theme', 'https://theme.js' );

	expect( $out )->toBe( $tag );
} );

it( 'keeps registered handles live under server-side gating but src scripts stay inert', function (): void {
	$GLOBALS['kjeks_ss_gating'] = true;

	IntegrationRegistry::register(
		'ga',
		array(
			'category'    => 'marketing',
			'handles'     => array( 'google-analytics' ),
			'src_scripts' => array( array( 'src' => 'https://x.js', 'attrs' => array() ) ),
		)
	);

	$gate = new ScriptGate();
	$gate->enqueue_src_scripts();

	$handle_tag = '<script src="https://gtag.js" id="google-analytics-js"></script>';
	$src_tag    = '<script src="https://x.js" id="kjeks-ga-src-0-js"></script>';

	expect( $gate->make_handle_inert( $handle_tag, 'google-analytics', 'https://gtag.js' ) )->toBe( $handle_tag )
		->and( $gate->make_handle_inert( $src_tag, 'kjeks-ga-src-0', 'https://x.js' ) )->toContain( 'type="text/plain"' );
} );

it( 'prints inline snippets inert and never emits src scripts in the footer', function (): void {
	IntegrationRegistry::register(
		'inline-analytics',
		array(
			'category'    => 'analytics',
			'inline'      => array( 'console.log(1); // </script> breakout' ),
			'src_scripts' => array( array( 'src' => 'https://x.js', 'attrs' => array() ) ),
		)
	);

	ob_start();
	( new ScriptGate() )->print_inert_scripts();
	$html = (string) ob_get_clean();

	expect( $html )->toContain( 'type="text/plain"' )
		->toContain( 'data-kjeks-category="analytics"' )
		->toContain( 'data-kjeks-integration="inline-analytics-0"' )
		->not->toContain( 'data-kjeks-src=' );
} );
