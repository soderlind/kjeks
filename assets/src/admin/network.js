/**
 * Kjeks network admin — aggregated cookie review.
 *
 * One row per cookie across the whole network, with search, status filtering,
 * and bulk review so a network admin classifies each cookie once.
 */
import './admin.css';
import { render, useState, useEffect, useMemo, createElement as h } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf, _n } from '@wordpress/i18n';
import {
	Panel,
	PanelBody,
	SearchControl,
	SelectControl,
	CheckboxControl,
	TextControl,
	TextareaControl,
	ToggleControl,
	Button,
	Notice,
	Spinner,
	Dropdown,
} from '@wordpress/components';

const settings = window.kjeksNetwork || {};
apiFetch.use( apiFetch.createNonceMiddleware( settings.nonce ) );

function App() {
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ config, setConfig ] = useState( null );
	const [ search, setSearch ] = useState( '' );
	const [ filter, setFilter ] = useState( 'all' );
	const [ selected, setSelected ] = useState( () => new Set() );
	const [ removed, setRemoved ] = useState( () => new Set() );
	const [ bulkCategory, setBulkCategory ] = useState( 'analytics' );
	const [ add, setAdd ] = useState( { name: '', provider: '', category: 'analytics' } );
	const [ scanKeyBusy, setScanKeyBusy ] = useState( false );

	useEffect( () => {
		apiFetch( { url: settings.restUrl } )
			.then( ( data ) => {
				setConfig( normalize( data ) );
				setLoading( false );
			} )
			.catch( () => {
				setNotice( { type: 'error', text: __( 'Failed to load configuration.', 'kjeks' ) } );
				setLoading( false );
			} );
	}, [] );

	const categoryOptions = useMemo(
		() =>
			config
				? config.categories.map( ( c ) => ( { label: c.label, value: c.slug } ) )
				: [],
		[ config ]
	);

	const visible = useMemo( () => {
		if ( ! config ) {
			return [];
		}
		const q = search.trim().toLowerCase();
		return config.trackers.filter( ( t ) => {
			if ( removed.has( t.id ) ) {
				return false;
			}
			if ( filter === 'pending' && t.reviewed ) {
				return false;
			}
			if ( filter === 'reviewed' && ! t.reviewed ) {
				return false;
			}
			if ( q && ! ( t.name + ' ' + t.provider ).toLowerCase().includes( q ) ) {
				return false;
			}
			return true;
		} );
	}, [ config, search, filter, removed ] );

	const counts = useMemo( () => {
		const live = config ? config.trackers.filter( ( t ) => ! removed.has( t.id ) ) : [];
		return {
			all: live.length,
			pending: live.filter( ( t ) => ! t.reviewed ).length,
			reviewed: live.filter( ( t ) => t.reviewed ).length,
		};
	}, [ config, removed ] );

	if ( loading ) {
		return h( Spinner );
	}
	if ( ! config ) {
		return notice ? h( Notice, { status: notice.type, isDismissible: false }, notice.text ) : null;
	}

	const updateTracker = ( id, changes ) => {
		setConfig( {
			...config,
			trackers: config.trackers.map( ( t ) => ( t.id === id ? { ...t, ...changes } : t ) ),
		} );
	};

	const toggleSelected = ( id, on ) => {
		const next = new Set( selected );
		if ( on ) {
			next.add( id );
		} else {
			next.delete( id );
		}
		setSelected( next );
	};

	const selectAllVisible = ( on ) => {
		const next = new Set( selected );
		visible.forEach( ( t ) => ( on ? next.add( t.id ) : next.delete( t.id ) ) );
		setSelected( next );
	};

	const applyBulk = ( markReviewed ) => {
		setConfig( {
			...config,
			trackers: config.trackers.map( ( t ) =>
				selected.has( t.id )
					? { ...t, category: bulkCategory, reviewed: markReviewed ? true : t.reviewed }
					: t
			),
		} );
		setSelected( new Set() );
	};

	const removeTracker = ( id ) => {
		const next = new Set( removed );
		next.add( id );
		setRemoved( next );
	};

	const save = () => {
		setSaving( true );
		const reviews = {};
		config.trackers.forEach( ( t ) => {
			if ( ! removed.has( t.id ) ) {
				reviews[ t.id ] = { category: t.category, reviewed: !! t.reviewed };
			}
		} );

		apiFetch( {
			url: settings.restUrl,
			method: 'POST',
			data: {
				reviews,
				remove: Array.from( removed ),
				add: add.name.trim() ? add : undefined,
				content: config.content,
				deleteOnUninstall: config.deleteOnUninstall,
				bannerDefaultVisible: config.bannerDefaultVisible,
				privacyPageDeclaration: config.privacyPageDeclaration,
			},
		} )
			.then( ( data ) => {
				setConfig( normalize( data ) );
				setRemoved( new Set() );
				setAdd( { name: '', provider: '', category: 'analytics' } );
				setNotice( { type: 'success', text: __( 'Saved.', 'kjeks' ) } );
				setSaving( false );
			} )
			.catch( () => {
				setNotice( { type: 'error', text: __( 'Save failed.', 'kjeks' ) } );
				setSaving( false );
			} );
	};

	// Generates or clears the scanner key immediately, preserving other unsaved edits.
	const runScanKeyAction = ( action ) => {
		setScanKeyBusy( true );
		apiFetch( {
			url: settings.restUrl,
			method: 'POST',
			data: { scanKeyAction: action },
		} )
			.then( ( data ) => {
				setConfig( ( prev ) => ( { ...prev, scanKey: data.scanKey || '' } ) );
				setNotice( {
					type: 'success',
					text: 'clear' === action ? __( 'Scanner key cleared.', 'kjeks' ) : __( 'Scanner key generated.', 'kjeks' ),
				} );
				setScanKeyBusy( false );
			} )
			.catch( () => {
				setNotice( { type: 'error', text: __( 'Scanner key update failed.', 'kjeks' ) } );
				setScanKeyBusy( false );
			} );
	};

	const copyScanKey = () => {
		if ( config.scanKey && navigator.clipboard ) {
			navigator.clipboard.writeText( config.scanKey );
			setNotice( { type: 'success', text: __( 'Key copied to clipboard.', 'kjeks' ) } );
		}
	};

	const filterButton = ( value, label ) =>
		h(
			Button,
			{
				variant: filter === value ? 'primary' : 'secondary',
				onClick: () => setFilter( value ),
			},
			label,
			h( 'span', { className: 'kjeks-network__count' }, String( counts[ value ] ) )
		);

	const saveButton = h(
		Button,
		{ variant: 'primary', isBusy: saving, disabled: saving, onClick: save },
		__( 'Save changes', 'kjeks' )
	);

	const view = settings.view || 'cookies';

	return h(
		'div',
		{ className: 'kjeks-network' },
		'cookies' === view &&
			h(
				'p',
				{ className: 'kjeks-network__summary' },
				sprintf(
					/* translators: 1: reviewed count, 2: pending count. */
					__( '%1$d reviewed · %2$d pending', 'kjeks' ),
					config.trackers.filter( ( t ) => t.reviewed && ! removed.has( t.id ) ).length,
					config.trackers.filter( ( t ) => ! t.reviewed && ! removed.has( t.id ) ).length
				)
			),
		notice &&
			h( Notice, { status: notice.type, onRemove: () => setNotice( null ) }, notice.text ),

		renderView( {
			cookies: h(
				'div',
				{ className: 'kjeks-network__cookies' },
				h(
					Panel,
					{},
					h(
						PanelBody,
						{ title: __( 'Discovered cookies', 'kjeks' ), initialOpen: true },
						h(
							'div',
							{ className: 'kjeks-network__toolbar' },
							h( SearchControl, {
								value: search,
								onChange: setSearch,
								placeholder: __( 'Search name or provider…', 'kjeks' ),
								__nextHasNoMarginBottom: true,
							} ),
							h(
								'div',
								{ className: 'kjeks-network__filters' },
								filterButton( 'all', __( 'All', 'kjeks' ) ),
								filterButton( 'pending', __( 'Pending', 'kjeks' ) ),
								filterButton( 'reviewed', __( 'Reviewed', 'kjeks' ) )
							)
						),

						selected.size > 0 &&
							h(
								'div',
								{ className: 'kjeks-network__bulk' },
								h(
									'span',
									{},
									sprintf(
										/* translators: %d: number of selected cookies. */
									_n( '%d selected', '%d selected', selected.size, 'kjeks' ),
									selected.size
								)
							),
							h( SelectControl, {
								label: __( 'Category', 'kjeks' ),
								hideLabelFromVision: true,
								value: bulkCategory,
								options: categoryOptions,
								onChange: setBulkCategory,
								__nextHasNoMarginBottom: true,
							} ),
							h(
								Button,
								{ variant: 'primary', onClick: () => applyBulk( true ) },
								__( 'Set category & mark reviewed', 'kjeks' )
							),
							h(
								Button,
								{ variant: 'secondary', onClick: () => applyBulk( false ) },
								__( 'Set category only', 'kjeks' )
							),
							h(
								Button,
								{ variant: 'tertiary', onClick: () => setSelected( new Set() ) },
								__( 'Clear', 'kjeks' )
							)
						),

					h( TrackerTable, {
						rows: visible,
						categoryOptions,
						selected,
						siteNames: config.siteNames,
						onToggleSelect: toggleSelected,
						onSelectAll: selectAllVisible,
						onChange: updateTracker,
						onRemove: removeTracker,
					} )
				)
			),
			saveButton
			),

			banner: h(
				'div',
				{ className: 'kjeks-network__banner' },
				h(
					Panel,
					{},
					h(
						PanelBody,
						{ title: __( 'Banner defaults', 'kjeks' ), initialOpen: true },
						h( ToggleControl, {
							label: __( 'Show the consent banner until a visitor makes a choice', 'kjeks' ),
							help: __( 'On by default. When off, the banner is not shown automatically; visitors open it from a “Cookie settings” link, and optional categories stay denied until a choice is made.', 'kjeks' ),
							checked: config.bannerDefaultVisible !== false,
							onChange: ( v ) => setConfig( { ...config, bannerDefaultVisible: v } ),
							__nextHasNoMarginBottom: true,
						} ),
						h( TextControl, {
							label: __( 'Heading', 'kjeks' ),
							value: config.content.heading || '',
							onChange: ( v ) => setConfig( { ...config, content: { ...config.content, heading: v } } ),
							__nextHasNoMarginBottom: true,
						} ),
						h( TextareaControl, {
							label: __( 'Body', 'kjeks' ),
							value: config.content.body || '',
							onChange: ( v ) => setConfig( { ...config, content: { ...config.content, body: v } } ),
							__nextHasNoMarginBottom: true,
						} ),
						h( TextControl, {
							label: __( 'Privacy policy URL', 'kjeks' ),
							type: 'url',
							value: config.content.privacy_url || '',
							onChange: ( v ) => setConfig( { ...config, content: { ...config.content, privacy_url: v } } ),
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				saveButton
			),

			settings: h(
				'div',
				{ className: 'kjeks-network__settings' },
				h(
					Panel,
					{},
					h(
						PanelBody,
						{ title: __( 'Scanner key', 'kjeks' ), initialOpen: true },
						h(
							'p',
							{ className: 'kjeks-network__hint' },
							__( 'Authenticates the discovery scanner (CI) against the scan-config and import endpoints via the X-Kjeks-Key header — useful when a proxy strips the Authorization header. Store it as the KJEKS_SCAN_KEY secret in your scan workflow.', 'kjeks' )
						),
						config.scanKey
							? h(
								'div',
								{ className: 'kjeks-scan-key' },
								h( TextControl, {
									label: __( 'Current key', 'kjeks' ),
									value: config.scanKey,
									readOnly: true,
									onChange: () => {},
									__nextHasNoMarginBottom: true,
								} ),
								h(
									'div',
									{ className: 'kjeks-scan-key__actions' },
									h( Button, { variant: 'secondary', onClick: copyScanKey }, __( 'Copy', 'kjeks' ) ),
									h(
										Button,
										{ variant: 'secondary', isBusy: scanKeyBusy, disabled: scanKeyBusy, onClick: () => runScanKeyAction( 'generate' ) },
										__( 'Regenerate', 'kjeks' )
									),
									h(
										Button,
										{ variant: 'tertiary', isDestructive: true, disabled: scanKeyBusy, onClick: () => runScanKeyAction( 'clear' ) },
										__( 'Clear', 'kjeks' )
									)
								)
							)
							: h(
								Button,
								{ variant: 'secondary', isBusy: scanKeyBusy, disabled: scanKeyBusy, onClick: () => runScanKeyAction( 'generate' ) },
								__( 'Generate scanner key', 'kjeks' )
							)
					),
					h(
						PanelBody,
						{ title: __( 'Advanced', 'kjeks' ), initialOpen: true },
						h( ToggleControl, {
							label: __( 'Delete all Kjeks data on uninstall', 'kjeks' ),
							checked: !! config.deleteOnUninstall,
							onChange: ( v ) => setConfig( { ...config, deleteOnUninstall: v } ),
							__nextHasNoMarginBottom: true,
						} ),
						h( ToggleControl, {
							label: __( 'Show the cookie declaration on the privacy policy page', 'kjeks' ),
							help: __( 'Appends the live cookie table to the site\u2019s privacy policy page, unless the Cookie declaration block or [kjeks_cookie_declaration] shortcode is already used there.', 'kjeks' ),
							checked: !! config.privacyPageDeclaration,
							onChange: ( v ) => setConfig( { ...config, privacyPageDeclaration: v } ),
							__nextHasNoMarginBottom: true,
						} ),
						h( 'h3', {}, __( 'Add a network-wide cookie', 'kjeks' ) ),
						h( TextControl, {
							label: __( 'Name', 'kjeks' ),
							value: add.name,
							onChange: ( v ) => setAdd( { ...add, name: v } ),
							__nextHasNoMarginBottom: true,
						} ),
						h( TextControl, {
							label: __( 'Provider', 'kjeks' ),
							value: add.provider,
							onChange: ( v ) => setAdd( { ...add, provider: v } ),
							__nextHasNoMarginBottom: true,
						} ),
						h( SelectControl, {
							label: __( 'Category', 'kjeks' ),
							value: add.category,
							options: categoryOptions,
							onChange: ( v ) => setAdd( { ...add, category: v } ),
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				saveButton
			),
		} )
	);
}

/**
 * Selects the body for the active view. The active view is chosen server-side by
 * the PHP tab shell and passed in via `kjeksNetwork.view` ('cookies' by default).
 *
 * `bodies` maps a view name ('cookies' | 'banner' | 'settings') to its ReactNode.
 */
function renderView( bodies ) {
	const active = settings.view || 'cookies';
	return bodies[ active ] || bodies.cookies;
}

function TrackerTable( { rows, categoryOptions, selected, siteNames, onToggleSelect, onSelectAll, onChange, onRemove } ) {
	if ( rows.length === 0 ) {
		return h( 'p', { className: 'kjeks-network__empty' }, __( 'No cookies match. Run the scanner and import to populate this list.', 'kjeks' ) );
	}

	const allSelected = rows.every( ( r ) => selected.has( r.id ) );

	return h(
		'table',
		{ className: 'wp-list-table widefat striped kjeks-network__table' },
		h(
			'thead',
			{},
			h(
				'tr',
				{},
				h( 'td', { className: 'check-column' }, h( CheckboxControl, {
					checked: allSelected,
					onChange: onSelectAll,
					'aria-label': __( 'Select all', 'kjeks' ),
					__nextHasNoMarginBottom: true,
				} ) ),
				h( 'th', {}, __( 'Cookie', 'kjeks' ) ),
				h( 'th', {}, __( 'Type', 'kjeks' ) ),
				h( 'th', {}, __( 'Party', 'kjeks' ) ),
				settings.isMultisite !== false && h( 'th', {}, __( 'Sites', 'kjeks' ) ),
				h( 'th', {}, __( 'Category', 'kjeks' ) ),
				h( 'th', {}, __( 'Reviewed', 'kjeks' ) ),
				h( 'th', {}, __( 'Remove', 'kjeks' ) )
			)
		),
		h(
			'tbody',
			{},
			rows.map( ( t ) =>
				h(
					'tr',
					{ key: t.id },
					h( 'td', { className: 'check-column' }, h( CheckboxControl, {
						checked: selected.has( t.id ),
						onChange: ( on ) => onToggleSelect( t.id, on ),
						'aria-label': t.name || t.id,
						__nextHasNoMarginBottom: true,
					} ) ),
					h(
						'td',
						{},
						h( 'strong', {}, t.name || t.id ),
						t.provider && h( 'div', { className: 'kjeks-network__provider' }, t.provider ),
						t.domain && h( 'div', { className: 'kjeks-network__domain' }, t.domain )
					),
					h( 'td', {}, t.storage_type ),
					h( 'td', {}, t.party === 'first' ? __( 'First', 'kjeks' ) : __( 'Third', 'kjeks' ) ),
					settings.isMultisite !== false && h( 'td', {}, h( SitesCell, { sites: t.sites || [], siteNames } ) ),
					h( 'td', {}, h( SelectControl, {
						value: t.category,
						options: categoryOptions,
						onChange: ( v ) => onChange( t.id, { category: v } ),
						__nextHasNoMarginBottom: true,
					} ) ),
					h( 'td', {}, h( CheckboxControl, {
						checked: !! t.reviewed,
						onChange: ( v ) => onChange( t.id, { reviewed: v } ),
						__nextHasNoMarginBottom: true,
					} ) ),
					h( 'td', {}, h( Button, {
						variant: 'link',
						isDestructive: true,
						onClick: () => onRemove( t.id ),
					}, __( 'Remove', 'kjeks' ) ) )
				)
			)
		)
	);
}

function SitesCell( { sites, siteNames } ) {
	if ( ! sites.length ) {
		return h( 'span', { title: __( 'Applies to all sites', 'kjeks' ) }, __( 'All', 'kjeks' ) );
	}
	const sorted = [ ...sites ].sort( ( a, b ) => Number( a ) - Number( b ) );
	return h( Dropdown, {
		className: 'kjeks-sites-cell',
		contentClassName: 'kjeks-sites-popover',
		popoverProps: { placement: 'bottom-start', focusOnMount: 'container' },
		renderToggle: ( { isOpen, onToggle } ) =>
			h(
				Button,
				{
					variant: 'link',
					onClick: onToggle,
					'aria-expanded': isOpen,
					'aria-label': sprintf(
						/* translators: %d: number of sites. */
						_n( 'Show %d site', 'Show %d sites', sites.length, 'kjeks' ),
						sites.length
					),
				},
				String( sites.length )
			),
		renderContent: () =>
			h(
				'ul',
				{ className: 'kjeks-sites-list' },
				sorted.map( ( id ) => h( 'li', { key: id }, siteNames[ id ] || '#' + id ) )
			),
	} );
}

function normalize( data ) {
	return {
		categories: data.categories || [],
		trackers: ( data.trackers || [] ).map( ( t ) => ( { ...t, sites: t.sites || [] } ) ),
		siteNames: data.siteNames || {},
		content: data.content || {},
		deleteOnUninstall: !! data.deleteOnUninstall,
		bannerDefaultVisible: data.bannerDefaultVisible !== false,
		privacyPageDeclaration: !! data.privacyPageDeclaration,
		scanKey: data.scanKey || '',
	};
}

document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'kjeks-network-root' );
	if ( root ) {
		render( h( App ), root );
	}
} );
