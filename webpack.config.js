const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		banner: path.resolve( __dirname, 'assets/src/banner.js' ),
		network: path.resolve( __dirname, 'assets/src/admin/network.js' ),
		blocks: path.resolve( __dirname, 'assets/src/blocks/index.js' ),
	},
};
