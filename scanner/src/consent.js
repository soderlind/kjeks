/**
 * Consent-state matrix and the shared consent-record schema.
 *
 * Mirrors the PHP `Soderlind\Kjeks\Consent\ConsentSchema` wire format so the
 * scanner can drive each consent state deterministically by injecting the
 * record directly, rather than clicking the banner UI.
 */

export const COOKIE_NAME = 'kjeks_consent';
export const STORAGE_KEY = 'kjeks_consent';

// Must match Categories::optional() on the PHP side.
export const OPTIONAL_CATEGORIES = [ 'preferences', 'analytics', 'marketing' ];

/**
 * Builds the ordered list of consent states to scan for one site.
 *
 * @returns {{ id: string, choices: Record<string, boolean> | null }[]}
 */
export function consentStates() {
	const states = [
		// Before any choice: no record injected at all.
		{ id: 'before-choice', choices: null },
		{ id: 'reject-all', choices: denied() },
	];

	// Each optional category granted on its own.
	for ( const category of OPTIONAL_CATEGORIES ) {
		states.push( { id: `only-${ category }`, choices: { ...denied(), [ category ]: true } } );
	}

	states.push( { id: 'accept-all', choices: accepted() } );
	return states;
}

export function denied() {
	return Object.fromEntries( OPTIONAL_CATEGORIES.map( ( c ) => [ c, false ] ) );
}

export function accepted() {
	return Object.fromEntries( OPTIONAL_CATEGORIES.map( ( c ) => [ c, true ] ) );
}

/**
 * Encodes a consent record in the shared wire format.
 *
 * @param {Record<string, boolean>} choices  Optional-category choices.
 * @param {number} version  Policy version.
 * @param {number} blogId   Blog id.
 * @param {number} time     Unix seconds.
 */
export function encodeRecord( choices, version, blogId, time ) {
	const c = {};
	for ( const category of OPTIONAL_CATEGORIES ) {
		c[ category ] = choices[ category ] ? 1 : 0;
	}
	return { v: version, t: time, b: blogId, c };
}
