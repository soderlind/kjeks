import { defineConfig } from '@playwright/test';

/**
 * Playwright config for the "no tracking before consent" checks.
 *
 * Set BASE_URL to a running site (defaults to the local dev network). Tests
 * are skipped automatically when the site is unreachable.
 */
export default defineConfig( {
	testDir: './tests',
	timeout: 30000,
	use: {
		baseURL: process.env.BASE_URL || 'http://plugins.local/',
		headless: true,
	},
	reporter: [ [ 'list' ] ],
} );
