// @vitest-environment node
/**
 * Tests for the introductions component registry.
 *
 * Verifies registration, resolution, and the fallback behaviour for
 * unknown ids. The fallback component is verified by reference (we do
 * not render it here; the modal smoke-test in manual QA covers
 * rendering).
 *
 * Targets:
 *  - src/js/admin/introductions/componentRegistry.js
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
	_resetRegistryForTests,
	registerComponent,
	resolveComponent,
} from '../../../src/js/admin/introductions/componentRegistry.js';
import UntitledIntroFallback from '../../../src/js/admin/introductions/components/UntitledIntroFallback.jsx';

describe( 'componentRegistry', () => {
	beforeEach( () => {
		_resetRegistryForTests();
	} );

	it( 'returns the fallback for unknown ids', () => {
		expect( resolveComponent( 'never-registered' ) ).toBe( UntitledIntroFallback );
	} );

	it( 'returns the registered component for a known id', () => {
		const Marker = () => null;
		registerComponent( 'welcome-3.0', Marker );

		expect( resolveComponent( 'welcome-3.0' ) ).toBe( Marker );
	} );

	it( 'silently ignores invalid registrations', () => {
		registerComponent( '', () => null );
		registerComponent( 'no-component', null );
		registerComponent( 42, () => null );

		expect( resolveComponent( '' ) ).toBe( UntitledIntroFallback );
		expect( resolveComponent( 'no-component' ) ).toBe( UntitledIntroFallback );
	} );
} );
