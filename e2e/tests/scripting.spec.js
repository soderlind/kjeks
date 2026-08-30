import { test } from '@playwright/test';
import {
	openPage,
	waitForRuntime,
	expectGatingActivates,
	GATED,
} from '../helpers/consent.js';

/**
 * kjeks-scripting: a script mapped by handle is rewritten to an inert tag and
 * only runs once its category is granted. Skips when no handle-gated script is
 * present on the page (no handles mapped, or none enqueued here).
 *
 * Target page: SCRIPTING_URL (default: the site home).
 */
test.describe( 'kjeks-scripting gating', () => {
	test( 'keeps a handle-gated script inert until consent', async ( {
		page,
	} ) => {
		await openPage( page, process.env.SCRIPTING_URL || '/' );
		await waitForRuntime( page );

		await expectGatingActivates( page, GATED.scripting );
	} );
} );
