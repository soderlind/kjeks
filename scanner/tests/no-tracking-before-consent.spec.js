import { test, expect } from '@playwright/test';
import { registrableDomain } from '../src/collect.js';

/**
 * Confirms that no optional cookies, storage, or third-party requests occur
 * before the visitor makes a consent choice.
 *
 * These run against a live site (BASE_URL). They assert the consent *mechanism*
 * fails closed; they cannot prove the absence of all tracking (see ADR notes).
 */

const BASE_URL = process.env.BASE_URL || 'http://plugins.local/';

test.describe( 'before consent', () => {
	test( 'no third-party requests fire before a choice', async ( { page, baseURL } ) => {
		const origin = new URL( baseURL || BASE_URL );
		const firstParty = registrableDomain( origin.hostname );
		const thirdPartyRequests = [];

		page.on( 'request', ( request ) => {
			let host = '';
			try {
				host = new URL( request.url() ).hostname;
			} catch ( e ) {
				host = '';
			}
			if ( host && registrableDomain( host ) !== firstParty ) {
				thirdPartyRequests.push( request.url() );
			}
		} );

		await page.goto( '/', { waitUntil: 'networkidle' } );

		expect( thirdPartyRequests, `unexpected third-party requests: ${ thirdPartyRequests.join( ', ' ) }` ).toEqual( [] );
	} );

	test( 'no non-essential cookies or localStorage before a choice', async ( { page, context, baseURL } ) => {
		const origin = new URL( baseURL || BASE_URL );
		const firstParty = registrableDomain( origin.hostname );

		await page.goto( '/', { waitUntil: 'networkidle' } );

		const cookies = await context.cookies();
		const thirdPartyCookies = cookies.filter(
			( c ) => registrableDomain( ( c.domain || '' ).replace( /^\./, '' ) ) !== firstParty
		);
		expect( thirdPartyCookies, 'third-party cookies set before consent' ).toEqual( [] );

		const localStorageKeys = await page.evaluate( () => {
			try {
				return Object.keys( window.localStorage );
			} catch ( e ) {
				return [];
			}
		} );
		// Only the consent record itself may be present, and only after a choice.
		expect(
			localStorageKeys.filter( ( k ) => k !== 'kjeks_consent' ),
			`unexpected localStorage keys: ${ localStorageKeys.join( ', ' ) }`
		).toEqual( [] );
	} );

	test( 'inert gated scripts do not execute before a choice', async ( { page } ) => {
		await page.goto( '/', { waitUntil: 'networkidle' } );

		// Any script the plugin gated must still be type="text/plain".
		const executedGated = await page.evaluate( () =>
			Array.from(
				document.querySelectorAll( 'script[data-kjeks-category]' )
			).filter( ( s ) => s.type !== 'text/plain' ).length
		);
		expect( executedGated, 'a gated script executed before consent' ).toBe( 0 );
	} );
} );
