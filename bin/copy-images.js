/**
 * GTM Kit - Copy images
 */

const ncp = require( 'ncp' ).ncp;

ncp.limit = 16;

ncp( 'src/images', 'assets/images', function ( err ) {
	if ( err ) {
		// eslint-disable-next-line no-console
		return console.error( err );
	}
	// eslint-disable-next-line no-console
	console.log( 'Images copied!' );
} );
