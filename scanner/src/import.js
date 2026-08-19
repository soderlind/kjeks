#!/usr/bin/env node
/**
 * Posts scan results to the Kjeks REST import endpoint.
 *
 * Reads one or more scan JSON files and imports each site's observations as
 * UNREVIEWED. Authentication uses a WordPress application password via HTTP
 * Basic auth — supply it through the environment, never on the command line or
 * in the repo.
 *
 * Usage:
 *   KJEKS_USER=admin KJEKS_APP_PASSWORD='xxxx xxxx xxxx' \
 *     node src/import.js --site https://network.example.com scan/*.json
 */

import { readFile } from 'node:fs/promises';

async function main() {
	const args = process.argv.slice( 2 );
	const siteIndex = args.indexOf( '--site' );
	if ( siteIndex === -1 ) {
		throw new Error( 'Provide --site <network-base-url>.' );
	}
	const base = args[ siteIndex + 1 ];
	const files = args.filter( ( a, i ) => i !== siteIndex && i !== siteIndex + 1 && ! a.startsWith( '--' ) );

	const user = process.env.KJEKS_USER;
	const password = process.env.KJEKS_APP_PASSWORD;
	if ( ! user || ! password ) {
		throw new Error( 'Set KJEKS_USER and KJEKS_APP_PASSWORD in the environment.' );
	}

	const auth = 'Basic ' + Buffer.from( `${ user }:${ password }` ).toString( 'base64' );
	const endpoint = new URL( '/wp-json/kjeks/v1/import', base ).toString();

	for ( const file of files ) {
		const scan = JSON.parse( await readFile( file, 'utf8' ) );
		for ( const site of scan.sites || [] ) {
			const response = await fetch( endpoint, {
				method: 'POST',
				headers: { 'content-type': 'application/json', authorization: auth },
				body: JSON.stringify( {
					blog_id: site.blog_id,
					observations: site.observations,
				} ),
			} );
			const body = await response.json().catch( () => ( {} ) );
			if ( ! response.ok ) {
				process.stderr.write( `Import failed for blog ${ site.blog_id }: ${ response.status } ${ JSON.stringify( body ) }\n` );
				process.exitCode = 1;
				continue;
			}
			process.stdout.write( `blog ${ site.blog_id }: imported ${ body.imported } unreviewed observation(s)\n` );
		}
	}
}

main().catch( ( error ) => {
	process.stderr.write( `kjeks-import: ${ error.message }\n` );
	process.exitCode = 2;
} );
