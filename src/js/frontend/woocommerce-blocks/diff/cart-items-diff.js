/**
 * Pure cart-items diff.
 *
 * Compares two normalized cart-item snapshots (keyed by cart item key)
 * and reports the net additions and removals between them. Having this
 * as a pure function keeps the emission logic deterministic and unit
 * testable without a live WooCommerce store.
 */

/**
 * Index normalized cart items by their cart item key.
 *
 * @param {Array<{key: string, quantity: number, item: Object}>} items Cart items.
 * @return {Object<string, {key: string, quantity: number, item: Object}>} Items keyed by cart item key.
 */
const indexByKey = ( items ) => {
	const byKey = {};
	for ( const entry of items ) {
		if ( entry && entry.key !== undefined && entry.key !== null ) {
			byKey[ entry.key ] = entry;
		}
	}
	return byKey;
};

/**
 * Diff two cart snapshots.
 *
 * @param {Array} prevItems Previous normalized cart items.
 * @param {Array} nextItems Next normalized cart items.
 * @return {{added: Array<{item: Object, quantity: number}>, removed: Array<{item: Object, quantity: number}>}} Net additions and removals.
 */
export const diffCartItems = ( prevItems = [], nextItems = [] ) => {
	const prevByKey = indexByKey( prevItems );
	const nextByKey = indexByKey( nextItems );

	const added = [];
	const removed = [];

	// Additions and quantity changes for keys present in the next snapshot.
	for ( const key of Object.keys( nextByKey ) ) {
		const next = nextByKey[ key ];
		const prevQuantity = prevByKey[ key ] ? prevByKey[ key ].quantity : 0;
		const delta = next.quantity - prevQuantity;

		if ( delta > 0 ) {
			added.push( {
				item: { ...next.item, quantity: delta },
				quantity: delta,
			} );
		} else if ( delta < 0 ) {
			removed.push( {
				item: { ...next.item, quantity: -delta },
				quantity: -delta,
			} );
		}
	}

	// Full removals for keys that disappeared from the next snapshot.
	for ( const key of Object.keys( prevByKey ) ) {
		if ( ! nextByKey[ key ] ) {
			const prev = prevByKey[ key ];
			removed.push( {
				item: { ...prev.item, quantity: prev.quantity },
				quantity: prev.quantity,
			} );
		}
	}

	return { added, removed };
};
