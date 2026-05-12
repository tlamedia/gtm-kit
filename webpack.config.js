/**
 * Webpack configuration override for the gtm-kit plugin.
 *
 * Extends @wordpress/scripts' default config to disable output-path
 * cleaning. The `assets/admin/` directory is shared between this
 * plugin's introductions bundle and the React settings bundle that
 * gtm-kit-settings writes here, so cleaning either side would wipe the
 * other.
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	output: {
		...defaultConfig.output,
		clean: false,
	},
};
