/**
 * Kjeks network admin — aggregated cookie review.
 *
 * One row per cookie across the whole network, with search, status filtering,
 * and bulk review so a network admin classifies each cookie once.
 */
import './admin.css';
import { render, useState, useEffect, useMemo, createElement as h } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { applyFilters } from '@wordpress/hooks';
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
	TabPanel,
	Tooltip,
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

	const optionalCategories = useMemo(
		() => ( config ? config.categories.filter( ( c ) => ! c.required ) : [] ),
		[ config ]
	);
	const categoryOptions = optionalCategories.map( ( c ) => ( { label: c.label, value: c.slug } ) );

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

	return h(
		'div',
		{ className: 'kjeks-network' },
		h( 'h1', {}, __( 'Cookie Consent — network review', 'kjeks' ) ),
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

		renderTabs( h( 'div', { className: 'kjeks-network__cookies' },
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
			),

			h(
				PanelBody,
				{ title: __( 'Banner defaults', 'kjeks' ), initialOpen: false },
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
			),

			h(
				PanelBody,
				{ title: __( 'Advanced', 'kjeks' ), initialOpen: false },
				h( ToggleControl, {
					label: __( 'Delete all Kjeks data on uninstall', 'kjeks' ),
					checked: !! config.deleteOnUninstall,
					onChange: ( v ) => setConfig( { ...config, deleteOnUninstall: v } ),
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

		h(
			Button,
			{ variant: 'primary', isBusy: saving, disabled: saving, onClick: save },
			__( 'Save changes', 'kjeks' )
		)
		) )
	);
}

/**
 * Wraps the review body in a tabbed layout when add-ons register extra tabs
 * via the `kjeks.networkAdminTabs` filter. With no add-on, renders as before.
 *
 * Each extra tab is `{ name, title, render: () => ReactNode }`.
 */
function renderTabs( cookiesBody ) {
	const extraTabs = applyFilters( 'kjeks.networkAdminTabs', [] );
	if ( ! extraTabs.length ) {
		return cookiesBody;
	}
	const tabs = [
		{ name: 'cookies', title: __( 'Cookies', 'kjeks' ) },
		...extraTabs.map( ( t ) => ( { name: t.name, title: t.title } ) ),
	];
	return h(
		TabPanel,
		{ className: 'kjeks-network__tabs', tabs },
		( tab ) =>
			tab.name === 'cookies'
				? cookiesBody
				: ( ( extraTabs.find( ( t ) => t.name === tab.name ) || {} ).render || ( () => null ) )()
	);
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
				h( 'th', {}, __( 'Sites', 'kjeks' ) ),
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
					h( 'td', {}, h( SitesCell, { sites: t.sites || [], siteNames } ) ),
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
	const names = sites.map( ( id ) => siteNames[ id ] || '#' + id ).join( ', ' );
	return h( Tooltip, { text: names }, h( 'span', {}, String( sites.length ) ) );
}

function normalize( data ) {
	return {
		categories: data.categories || [],
		trackers: ( data.trackers || [] ).map( ( t ) => ( { ...t, sites: t.sites || [] } ) ),
		siteNames: data.siteNames || {},
		content: data.content || {},
		deleteOnUninstall: !! data.deleteOnUninstall,
		bannerDefaultVisible: data.bannerDefaultVisible !== false,
	};
}

document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'kjeks-network-root' );
	if ( root ) {
		render( h( App ), root );
	}
} );
