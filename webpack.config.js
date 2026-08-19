const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		banner: path.resolve( __dirname, 'assets/src/banner.js' ),
		admin: path.resolve( __dirname, 'assets/src/admin/index.js' ),
		network: path.resolve( __dirname, 'assets/src/admin/network.js' ),
	},
};
