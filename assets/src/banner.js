/**
 * Kjeks consent runtime.
 *
 * Minimal, dependency-free. Reads window.kjeksConfig, manages the client-side
 * consent record, renders an accessible non-modal banner and preference
 * dialog, and activates inert scripts/embeds once consent is granted.
 */
import './banner.css';
import { __ } from '@wordpress/i18n';

const config = window.kjeksConfig || {};
const optional = ( config.categories || [] ).filter( ( c ) => ! c.required );

/* -------------------------------------------------------------------------- */
/* Consent record (client-side only).                                         */
/* -------------------------------------------------------------------------- */

function readRecord() {
	let raw = '';
	try {
		raw = window.localStorage.getItem( config.storageKey ) || '';
	} catch ( e ) {
		raw = '';
	}
	if ( ! raw ) {
		raw = cookieValue( config.cookieName );
	}
	if ( ! raw ) {
		return null;
	}
	let data;
	try {
		data = JSON.parse( raw );
	} catch ( e ) {
		return null;
	}
	// Compare loosely: kjeksConfig values are stringified by wp_localize_script,
	// while an injected record (e.g. from the scanner) may carry numbers.
	if (
		! data ||
		String( data.v ) !== String( config.policyVersion ) ||
		String( data.b ) !== String( config.blogId )
	) {
		return null;
	}
	return data;
}

function writeRecord( choices ) {
	const map = {};
	optional.forEach( ( c ) => {
		map[ c.slug ] = choices[ c.slug ] ? 1 : 0;
	} );
	const record = {
		v: config.policyVersion,
		t: Math.floor( Date.now() / 1000 ),
		b: config.blogId,
		c: map,
	};
	const json = JSON.stringify( record );
	const maxAge = ( config.cookieMonths || 6 ) * 30 * 24 * 60 * 60;
	let cookie =
		config.cookieName +
		'=' +
		encodeURIComponent( json ) +
		';path=/;max-age=' +
		maxAge +
		';SameSite=Lax';
	if ( config.secure ) {
		cookie += ';Secure';
	}
	document.cookie = cookie;
	try {
		window.localStorage.setItem( config.storageKey, json );
	} catch ( e ) {}
	return record;
}

function cookieValue( name ) {
	const match = document.cookie.match(
		new RegExp( '(?:^|; )' + name + '=([^;]*)' )
	);
	return match ? decodeURIComponent( match[ 1 ] ) : '';
}

function grantedSet( record ) {
	const set = new Set();
	if ( record && record.c ) {
		Object.keys( record.c ).forEach( ( slug ) => {
			if ( record.c[ slug ] === 1 ) {
				set.add( slug );
			}
		} );
	}
	return set;
}

/* -------------------------------------------------------------------------- */
/* Activation of inert scripts and embeds.                                    */
/* -------------------------------------------------------------------------- */

function activate( granted ) {
	document
		.querySelectorAll( 'script[type="text/plain"][data-kjeks-category]' )
		.forEach( ( node ) => {
			const category = node.getAttribute( 'data-kjeks-category' );
			if ( ! granted.has( category ) || node.dataset.kjeksActivated ) {
				return;
			}
			node.dataset.kjeksActivated = '1';
			const script = document.createElement( 'script' );
			const src = node.getAttribute( 'data-kjeks-src' );
			for ( const attr of node.attributes ) {
				if ( attr.name.indexOf( 'data-attr-' ) === 0 ) {
					script.setAttribute( attr.name.slice( 10 ), attr.value );
				}
			}
			if ( src ) {
				script.src = src;
			} else {
				script.text = node.textContent;
			}
			node.parentNode.insertBefore( script, node.nextSibling );
		} );

	document
		.querySelectorAll( '[data-kjeks-embed]' )
		.forEach( ( node ) => {
			const category = node.getAttribute( 'data-kjeks-category' );
			if ( ! granted.has( category ) || node.dataset.kjeksActivated ) {
				return;
			}
			loadEmbed( node );
		} );
}

function loadEmbed( node ) {
	node.dataset.kjeksActivated = '1';
	const iframe = document.createElement( 'iframe' );
	iframe.src = node.getAttribute( 'data-kjeks-src' );
	iframe.title = node.getAttribute( 'data-kjeks-title' ) || '';
	iframe.width = node.getAttribute( 'data-kjeks-width' ) || '';
	iframe.height = node.getAttribute( 'data-kjeks-height' ) || '';
	iframe.loading = 'lazy';
	iframe.style.width = '100%';
	iframe.style.height = '100%';
	iframe.setAttribute( 'frameborder', '0' );
	iframe.allowFullscreen = true;
	node.innerHTML = '';
	node.appendChild( iframe );
}

/* -------------------------------------------------------------------------- */
/* Public API + events.                                                       */
/* -------------------------------------------------------------------------- */

const listeners = { grant: [], withdraw: [] };

function emit( previous, granted ) {
	optional.forEach( ( c ) => {
		const was = previous.has( c.slug );
		const is = granted.has( c.slug );
		if ( is && ! was ) {
			dispatch( 'grant', c.slug );
			window.dispatchEvent(
				new CustomEvent( 'kjeks:granted', { detail: { category: c.slug } } )
			);
		} else if ( ! is && was ) {
			dispatch( 'withdraw', c.slug );
			window.dispatchEvent(
				new CustomEvent( 'kjeks:withdrawn', { detail: { category: c.slug } } )
			);
		}
	} );
}

function dispatch( type, category ) {
	listeners[ type ].forEach( ( entry ) => {
		if ( entry.category === category || entry.category === '*' ) {
			try {
				entry.cb( category );
			} catch ( e ) {}
		}
	} );
}

/* -------------------------------------------------------------------------- */
/* UI.                                                                         */
/* -------------------------------------------------------------------------- */

let lastFocus = null;

function el( tag, attrs, children ) {
	const node = document.createElement( tag );
	Object.keys( attrs || {} ).forEach( ( key ) => {
		if ( key === 'text' ) {
			node.textContent = attrs[ key ];
		} else if ( key === 'html' ) {
			node.innerHTML = attrs[ key ];
		} else {
			node.setAttribute( key, attrs[ key ] );
		}
	} );
	( children || [] ).forEach( ( c ) => node.appendChild( c ) );
	return node;
}

function buildBanner( root, apply ) {
	const content = config.content || {};
	const banner = el( 'div', {
		class: 'kjeks-banner',
		role: 'region',
		'aria-label': content.heading || __( 'Cookie consent', 'kjeks' ),
	} );

	banner.appendChild( el( 'h2', { class: 'kjeks-banner__title', text: content.heading || '' } ) );
	banner.appendChild( el( 'p', { class: 'kjeks-banner__body', text: content.body || '' } ) );

	if ( content.privacy_url ) {
		banner.appendChild(
			el( 'p', {
				class: 'kjeks-banner__link',
				html:
					'<a href="' +
					encodeURI( content.privacy_url ) +
					'">' +
					__( 'Privacy policy', 'kjeks' ) +
					'</a>',
			} )
		);
	}

	const actions = el( 'div', { class: 'kjeks-banner__actions' } );
	const reject = el( 'button', {
		type: 'button',
		class: 'kjeks-btn kjeks-btn--secondary',
		text: __( 'Reject all', 'kjeks' ),
	} );
	const customize = el( 'button', {
		type: 'button',
		class: 'kjeks-btn kjeks-btn--secondary',
		text: __( 'Customize', 'kjeks' ),
	} );
	const accept = el( 'button', {
		type: 'button',
		class: 'kjeks-btn kjeks-btn--primary',
		text: __( 'Accept all', 'kjeks' ),
	} );

	reject.addEventListener( 'click', () => apply( denied() ) );
	accept.addEventListener( 'click', () => apply( all() ) );
	customize.addEventListener( 'click', () => openDialog( root, apply ) );

	// Reject and accept share prominence; reject is listed first.
	actions.appendChild( reject );
	actions.appendChild( customize );
	actions.appendChild( accept );
	banner.appendChild( actions );

	return banner;
}

function buildDialog( root, apply ) {
	const dialog = el( 'div', {
		class: 'kjeks-dialog',
		role: 'dialog',
		'aria-modal': 'false',
		'aria-label': __( 'Cookie preferences', 'kjeks' ),
		tabindex: '-1',
	} );

	dialog.appendChild(
		el( 'h2', { class: 'kjeks-dialog__title', text: __( 'Cookie preferences', 'kjeks' ) } )
	);

	const tabs = buildTabs( [
		{ id: 'choices', label: __( 'Your choices', 'kjeks' ), panel: buildChoicesForm( apply ) },
		{ id: 'declaration', label: __( 'Cookie declaration', 'kjeks' ), panel: buildDeclarationPanel() },
	] );

	dialog.appendChild( tabs.tablist );
	tabs.panels.forEach( ( panel ) => dialog.appendChild( panel ) );

	return dialog;
}

function buildChoicesForm( apply ) {
	const record = readRecord();
	const granted = grantedSet( record );
	const form = el( 'form', { class: 'kjeks-dialog__form' } );
	const inputs = {};
	const declaration = config.declaration || {};

	( config.categories || [] ).forEach( ( category ) => {
		const group = el( 'fieldset', { class: 'kjeks-category' } );
		const legendRow = el( 'div', { class: 'kjeks-category__row' } );
		const input = document.createElement( 'input' );
		input.type = 'checkbox';
		input.id = 'kjeks-cat-' + category.slug;
		input.checked = category.required || granted.has( category.slug );
		input.disabled = category.required;
		inputs[ category.slug ] = input;

		const label = el( 'label', {
			class: 'kjeks-category__label',
			for: input.id,
			text: category.label,
		} );

		legendRow.appendChild( input );
		legendRow.appendChild( label );
		group.appendChild( legendRow );
		group.appendChild( el( 'p', { class: 'kjeks-category__desc', text: category.description } ) );

		const trackers = declaration[ category.slug ] || [];
		if ( trackers.length ) {
			const details = el( 'details', { class: 'kjeks-category__details' } );
			details.appendChild(
				el( 'summary', { text: __( 'View trackers', 'kjeks' ) } )
			);
			const list = el( 'ul', { class: 'kjeks-category__trackers' } );
			trackers.forEach( ( t ) => {
				list.appendChild(
					el( 'li', {
						text:
							t.name +
							( t.provider ? ' — ' + t.provider : '' ) +
							( t.purpose ? ' (' + t.purpose + ')' : '' ),
					} )
				);
			} );
			details.appendChild( list );
			group.appendChild( details );
		}

		form.appendChild( group );
	} );

	const actions = el( 'div', { class: 'kjeks-dialog__actions' } );
	const save = el( 'button', {
		type: 'submit',
		class: 'kjeks-btn kjeks-btn--primary',
		text: __( 'Save choices', 'kjeks' ),
	} );
	const rejectAll = el( 'button', {
		type: 'button',
		class: 'kjeks-btn kjeks-btn--secondary',
		text: __( 'Reject all', 'kjeks' ),
	} );
	const acceptAll = el( 'button', {
		type: 'button',
		class: 'kjeks-btn kjeks-btn--secondary',
		text: __( 'Accept all', 'kjeks' ),
	} );

	rejectAll.addEventListener( 'click', () => apply( denied() ) );
	acceptAll.addEventListener( 'click', () => apply( all() ) );
	form.addEventListener( 'submit', ( e ) => {
		e.preventDefault();
		const choices = {};
		optional.forEach( ( c ) => {
			choices[ c.slug ] = inputs[ c.slug ].checked;
		} );
		apply( choices );
	} );

	actions.appendChild( rejectAll );
	actions.appendChild( acceptAll );
	actions.appendChild( save );
	form.appendChild( actions );

	return form;
}

/**
 * The cookie declaration, rendered from the reviewed inventory already
 * localised into kjeksConfig — the same data the shortcode uses.
 */
function buildDeclarationPanel() {
	const wrap = el( 'div', { class: 'kjeks-declaration' } );
	const declaration = config.declaration || {};
	let any = false;

	( config.categories || [] ).forEach( ( category ) => {
		const trackers = declaration[ category.slug ] || [];
		if ( ! trackers.length ) {
			return;
		}
		any = true;

		const section = el( 'section', { class: 'kjeks-declaration__category' } );
		section.appendChild( el( 'h3', { text: category.label } ) );
		if ( category.description ) {
			section.appendChild( el( 'p', { text: category.description } ) );
		}

		const table = el( 'table', { class: 'kjeks-declaration__table' } );
		const headRow = el( 'tr' );
		[
			__( 'Name', 'kjeks' ),
			__( 'Provider', 'kjeks' ),
			__( 'Purpose', 'kjeks' ),
			__( 'Type', 'kjeks' ),
			__( 'Party', 'kjeks' ),
			__( 'Retention', 'kjeks' ),
		].forEach( ( heading ) => headRow.appendChild( el( 'th', { scope: 'col', text: heading } ) ) );
		const thead = el( 'thead' );
		thead.appendChild( headRow );
		table.appendChild( thead );

		const tbody = el( 'tbody' );
		trackers.forEach( ( t ) => {
			const row = el( 'tr' );
			const party = t.party === 'first' ? __( 'First-party', 'kjeks' ) : __( 'Third-party', 'kjeks' );
			[ t.name, t.provider, t.purpose, t.storage_type, party, t.retention ].forEach( ( value ) =>
				row.appendChild( el( 'td', { text: value || '' } ) )
			);
			tbody.appendChild( row );
		} );
		table.appendChild( tbody );
		section.appendChild( table );
		wrap.appendChild( section );
	} );

	if ( ! any ) {
		wrap.appendChild(
			el( 'p', {
				class: 'kjeks-declaration__empty',
				text: __( 'No trackers have been documented yet.', 'kjeks' ),
			} )
		);
	}

	return wrap;
}

/**
 * ARIA tabs with roving tabindex and arrow-key navigation.
 *
 * @param {{ id: string, label: string, panel: HTMLElement }[]} items
 */
function buildTabs( items ) {
	const tablist = el( 'div', {
		class: 'kjeks-tabs',
		role: 'tablist',
		'aria-label': __( 'Cookie preferences sections', 'kjeks' ),
	} );
	const tabs = [];
	const panels = [];

	items.forEach( ( item, index ) => {
		const tabId = 'kjeks-tab-' + item.id;
		const panelId = 'kjeks-panel-' + item.id;
		const tab = el( 'button', {
			type: 'button',
			class: 'kjeks-tab',
			id: tabId,
			role: 'tab',
			'aria-controls': panelId,
			'aria-selected': index === 0 ? 'true' : 'false',
			tabindex: index === 0 ? '0' : '-1',
			text: item.label,
		} );

		item.panel.setAttribute( 'id', panelId );
		item.panel.setAttribute( 'role', 'tabpanel' );
		item.panel.setAttribute( 'aria-labelledby', tabId );
		item.panel.setAttribute( 'tabindex', '0' );
		item.panel.classList.add( 'kjeks-tabpanel' );
		if ( index !== 0 ) {
			item.panel.hidden = true;
		}

		tabs.push( tab );
		panels.push( item.panel );
		tablist.appendChild( tab );
	} );

	function activate( index ) {
		tabs.forEach( ( tab, i ) => {
			const selected = i === index;
			tab.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
			tab.tabIndex = selected ? 0 : -1;
			panels[ i ].hidden = ! selected;
		} );
	}

	tabs.forEach( ( tab, index ) => {
		tab.addEventListener( 'click', () => {
			activate( index );
			tab.focus();
		} );
		tab.addEventListener( 'keydown', ( e ) => {
			let next = null;
			if ( e.key === 'ArrowRight' || e.key === 'ArrowDown' ) {
				next = ( index + 1 ) % tabs.length;
			} else if ( e.key === 'ArrowLeft' || e.key === 'ArrowUp' ) {
				next = ( index - 1 + tabs.length ) % tabs.length;
			} else if ( e.key === 'Home' ) {
				next = 0;
			} else if ( e.key === 'End' ) {
				next = tabs.length - 1;
			}
			if ( next !== null ) {
				e.preventDefault();
				activate( next );
				tabs[ next ].focus();
			}
		} );
	} );

	return { tablist, panels };
}

function all() {
	const c = {};
	optional.forEach( ( cat ) => {
		c[ cat.slug ] = true;
	} );
	return c;
}

function denied() {
	const c = {};
	optional.forEach( ( cat ) => {
		c[ cat.slug ] = false;
	} );
	return c;
}

/* -------------------------------------------------------------------------- */
/* Orchestration.                                                             */
/* -------------------------------------------------------------------------- */

let root;

function apply( choices ) {
	const previous = grantedSet( readRecord() );
	const record = writeRecord( choices );
	const granted = grantedSet( record );
	activate( granted );
	emit( previous, granted );
	closeBanner();
	closeDialog();
	ensureTrigger();
}

function showBanner() {
	closeBanner();
	const banner = buildBanner( root, apply );
	banner.classList.add( 'kjeks-banner--visible' );
	root.appendChild( banner );
	root.__banner = banner;
}

function closeBanner() {
	if ( root.__banner ) {
		root.__banner.remove();
		root.__banner = null;
	}
}

function openDialog() {
	closeDialog();
	lastFocus = document.activeElement;
	const backdrop = el( 'div', { class: 'kjeks-dialog-backdrop' } );
	const dialog = buildDialog( root, apply );
	backdrop.appendChild( dialog );
	root.appendChild( backdrop );
	root.__dialog = backdrop;
	dialog.focus();
	trapFocus( dialog );
}

function closeDialog() {
	if ( root.__dialog ) {
		root.__dialog.remove();
		root.__dialog = null;
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	}
}

function trapFocus( dialog ) {
	dialog.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Escape' ) {
			closeDialog();
			return;
		}
		if ( e.key !== 'Tab' ) {
			return;
		}
		const focusable = dialog.querySelectorAll(
			'button, input, summary, a[href], [tabindex]:not([tabindex="-1"])'
		);
		if ( ! focusable.length ) {
			return;
		}
		const first = focusable[ 0 ];
		const last = focusable[ focusable.length - 1 ];
		if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	} );
}

function ensureTrigger() {
	if ( document.querySelector( '.kjeks-trigger' ) ) {
		return;
	}
	const trigger = el( 'button', {
		type: 'button',
		class: 'kjeks-trigger',
		'aria-label': __( 'Cookie settings', 'kjeks' ),
		text: __( 'Cookie settings', 'kjeks' ),
	} );
	trigger.addEventListener( 'click', () => openDialog() );
	root.appendChild( trigger );
}

/* -------------------------------------------------------------------------- */
/* Boot.                                                                       */
/* -------------------------------------------------------------------------- */

function boot() {
	root = document.getElementById( 'kjeks-root' );
	if ( ! root ) {
		return;
	}
	root.hidden = false;

	window.kjeks = {
		openPreferences: () => openDialog(),
		getConsent: () => grantedSet( readRecord() ),
		isGranted: ( category ) => grantedSet( readRecord() ).has( category ),
		acceptAll: () => apply( all() ),
		rejectAll: () => apply( denied() ),
		withdrawAll: () => apply( denied() ),
		onGrant: ( category, cb ) => listeners.grant.push( { category, cb } ),
		onWithdraw: ( category, cb ) => listeners.withdraw.push( { category, cb } ),
	};

	document.addEventListener( 'click', ( e ) => {
		const opener = e.target.closest( '[data-kjeks-open]' );
		if ( opener ) {
			e.preventDefault();
			openDialog();
		}
		const embedLoad = e.target.closest( '[data-kjeks-embed-load]' );
		if ( embedLoad ) {
			const wrap = embedLoad.closest( '[data-kjeks-embed]' );
			if ( wrap ) {
				const category = wrap.getAttribute( 'data-kjeks-category' );
				apply( { ...objFromSet( grantedSet( readRecord() ) ), [ category ]: true } );
			}
		}
	} );

	const record = readRecord();

	if ( record ) {
		activate( grantedSet( record ) );
		ensureTrigger();
		return;
	}

	// Global Privacy Control: auto-reject for undecided visitors; explicit accept still wins.
	if ( config.honorGpc && navigator.globalPrivacyControl === true ) {
		activate( new Set() );
		ensureTrigger();
		return;
	}

	// Show the banner until a choice is made, unless the network disabled it.
	// While the banner is visible the "Cookie settings" trigger is redundant, so
	// it is only added when the banner is not shown.
	if ( config.bannerDefaultVisible !== false ) {
		showBanner();
	} else {
		ensureTrigger();
	}
}

function objFromSet( set ) {
	const obj = {};
	optional.forEach( ( c ) => {
		obj[ c.slug ] = set.has( c.slug );
	} );
	return obj;
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
