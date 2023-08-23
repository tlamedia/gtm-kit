/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

module.exports = {
	plugins: [
		require( 'autoprefixer' ),
		require( 'cssnano' ) // This will minify the result CSS.
	]
}
