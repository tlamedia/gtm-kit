/**
 * Webpack configuration override for the gtm-kit plugin.
 *
 * Extends @wordpress/scripts' default config to serve three builds from one
 * config: the WooCommerce blocks frontend bundle, the admin introductions
 * bundle, and the React settings app.
 *
 * `output.clean` is disabled because `assets/admin/` is shared between the
 * introductions bundle and the settings app, so cleaning either side would
 * wipe the other. (A full `build:assets` starts from a clean `assets/`, so
 * stale settings route-chunks do not accumulate there.)
 *
 * The remaining overrides are what the settings app needs and are inert for
 * the other two entries: `publicPath: 'auto'` so the React.lazy route chunks
 * resolve their URL from the script tag at runtime, async-only `splitChunks`
 * with vendor splitting disabled so vendors stay bundled with each entry for
 * WordPress script-handle compatibility, and a `lodash -> lodash-es` alias for
 * ESM tree-shaking.
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	output: {
		...defaultConfig.output,
		clean: false,
		publicPath: 'auto',
	},
	optimization: {
		...defaultConfig.optimization,
		splitChunks: {
			chunks: 'async',
			cacheGroups: {
				defaultVendors: false,
				default: false,
			},
		},
	},
	resolve: {
		...defaultConfig.resolve,
		alias: {
			...defaultConfig.resolve.alias,
			lodash: path.resolve(
				__dirname,
				'node_modules/lodash-es/lodash.js'
			),
		},
	},
};
