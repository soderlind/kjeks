import { test } from '@playwright/test';
import {
	openPage,
	waitForRuntime,
	expectGatingActivates,
	GATED,
} from '../helpers/consent.js';

/**
 * kjeks-google: the Google tag / consent-mode script is emitted inert and only
 * runs once the visitor grants its category. Skips when the page carries no
 * gated Google markup (e.g. the tag is not configured on the target site).
 *
 * Target page: GOOGLE_URL (default: the site home).
 */
test.describe( 'kjeks-google gating', () => {
	test( 'keeps the Google tag inert until consent', async ( { page } ) => {
		await openPage( page, process.env.GOOGLE_URL || '/' );
		await waitForRuntime( page );

		await expectGatingActivates( page, GATED.google );
	} );
} );
