#!/usr/bin/env node
/**
 * Kjeks discovery scanner CLI.
 *
 * Usage:
 *   node src/cli.js --config config.json --out scan
 *   node src/cli.js --url https://example.com --blog-id 1 --out scan
 *   node src/cli.js --config-url https://net.example/wp-json/kjeks/v1/scan-config --out scan
 *   node src/cli.js --config-url <url> --overlay overlay.json --out scan
 *   node src/cli.js --config config.json --endpoint wss://…browser-run…  (Browser Run)
 *
 * --config-url fetches the site list from WordPress (auth: KJEKS_USER +
 * KJEKS_APP_PASSWORD env). --overlay merges repo-side paths/scenarios by blog_id.
 *
 * Writes one deterministic JSON file per site to <out>/<host>.json and prints a
 * per-subsite diff against any previous file of the same name.
 */

import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join } from 'node:path';
import { runScan } from './scan.js';
import { diffScans } from './diff.js';

async function main() {
	const args = parseArgs( process.argv.slice( 2 ) );
	const config = await loadConfig( args );
	const outDir = args.out || 'scan';

	await mkdir( outDir, { recursive: true } );

	const result = await runScan( config, { endpoint: args.endpoint } );
	let anyChanged = false;

	for ( const site of result.sites ) {
		const file = join( outDir, `${ site.host }.json` );
		const previous = existsSync( file ) ? JSON.parse( await readFile( file, 'utf8' ) ) : null;

		const [ siteDiff ] = diffScans( previous, { sites: [ site ] } );

		const single = { generated_at: result.generated_at, sites: [ site ] };
		await writeFile( file, stableStringify( single ) + '\n', 'utf8' );

		printDiff( site.host, siteDiff );

		if ( siteDiff && ( siteDiff.added.length || siteDiff.changed.length || siteDiff.removed.length ) ) {
			anyChanged = true;
		}
	}

	// Non-zero exit if any subsite changed, so CI can flag review.
	process.exitCode = anyChanged ? 1 : 0;
}

function printDiff( host, diff ) {
	if ( ! diff ) {
		return;
	}
	const total = diff.added.length + diff.changed.length + diff.removed.length;
	if ( total === 0 ) {
		process.stdout.write( `${ host }: no changes\n` );
		return;
	}
	process.stdout.write(
		`${ host }: ${ diff.added.length } new, ${ diff.changed.length } changed, ${ diff.removed.length } removed\n`
	);
	for ( const o of diff.added ) {
		process.stdout.write( `  + ${ o.storage_type } ${ o.name }${ o.domain ? ' @ ' + o.domain : '' }\n` );
	}
	for ( const o of diff.removed ) {
		process.stdout.write( `  - ${ o.storage_type } ${ o.name }${ o.domain ? ' @ ' + o.domain : '' }\n` );
	}
}

async function loadConfig( args ) {
	if ( args.config ) {
		return JSON.parse( await readFile( args.config, 'utf8' ) );
	}
	if ( args[ 'config-url' ] ) {
		return applyOverlay( await fetchConfig( args[ 'config-url' ] ), args.overlay );
	}
	if ( args.url ) {
		return {
			sites: [
				{
					url: args.url,
					blog_id: args[ 'blog-id' ] ? Number( args[ 'blog-id' ] ) : 1,
					paths: args.path ? [].concat( args.path ) : [ '/' ],
				},
			],
		};
	}
	throw new Error( 'Provide --config <file>, --config-url <url>, or --url <url>.' );
}

/**
 * Fetches the scanner config from the WordPress REST endpoint.
 *
 * Authenticates with an application password via HTTP Basic auth, supplied
 * through KJEKS_USER / KJEKS_APP_PASSWORD in the environment.
 */
async function fetchConfig( url ) {
	const user = process.env.KJEKS_USER;
	const password = process.env.KJEKS_APP_PASSWORD;
	if ( ! user || ! password ) {
		throw new Error( '--config-url requires KJEKS_USER and KJEKS_APP_PASSWORD in the environment.' );
	}
	const auth = 'Basic ' + Buffer.from( `${ user }:${ password }` ).toString( 'base64' );
	const response = await fetch( url, { headers: { authorization: auth } } );
	if ( ! response.ok ) {
		throw new Error( `config-url returned ${ response.status }` );
	}
	return response.json();
}

/**
 * Merges a repo-side overlay (paths / scenarios keyed by blog_id) into the
 * site list fetched from REST. Operator intent stays in the repo; the live
 * site list stays authoritative.
 */
async function applyOverlay( config, overlayPath ) {
	if ( ! overlayPath ) {
		return config;
	}
	const overlay = JSON.parse( await readFile( overlayPath, 'utf8' ) );
	const byBlog = new Map( ( overlay.sites || [] ).map( ( s ) => [ s.blog_id, s ] ) );

	config.sites = ( config.sites || [] ).map( ( site ) => {
		const extra = byBlog.get( site.blog_id );
		if ( ! extra ) {
			return site;
		}
		return {
			...site,
			paths: extra.paths && extra.paths.length ? extra.paths : site.paths,
			scenarios: extra.scenarios || site.scenarios,
		};
	} );

	return config;
}

function parseArgs( argv ) {
	const args = {};
	for ( let i = 0; i < argv.length; i++ ) {
		const token = argv[ i ];
		if ( token.startsWith( '--' ) ) {
			const key = token.slice( 2 );
			const next = argv[ i + 1 ];
			if ( ! next || next.startsWith( '--' ) ) {
				args[ key ] = true;
			} else {
				args[ key ] = args[ key ] ? [].concat( args[ key ], next ) : next;
				i++;
			}
		}
	}
	return args;
}

/**
 * Stable JSON: object keys sorted recursively so output is byte-stable.
 */
function stableStringify( value ) {
	return JSON.stringify( sortKeys( value ), null, '\t' );
}

function sortKeys( value ) {
	if ( Array.isArray( value ) ) {
		return value.map( sortKeys );
	}
	if ( value && typeof value === 'object' ) {
		return Object.keys( value )
			.sort()
			.reduce( ( acc, key ) => {
				acc[ key ] = sortKeys( value[ key ] );
				return acc;
			}, {} );
	}
	return value;
}

main().catch( ( error ) => {
	process.stderr.write( `kjeks-scan: ${ error.message }\n` );
	process.exitCode = 2;
} );
