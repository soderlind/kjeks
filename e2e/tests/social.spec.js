import { test } from '@playwright/test';
import {
	openPage,
	waitForRuntime,
	expectGatingActivates,
	GATED,
} from '../helpers/consent.js';

/**
 * kjeks-social: a provider widget script (Twitter/X, Instagram, …) is stripped
 * from the content and re-emitted inert, so it only runs after consent. Skips
 * when the page has no social embeds.
 *
 * Target page: SOCIAL_URL (default: a `social` page on the dev subsite).
 */
test.describe( 'kjeks-social gating', () => {
	test( 'keeps a social widget script inert until consent', async ( {
		page,
	} ) => {
		await openPage( page, process.env.SOCIAL_URL || 'social/' );
		await waitForRuntime( page );

		await expectGatingActivates( page, GATED.social );
	} );
} );
