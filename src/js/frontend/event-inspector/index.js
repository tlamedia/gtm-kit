/**
 * SCSS
 */
import './styles.scss';

const theDataLayer = window[ window.gtmkit_settings.datalayer_name ];

theDataLayer.push = function () {
	Array.prototype.push.apply( this, arguments );
	const event = new CustomEvent( 'gtmkitDataLayerPush', {
		detail: arguments[ 0 ],
	} );
	window.dispatchEvent( event );
};

window.addEventListener( 'gtmkitDataLayerPush', function ( e ) {
	getDataLayer();
} );

let dataLayerLastIndex = 0;

function getDataLayer() {
	const currentDataLayerIndex = theDataLayer.length - 1;

	if ( currentDataLayerIndex > dataLayerLastIndex ) {
		const newDataLayer = theDataLayer.slice( dataLayerLastIndex + 1 );
		let storedEvents =
			JSON.parse( sessionStorage.getItem( 'gtmkit-event-inspector' ) ) ||
			[];

		newDataLayer.forEach( ( event ) => {
			if (
				( ! event.event && ! event.ecommerce ) ||
				( event.event && event.event.substring( 0, 4 ) === 'gtm.' )
			) {
				return;
			}
			storedEvents.push( event );
		} );

		if ( storedEvents.length > 5 ) {
			storedEvents = storedEvents.slice( -5 );
		}

		dataLayerLastIndex = currentDataLayerIndex;

		sessionStorage.setItem(
			'gtmkit-event-inspector',
			JSON.stringify( storedEvents )
		);
		renderList( storedEvents );
	}
}

function renderList( events ) {
	const listItem = '<li>{{event}}</li>';
	const reversedList = [ ...events ].reverse();
	const rendered = reversedList.map( ( item ) => {
		return listItem.replace( '{{event}}', item.event );
	} );

	document.getElementById( 'gtmkit-event-inspector-list' ).innerHTML =
		rendered.join( '' );
}
