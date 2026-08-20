<?php
/**
 * PrivacyPageDeclaration opt-in and dedup tests.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

use Soderlind\Kjeks\Inventory\NetworkStore;
use Soderlind\Kjeks\Privacy\PrivacyPageDeclaration;

beforeEach(
	function (): void {
		$GLOBALS['kjeks_test_site_options'] = array();
	}
);

it( 'does not render when the opt-in is off', function (): void {
	$subject = new PrivacyPageDeclaration( new NetworkStore() );

	expect( $subject->should_render( 'Some privacy content.' ) )->toBeFalse();
} );

it( 'renders when the opt-in is on and no declaration is present', function (): void {
	( new NetworkStore() )->set_privacy_page_declaration( true );
	$subject = new PrivacyPageDeclaration( new NetworkStore() );

	expect( $subject->should_render( 'Some privacy content.' ) )->toBeTrue();
} );

it( 'skips rendering when a declaration is already in the content (dedup)', function (): void {
	( new NetworkStore() )->set_privacy_page_declaration( true );
	$subject = new PrivacyPageDeclaration( new NetworkStore() );

	$content = 'Intro. <div class="kjeks-declaration">table</div>';

	expect( $subject->should_render( $content ) )->toBeFalse();
} );
