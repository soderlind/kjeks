/**
 * Editor registration for the Kjeks dynamic blocks.
 *
 * Both blocks render server-side (PHP `render_callback`); here we only provide
 * the editor `edit` view via ServerSideRender so they appear in the inserter.
 */
import { registerBlockType, getBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

import preferencesMeta from '../../../blocks/preferences/block.json';
import declarationMeta from '../../../blocks/cookie-declaration/block.json';

const makeEdit = ( name, placeholder ) =>
	function Edit() {
		return createElement(
			'div',
			useBlockProps(),
			createElement( ServerSideRender, {
				block: name,
				EmptyResponsePlaceholder: () =>
					createElement( 'p', null, placeholder ),
			} )
		);
	};

const register = ( metadata, placeholder ) => {
	if ( getBlockType( metadata.name ) ) {
		return;
	}
	registerBlockType( metadata.name, {
		...metadata,
		edit: makeEdit( metadata.name, placeholder ),
		save: () => null,
	} );
};

register( preferencesMeta, __( 'Cookie settings link', 'kjeks' ) );
register( declarationMeta, __( 'Cookie declaration table', 'kjeks' ) );
