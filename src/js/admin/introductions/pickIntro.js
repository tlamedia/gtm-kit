/**
 * Pick the highest-priority introduction from a localised list. Lower
 * priority numbers win; ties keep input order. Returns null when the
 * input is empty or invalid.
 *
 * @param {Array<{ id: string, priority: number }>|undefined} intros
 * @return {{ id: string, priority: number }|null}
 */
export function pickHighestPriority( intros ) {
	if ( ! Array.isArray( intros ) || intros.length === 0 ) {
		return null;
	}

	let best = null;
	for ( const intro of intros ) {
		if ( ! intro || typeof intro.id !== 'string' ) {
			continue;
		}
		if ( best === null || intro.priority < best.priority ) {
			best = intro;
		}
	}
	return best;
}
