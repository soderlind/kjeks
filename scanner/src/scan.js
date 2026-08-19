/**
 * Scan orchestration.
 *
 * For each site and each consent state, opens a FRESH browser context, injects
 * the consent record for that state, visits the configured paths, and collects
 * observations. Produces a deterministic result per site.
 */

import { chromium } from 'playwright';
import { COOKIE_NAME, STORAGE_KEY, consentStates, encodeRecord } from './consent.js';
import { collect, registrableDomain } from './collect.js';

const IGNORED_COOKIES = new Set( [ COOKIE_NAME ] );

/**
 * @param {object} config  Parsed scanner config.
 * @param {object} [options]
 * @param {string} [options.endpoint]  CDP endpoint (e.g. Cloudflare Browser Run). Omit for local Chromium.
 */
export async function runScan( config, options = {} ) {
	const browser = options.endpoint
		? await chromium.connectOverCDP( options.endpoint )
		: await chromium.launch( { headless: true } );

	try {
		const sites = [];
		for ( const site of config.sites ) {
			sites.push( await scanSite( browser, site, config ) );
		}
		return {
			// generated_at is excluded from diffs; kept for provenance only.
			generated_at: Math.floor( Date.now() / 1000 ),
			sites: sites.sort( ( a, b ) => a.host.localeCompare( b.host ) ),
		};
	} finally {
		await browser.close();
	}
}

async function scanSite( browser, site, config ) {
	const baseUrl = site.url;
	const host = new URL( baseUrl ).hostname;
	const firstPartyDomain = registrableDomain( host );
	const paths = site.paths && site.paths.length ? site.paths : [ '/' ];
	const blogId = site.blog_id || 1;
	const policyVersion = site.policy_version || 1;

	const states = [];
	for ( const state of consentStates() ) {
		const context = await browser.newContext();

		if ( state.choices !== null ) {
			const record = encodeRecord( state.choices, policyVersion, blogId, 0 );
			const json = JSON.stringify( record );
			await context.addCookies( [
				{
					name: COOKIE_NAME,
					value: encodeURIComponent( json ),
					domain: host,
					path: '/',
					sameSite: 'Lax',
				},
			] );
			await context.addInitScript(
				( { key, value } ) => {
					try {
						window.localStorage.setItem( key, value );
					} catch ( e ) {}
				},
				{ key: STORAGE_KEY, value: json }
			);
		}

		const page = await context.newPage();
		const collected = await collect( page, context, { firstPartyDomain, paths, baseUrl } );

		if ( site.scenarios ) {
			await runScenarios( page, site.scenarios );
		}

		states.push( { state: state.id, ...normalizeState( collected, firstPartyDomain ) } );
		await context.close();
	}

	return {
		host,
		url: baseUrl,
		blog_id: blogId,
		states: states.sort( ( a, b ) => a.state.localeCompare( b.state ) ),
		observations: deriveObservations( states ),
	};
}

async function runScenarios( page, scenarios ) {
	for ( const scenario of scenarios ) {
		for ( const step of scenario.steps || [] ) {
			if ( step.action === 'click' && step.selector ) {
				await page.click( step.selector, { timeout: 5000 } ).catch( () => {} );
			}
			if ( step.action === 'wait' && step.ms ) {
				await page.waitForTimeout( step.ms );
			}
		}
	}
}

/**
 * Deterministically normalizes one state's collected data.
 */
function normalizeState( collected, firstPartyDomain ) {
	const cookies = collected.cookies
		.filter( ( c ) => ! IGNORED_COOKIES.has( c.name ) )
		.sort( byKey( ( c ) => `${ c.name }|${ c.domain }|${ c.path }` ) );

	const thirdPartyHosts = uniqueSorted(
		collected.requests.filter( ( r ) => r.party === 'third' ).map( ( r ) => r.host )
	);

	const beacons = uniqueSorted(
		collected.requests.filter( ( r ) => r.beacon ).map( ( r ) => stripQuery( r.url ) )
	);

	return {
		cookies,
		localStorage: uniqueSorted( collected.localStorage ).filter( ( k ) => k !== STORAGE_KEY ),
		sessionStorage: uniqueSorted( collected.sessionStorage ),
		indexedDB: uniqueSorted( collected.indexedDB ),
		thirdPartyHosts,
		beacons,
		scripts: uniqueSorted( collected.scripts.map( stripQuery ) ),
		iframes: uniqueSorted( collected.iframes.map( stripQuery ) ),
		redirects: collected.redirects.map( ( r ) => ( { from: stripQuery( r.from ), to: stripQuery( r.to ) } ) ),
	};
}

/**
 * Builds a flat, import-ready observation list from all states.
 */
function deriveObservations( states ) {
	const map = new Map();

	const add = ( key, observation, stateId ) => {
		if ( ! map.has( key ) ) {
			map.set( key, { ...observation, triggered_by: [] } );
		}
		const existing = map.get( key );
		if ( ! existing.triggered_by.includes( stateId ) ) {
			existing.triggered_by.push( stateId );
		}
	};

	for ( const state of states ) {
		for ( const c of state.cookies ) {
			add( `cookie|${ c.name }|${ c.domain }`, {
				name: c.name,
				storage_type: 'cookie',
				domain: c.domain,
				path: c.path,
				party: c.party,
				secure: c.secure,
				http_only: c.http_only,
				same_site: c.same_site,
				retention: c.session ? 'session' : 'persistent',
			}, state.state );
		}
		for ( const key of state.localStorage ) {
			add( `localstorage|${ key }`, { name: key, storage_type: 'localstorage', party: 'first' }, state.state );
		}
		for ( const key of state.sessionStorage ) {
			add( `sessionstorage|${ key }`, { name: key, storage_type: 'sessionstorage', party: 'first' }, state.state );
		}
		for ( const db of state.indexedDB ) {
			add( `indexeddb|${ db }`, { name: db, storage_type: 'indexeddb', party: 'first' }, state.state );
		}
		for ( const host of state.thirdPartyHosts ) {
			add( `script|${ host }`, { name: host, storage_type: 'script', domain: host, party: 'third' }, state.state );
		}
		for ( const url of state.beacons ) {
			add( `pixel|${ url }`, { name: url, storage_type: 'pixel', party: 'third' }, state.state );
		}
	}

	for ( const observation of map.values() ) {
		observation.triggered_by.sort();
	}

	return Array.from( map.values() ).sort(
		byKey( ( o ) => `${ o.storage_type }|${ o.name }|${ o.domain || '' }` )
	);
}

function byKey( keyFn ) {
	return ( a, b ) => keyFn( a ).localeCompare( keyFn( b ) );
}

function uniqueSorted( list ) {
	return Array.from( new Set( list.filter( Boolean ) ) ).sort();
}

function stripQuery( url ) {
	try {
		const u = new URL( url );
		return u.origin + u.pathname;
	} catch ( e ) {
		return url;
	}
}
