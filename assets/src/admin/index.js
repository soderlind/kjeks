/**
 * Kjeks per-site admin app.
 */
import './admin.css';
import { render, useState, useEffect, createElement as h } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import {
	Panel,
	PanelBody,
	PanelRow,
	ToggleControl,
	SelectControl,
	TextControl,
	TextareaControl,
	Button,
	Notice,
	Spinner,
} from '@wordpress/components';

const settings = window.kjeksAdmin || {};

apiFetch.use( apiFetch.createNonceMiddleware( settings.nonce ) );

function App() {
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ config, setConfig ] = useState( null );

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

	if ( loading ) {
		return h( Spinner );
	}
	if ( ! config ) {
		return notice ? h( Notice, { status: notice.type, isDismissible: false }, notice.text ) : null;
	}

	const categoryOptions = config.categories
		.filter( ( c ) => ! c.required )
		.map( ( c ) => ( { label: c.label, value: c.slug } ) );

	const updateTracker = ( index, changes ) => {
		const trackers = config.trackers.slice();
		trackers[ index ] = { ...trackers[ index ], ...changes };
		setConfig( { ...config, trackers } );
	};

	const updateContent = ( field, value ) => {
		setConfig( { ...config, content: { ...config.content, [ field ]: value } } );
	};

	const save = () => {
		setSaving( true );
		// Only site-local trackers are editable here; network cookies are read-only.
		const localTrackers = config.trackers.filter( ( t ) => t.source === 'local' );

		apiFetch( {
			url: settings.restUrl,
			method: 'POST',
			data: {
				localTrackers,
				content: config.content,
				policyVersion: config.policyVersion,
			},
		} )
			.then( ( data ) => {
				setConfig( normalize( data ) );
				setNotice( { type: 'success', text: __( 'Settings saved.', 'kjeks' ) } );
				setSaving( false );
			} )
			.catch( () => {
				setNotice( { type: 'error', text: __( 'Save failed.', 'kjeks' ) } );
				setSaving( false );
			} );
	};

	return h(
		'div',
		{ className: 'kjeks-admin' },
		h( 'h1', {}, __( 'Cookie Consent', 'kjeks' ) ),
		notice &&
			h(
				Notice,
				{ status: notice.type, onRemove: () => setNotice( null ) },
				notice.text
			),
		h(
			Panel,
			{},
			h(
				PanelBody,
				{ title: __( 'Network cookies (read-only)', 'kjeks' ), initialOpen: true },
				h(
					PanelRow,
					{},
					h(
						'p',
						{ className: 'kjeks-admin__hint' },
						__( 'These cookies are reviewed by the network administrator and apply to this site. Reviewed cookies appear in your cookie declaration.', 'kjeks' )
					)
				),
				config.trackers.filter( ( t ) => t.source === 'network' ).length === 0 &&
					h( PanelRow, {}, __( 'No network cookies apply to this site yet.', 'kjeks' ) ),
				config.trackers
					.filter( ( t ) => t.source === 'network' )
					.map( ( tracker ) =>
						h(
							PanelRow,
							{ key: tracker.id },
							h(
								'div',
								{ className: 'kjeks-admin__tracker kjeks-admin__tracker--locked' },
								h( 'strong', {}, tracker.name || tracker.id ),
								h( 'span', { className: 'kjeks-admin__category' }, categoryLabel( config, tracker.category ) ),
								h(
									'span',
									{ className: tracker.reviewed ? 'kjeks-admin__badge is-reviewed' : 'kjeks-admin__badge is-pending' },
									tracker.reviewed ? __( 'Reviewed', 'kjeks' ) : __( 'Pending network review', 'kjeks' )
								),
								h( 'span', { className: 'kjeks-admin__lock', 'aria-hidden': 'true' }, '🔒' )
							)
						)
					)
			),
			h(
				PanelBody,
				{ title: __( 'Your site cookies', 'kjeks' ), initialOpen: true },
				config.trackers.filter( ( t ) => t.source === 'local' ).length === 0 &&
					h( PanelRow, {}, __( 'None. Site-specific cookies you add or import appear here for you to review.', 'kjeks' ) ),
				config.trackers.map( ( tracker, index ) =>
					tracker.source === 'local' &&
					h(
						PanelRow,
						{ key: tracker.id },
						h(
							'div',
							{ className: 'kjeks-admin__tracker' },
							h( 'strong', {}, tracker.name || tracker.id ),
							h( SelectControl, {
								label: __( 'Category', 'kjeks' ),
								value: tracker.category,
								options: categoryOptions,
								onChange: ( value ) => updateTracker( index, { category: value } ),
							} ),
							h( ToggleControl, {
								label: __( 'Reviewed', 'kjeks' ),
								checked: !! tracker.reviewed,
								onChange: ( value ) => updateTracker( index, { reviewed: value } ),
							} )
						)
					)
				)
			),
			h(
				PanelBody,
				{ title: __( 'Banner content (overrides network default)', 'kjeks' ), initialOpen: false },
				h( TextControl, {
					label: __( 'Heading', 'kjeks' ),
					value: config.content.heading || '',
					placeholder: config.networkContent.heading,
					onChange: ( value ) => updateContent( 'heading', value ),
				} ),
				h( TextareaControl, {
					label: __( 'Body', 'kjeks' ),
					value: config.content.body || '',
					placeholder: config.networkContent.body,
					onChange: ( value ) => updateContent( 'body', value ),
				} ),
				h( TextControl, {
					label: __( 'Privacy policy URL', 'kjeks' ),
					type: 'url',
					value: config.content.privacy_url || '',
					placeholder: config.networkContent.privacy_url,
					onChange: ( value ) => updateContent( 'privacy_url', value ),
				} )
			),
			h(
				PanelBody,
				{ title: __( 'Policy version', 'kjeks' ), initialOpen: false },
				h( 'p', {}, __( 'Bump the version to invalidate prior consent and re-prompt visitors.', 'kjeks' ) ),
				h( TextControl, {
					label: __( 'Version', 'kjeks' ),
					type: 'number',
					value: String( config.policyVersion ),
					onChange: ( value ) =>
						setConfig( { ...config, policyVersion: parseInt( value, 10 ) || 1 } ),
				} ),
				h(
					Button,
					{
						variant: 'secondary',
						onClick: () =>
							setConfig( { ...config, policyVersion: config.policyVersion + 1 } ),
					},
					__( 'Bump version', 'kjeks' )
				)
			)
		),
		h(
			Button,
			{ variant: 'primary', isBusy: saving, disabled: saving, onClick: save },
			__( 'Save changes', 'kjeks' )
		)
	);
}

function normalize( data ) {
	return {
		categories: data.categories || [],
		trackers: data.trackers || [],
		content: data.content || {},
		networkContent: data.networkContent || {},
		policyVersion: data.policyVersion || 1,
	};
}

function categoryLabel( config, slug ) {
	const match = ( config.categories || [] ).find( ( c ) => c.slug === slug );
	return match ? match.label : slug;
}

document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'kjeks-admin-root' );
	if ( root ) {
		render( h( App ), root );
	}
} );
