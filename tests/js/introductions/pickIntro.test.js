// @vitest-environment node
/**
 * Tests for the introduction-priority picker.
 *
 * Pattern: pure-function unit tests, no JSDOM needed. The picker is the
 * extracted decision the modal uses to choose which intro to render
 * from a localised list.
 *
 * Target: src/js/admin/introductions/pickIntro.js — `pickHighestPriority`.
 */

import { describe, expect, it } from 'vitest';
import { pickHighestPriority } from '../../../src/js/admin/introductions/pickIntro.js';

describe( 'pickHighestPriority', () => {
	it( 'returns null for an empty or missing list', () => {
		expect( pickHighestPriority( [] ) ).toBeNull();
		expect( pickHighestPriority( undefined ) ).toBeNull();
		expect( pickHighestPriority( null ) ).toBeNull();
	} );

	it( 'returns the lowest priority value (highest-priority intro)', () => {
		const intros = [
			{ id: 'mid', priority: 200 },
			{ id: 'high', priority: 100 },
			{ id: 'low', priority: 500 },
		];
		expect( pickHighestPriority( intros ).id ).toBe( 'high' );
	} );

	it( 'keeps insertion order for ties', () => {
		const intros = [
			{ id: 'first', priority: 100 },
			{ id: 'second', priority: 100 },
		];
		expect( pickHighestPriority( intros ).id ).toBe( 'first' );
	} );

	it( 'ignores entries that are not shaped like intros', () => {
		const intros = [
			null,
			{ id: 42 },
			{ priority: 50 },
			{ id: 'valid', priority: 300 },
		];
		expect( pickHighestPriority( intros ).id ).toBe( 'valid' );
	} );
} );
