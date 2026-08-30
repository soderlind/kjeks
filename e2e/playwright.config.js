import { defineConfig } from '@playwright/test';

/**
 * Acceptance tests for Kjeks core + add-ons against a running WordPress site.
 *
 * Point BASE_URL at a site that has Kjeks (and the add-ons under test) active.
 * Defaults to the local multisite subsite used for development. Per-add-on
 * pages can be overridden with EMBEDS_URL / GOOGLE_URL / SCRIPTING_URL /
 * SOCIAL_URL; each add-on spec skips itself when its gated markup is absent, so
 * the suite stays green on installs where an add-on has no content yet.
 */
export default defineConfig( {
	testDir: './tests',
	timeout: 30000,
	expect: { timeout: 7000 },
	fullyParallel: true,
	use: {
		baseURL: process.env.BASE_URL || 'http://plugins.local/subsite29/',
		headless: true,
		trace: 'on-first-retry',
	},
	reporter: [ [ 'list' ], [ 'html', { open: 'never' } ] ],
} );
