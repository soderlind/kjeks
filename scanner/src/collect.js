/**
 * Per-state forensic collection.
 *
 * Attaches network listeners, drives a page, and reads all client-side storage
 * surfaces. HttpOnly cookies are only available via context.cookies() (CDP),
 * never document.cookie — which is exactly why a real, CDP-capable browser is
 * required (see docs/adr/0005).
 */

/**
 * Approximate registrable domain (eTLD+1). Good enough for first/third-party
 * classification; documented as a known limitation.
 *
 * @param {string} host
 */
export function registrableDomain( host ) {
	const labels = String( host || '' ).split( '.' ).filter( Boolean );
	if ( labels.length <= 2 ) {
		return labels.join( '.' );
	}
	return labels.slice( -2 ).join( '.' );
}

function isBeacon( request ) {
	const type = request.resourceType();
	if ( type === 'beacon' || type === 'ping' ) {
		return true;
	}
	const url = request.url();
	return /\.(gif|png)(\?|$)/i.test( url ) && /(pixel|track|beacon|collect|pageview)/i.test( url );
}

/**
 * Collects observations for a single already-configured page.
 *
 * @param {import('playwright').Page} page
 * @param {import('playwright').BrowserContext} context
 * @param {object} options
 * @param {string} options.firstPartyDomain  Registrable domain of the site.
 * @param {string[]} options.paths            Paths to visit (relative or absolute).
 * @param {string} options.baseUrl            Site base URL.
 */
export async function collect( page, context, { firstPartyDomain, paths, baseUrl } ) {
	const requests = [];
	const redirects = [];
	const setCookies = [];

	page.on( 'request', ( request ) => {
		let host = '';
		try {
			host = new URL( request.url() ).hostname;
		} catch ( e ) {
			host = '';
		}
		const party = registrableDomain( host ) === firstPartyDomain ? 'first' : 'third';
		requests.push( {
			url: request.url(),
			host,
			method: request.method(),
			resourceType: request.resourceType(),
			party,
			beacon: isBeacon( request ),
		} );
	} );

	page.on( 'response', ( response ) => {
		const headers = response.headers();
		if ( headers[ 'set-cookie' ] ) {
			setCookies.push( { url: response.url(), setCookie: headers[ 'set-cookie' ] } );
		}
		if ( response.status() >= 300 && response.status() < 400 && headers.location ) {
			redirects.push( { from: response.url(), to: headers.location } );
		}
	} );

	for ( const path of paths ) {
		const target = path.startsWith( 'http' ) ? path : new URL( path, baseUrl ).toString();
		await page.goto( target, { waitUntil: 'networkidle', timeout: 30000 } ).catch( () => {} );
	}

	const cookies = await context.cookies();
	const storage = await page.evaluate( readStorage );

	return {
		cookies: cookies.map( ( c ) => ( {
			name: c.name,
			domain: c.domain,
			path: c.path,
			secure: c.secure,
			http_only: c.httpOnly,
			same_site: c.sameSite,
			session: c.expires === -1,
			party: registrableDomain( ( c.domain || '' ).replace( /^\./, '' ) ) === firstPartyDomain ? 'first' : 'third',
		} ) ),
		documentCookie: storage.documentCookie,
		localStorage: storage.localStorage,
		sessionStorage: storage.sessionStorage,
		indexedDB: storage.indexedDB,
		scripts: storage.scripts,
		iframes: storage.iframes,
		requests,
		redirects,
		setCookies,
	};
}

/* Runs in the page context. */
function readStorage() {
	const names = ( store ) => {
		try {
			return Object.keys( store );
		} catch ( e ) {
			return [];
		}
	};
	return Promise.resolve()
		.then( async () => {
			let idb = [];
			try {
				if ( indexedDB.databases ) {
					idb = ( await indexedDB.databases() ).map( ( d ) => d.name ).filter( Boolean );
				}
			} catch ( e ) {
				idb = [];
			}
			return {
				documentCookie: ( document.cookie || '' )
					.split( ';' )
					.map( ( c ) => c.split( '=' )[ 0 ].trim() )
					.filter( Boolean ),
				localStorage: names( window.localStorage ),
				sessionStorage: names( window.sessionStorage ),
				indexedDB: idb,
				scripts: Array.from( document.querySelectorAll( 'script[src]' ) ).map( ( s ) => s.src ),
				iframes: Array.from( document.querySelectorAll( 'iframe[src]' ) ).map( ( f ) => f.src ),
			};
		} );
}
