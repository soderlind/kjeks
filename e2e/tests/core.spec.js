import { test, expect } from '@playwright/test';
import {
	openPage,
	waitForRuntime,
	bannerVisible,
	acceptAll,
	rejectAll,
	grantedCategories,
	consentRecord,
} from '../helpers/consent.js';

/**
 * Core consent flow: the banner appears for undecided visitors, Accept/Reject
 * persist the choice, a returning visitor is not re-prompted, and Global
 * Privacy Control auto-rejects.
 */
test.describe( 'Kjeks core consent flow', () => {
	test( 'shows the banner and exposes the runtime API on a first visit', async ( {
		page,
	} ) => {
		await openPage( page, '/' );
		await waitForRuntime( page );

		expect( await bannerVisible( page ) ).toBe( true );
		expect( await consentRecord( page ) ).toBeNull();

		const api = await page.evaluate( () =>
			Object.keys( window.kjeks || {} )
		);
		expect( api ).toEqual(
			expect.arrayContaining( [
				'openPreferences',
				'getConsent',
				'isGranted',
				'acceptAll',
				'rejectAll',
			] )
		);
	} );

	test( 'Reject all denies every optional category and hides the banner', async ( {
		page,
	} ) => {
		await openPage( page, '/' );
		await waitForRuntime( page );

		await rejectAll( page );

		expect( await bannerVisible( page ) ).toBe( false );
		expect( await grantedCategories( page ) ).toEqual( [] );

		const record = await consentRecord( page );
		expect( record ).not.toBeNull();
		expect( Object.values( record.c ) ).not.toContain( 1 );
	} );

	test( 'Accept all grants every optional category and persists across reloads', async ( {
		page,
	} ) => {
		await openPage( page, '/' );
		await waitForRuntime( page );

		await acceptAll( page );

		const granted = await grantedCategories( page );
		expect( granted.length ).toBeGreaterThan( 0 );
		expect( await bannerVisible( page ) ).toBe( false );

		// A returning visitor with a record is not prompted again.
		await page.reload( { waitUntil: 'domcontentloaded' } );
		await waitForRuntime( page );
		expect( await bannerVisible( page ) ).toBe( false );
		expect( await grantedCategories( page ) ).toEqual( granted );
	} );

	test( 'honors Global Privacy Control by auto-rejecting undecided visitors', async ( {
		page,
	} ) => {
		await page.addInitScript( () => {
			Object.defineProperty( navigator, 'globalPrivacyControl', {
				value: true,
				configurable: true,
			} );
		} );

		await openPage( page, '/' );
		await waitForRuntime( page );

		// GPC only applies when the site honors it; skip if disabled via filter.
		const honored = await page.evaluate(
			() => ( window.kjeksConfig || {} ).honorGpc !== false
		);
		test.skip( ! honored, 'Site does not honor GPC (kjeks_honor_gpc=false)' );

		expect( await bannerVisible( page ) ).toBe( false );
		expect( await grantedCategories( page ) ).toEqual( [] );
	} );
} );
