/**
 * Deterministic per-subsite diff against a previous scan.
 */

/**
 * Diffs two scan results, per site, by observation identity.
 *
 * @param {object|null} previous  Previous scan result (or null for first run).
 * @param {object} current        Current scan result.
 * @returns {{ host: string, added: object[], changed: object[], removed: object[] }[]}
 */
export function diffScans( previous, current ) {
	const previousByHost = indexByHost( previous );
	const out = [];

	for ( const site of current.sites ) {
		const before = previousByHost.get( site.host );
		out.push( diffSite( site.host, before ? before.observations : [], site.observations ) );
	}

	return out;
}

function diffSite( host, beforeObs, afterObs ) {
	const before = indexObservations( beforeObs );
	const after = indexObservations( afterObs );

	const added = [];
	const changed = [];
	const removed = [];

	for ( const [ key, observation ] of after ) {
		if ( ! before.has( key ) ) {
			added.push( observation );
		} else if ( ! sameAttributes( before.get( key ), observation ) ) {
			changed.push( { from: before.get( key ), to: observation } );
		}
	}

	for ( const [ key, observation ] of before ) {
		if ( ! after.has( key ) ) {
			removed.push( observation );
		}
	}

	return {
		host,
		added: added.sort( byName ),
		changed: changed.sort( ( a, b ) => byName( a.to, b.to ) ),
		removed: removed.sort( byName ),
	};
}

function observationKey( o ) {
	return `${ o.storage_type }|${ o.name }|${ o.domain || '' }`;
}

function indexObservations( list ) {
	return new Map( ( list || [] ).map( ( o ) => [ observationKey( o ), o ] ) );
}

function indexByHost( scan ) {
	return new Map( ( scan && scan.sites ? scan.sites : [] ).map( ( s ) => [ s.host, s ] ) );
}

/**
 * Compares attributes that matter, ignoring volatile fields like triggered_by
 * ordering (already sorted) and observation timestamps.
 */
function sameAttributes( a, b ) {
	const fields = [ 'party', 'secure', 'http_only', 'same_site', 'retention', 'path' ];
	for ( const field of fields ) {
		if ( ( a[ field ] ?? null ) !== ( b[ field ] ?? null ) ) {
			return false;
		}
	}
	return JSON.stringify( a.triggered_by || [] ) === JSON.stringify( b.triggered_by || [] );
}

function byName( a, b ) {
	return observationKey( a ).localeCompare( observationKey( b ) );
}
