import { test, expect } from '@playwright/test';
import { openPage, waitForRuntime, acceptAll, GATED } from '../helpers/consent.js';

/**
 * kjeks-embeds: iframe providers (YouTube, Vimeo, …) render as a consent
 * placeholder and no provider iframe exists until consent is granted.
 *
 * Target page: EMBEDS_URL (default: the `embeds` page on the dev subsite).
 */
test.describe( 'kjeks-embeds gating', () => {
	test( 'holds the provider iframe until consent, then loads it', async ( {
		page,
	} ) => {
		await openPage( page, process.env.EMBEDS_URL || 'embeds/' );
		await waitForRuntime( page );

		const placeholder = page.locator( GATED.embed ).first();
		const count = await page.locator( GATED.embed ).count();
		test.skip( count === 0, 'No gated iframe embeds on the target page' );

		// Before consent: a placeholder, but no iframe reaching the provider.
		await expect( placeholder ).not.toHaveAttribute(
			'data-kjeks-activated',
			'1'
		);
		expect( await placeholder.locator( 'iframe' ).count() ).toBe( 0 );

		await acceptAll( page );

		await expect( placeholder ).toHaveAttribute(
			'data-kjeks-activated',
			'1'
		);
		await expect( placeholder.locator( 'iframe' ) ).toHaveCount( 1 );
	} );
} );
