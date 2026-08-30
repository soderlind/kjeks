import { test, expect } from '@playwright/test';

/**
 * Shared helpers for the Kjeks acceptance suite.
 *
 * Interactions are locale-proof: consent is driven through the `window.kjeks`
 * runtime API and CSS classes, never button text. Gating is asserted through
 * the markup contract in banner.js — inert nodes carry
 * `data-kjeks-category` / `data-kjeks-src`, and once their category is granted
 * banner.js stamps `data-kjeks-activated="1"` and injects the live node.
 */

/** Selectors that identify each add-on's gated markup. */
export const GATED = {
	// src_scripts / inline integrations print a data-kjeks-integration id.
	social: 'script[type="text/plain"][data-kjeks-integration^="social-"]',
	google:
		'script[type="text/plain"][data-kjeks-integration^="google"], script[type="text/plain"][data-kjeks-category][data-kjeks-src*="googletagmanager"]',
	// Handle-gated tags are rewritten in place and carry no integration id.
	scripting:
		'script[type="text/plain"][data-kjeks-category][data-kjeks-src]:not([data-kjeks-integration])',
	// Iframe embeds render as a placeholder that banner.js swaps for an <iframe>.
	embed: '[data-kjeks-embed][data-kjeks-category]',
};

/**
 * Navigate to a URL, skipping the test when the site is unreachable.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} url
 */
export async function openPage( page, url ) {
	const response = await page
		.goto( url, { waitUntil: 'domcontentloaded' } )
		.catch( () => null );
	test.skip( ! response, `Site unreachable at ${ url }` );
	return response;
}

/**
 * Wait until banner.js has hydrated (window.kjeks exists). Skips if the runtime
 * never appears — e.g. the core plugin is not active on the target site.
 *
 * @param {import('@playwright/test').Page} page
 */
export async function waitForRuntime( page ) {
	const booted = await page
		.waitForFunction( () => Boolean( window.kjeks ), null, { timeout: 8000 } )
		.then( () => true )
		.catch( () => false );
	test.skip( ! booted, 'Kjeks runtime (window.kjeks) not present on the page' );
}

/** Whether the consent banner is currently shown. */
export function bannerVisible( page ) {
	return page.locator( '.kjeks-banner--visible' ).isVisible();
}

/** Grant every optional category through the runtime API. */
export async function acceptAll( page ) {
	await page.evaluate( () => window.kjeks.acceptAll() );
}

/** Deny every optional category through the runtime API. */
export async function rejectAll( page ) {
	await page.evaluate( () => window.kjeks.rejectAll() );
}

/**
 * The optional categories currently granted.
 *
 * @returns {Promise<string[]>}
 */
export function grantedCategories( page ) {
	return page.evaluate( () => Array.from( window.kjeks.getConsent() ) );
}

/** Read the persisted consent record, or null when none is stored. */
export function consentRecord( page ) {
	return page.evaluate( () => {
		const raw = window.localStorage.getItem( 'kjeks_consent' );
		return raw ? JSON.parse( raw ) : null;
	} );
}

/**
 * Assert an add-on's gating: at least one inert node exists and is inert before
 * consent, then activates after Accept all. Skips when the add-on has no gated
 * content on the page.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} selector A GATED.* selector.
 */
export async function expectGatingActivates( page, selector ) {
	const nodes = page.locator( selector );
	const count = await nodes.count();
	test.skip( count === 0, `No gated markup matching \`${ selector }\`` );

	const first = nodes.first();
	await expect(
		first,
		'gated node must be inert before consent'
	).not.toHaveAttribute( 'data-kjeks-activated', '1' );

	await acceptAll( page );

	await expect(
		first,
		'gated node must activate once its category is granted'
	).toHaveAttribute( 'data-kjeks-activated', '1', { timeout: 7000 } );
}
