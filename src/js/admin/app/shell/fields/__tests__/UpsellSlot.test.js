/**
 * The upgrade link beneath a tier-locked field.
 *
 * The link is marketing, so it belongs on free-tier installs only. The locked
 * row itself describes what the product offers and stays for everyone, so
 * these cover both halves: the link disappears for a paying customer while the
 * row it sits under does not.
 */

/*
 * `react` is a real dependency; `import/no-extraneous-dependencies` misfires on
 * the JSX below, flagging a phantom `@types/react`.
 */
/* eslint-disable import/no-extraneous-dependencies */

import { render, screen } from '@testing-library/react';

import UpsellSlot from '../UpsellSlot';
import FieldRow from '../../FieldRow';
import { SettingsDataContext } from '../../../../context/SettingsDataContext';
import { LicenseContext } from '../../../../context/LicenseContext';
import { NotificationContext } from '../../../../context/NotificationContext';
import { TIERS } from '../../../../constants/tiers';
import SettingsService from '../../../../services/SettingsService';

const UPGRADE_TEXT = /Upgrade to GTM Kit Premium/;

/**
 * Put the settings payload into the tier under test. The license context reads
 * the same source, so both the lock decision and the link gate stay consistent.
 *
 * @param {string} tier One of TIERS.
 */
const setTier = ( tier ) => {
	SettingsService.data = {
		isPremium: tier !== TIERS.FREE,
		isPremiumPlugin: tier === TIERS.PREMIUM,
	};
};

afterEach( () => {
	SettingsService.data = {};
} );

const PREMIUM_FIELD = {
	key: 'premium.example_setting',
	capability: 'setup',
	section: 'environment',
	control: 'toggle',
	label: 'Example premium setting',
	tier: TIERS.PREMIUM,
};

/**
 * Render a field row at a given tier with the contexts it consumes.
 *
 * @param {string} tier One of TIERS.
 */
const renderRow = ( tier ) => {
	setTier( tier );
	return render(
		<SettingsDataContext.Provider
			value={ { settings: {}, updateStateSettings: () => {} } }
		>
			<NotificationContext.Provider
				value={ {
					notifications: [],
					setNotificationStatus: () => {},
					isUpdatingNotifications: false,
				} }
			>
				<LicenseContext.Provider
					value={ {
						isPremium: tier !== TIERS.FREE,
						hasValidLicense: tier !== TIERS.FREE,
						activeTier: tier,
					} }
				>
					<FieldRow field={ PREMIUM_FIELD } />
				</LicenseContext.Provider>
			</NotificationContext.Provider>
		</SettingsDataContext.Provider>
	);
};

describe( 'UpsellSlot', () => {
	it( 'renders the upgrade link on a free-tier install', () => {
		setTier( TIERS.FREE );
		render( <UpsellSlot /> );
		expect( screen.getByText( UPGRADE_TEXT ) ).toBeInTheDocument();
	} );

	it( 'renders nothing for a Woo Add-On customer', () => {
		setTier( TIERS.WOO );
		const { container } = render( <UpsellSlot /> );
		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'renders nothing for a Premium customer', () => {
		setTier( TIERS.PREMIUM );
		const { container } = render( <UpsellSlot /> );
		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'points the link at the upgrade page in a new tab', () => {
		setTier( TIERS.FREE );
		render( <UpsellSlot /> );
		const link = screen.getByText( UPGRADE_TEXT ).closest( 'a' );
		expect( link ).toHaveAttribute( 'target', '_blank' );
		expect( link ).toHaveAttribute( 'rel', 'noreferrer' );
		expect( link.getAttribute( 'href' ) ).toMatch( /^https:\/\// );
	} );
} );

describe( 'a tier-locked field row', () => {
	it( 'shows the row and the upgrade link on a free-tier install', () => {
		renderRow( TIERS.FREE );
		expect(
			screen.getByText( 'Example premium setting' )
		).toBeInTheDocument();
		expect( screen.getByText( UPGRADE_TEXT ) ).toBeInTheDocument();
	} );

	it( 'keeps the locked row but drops the link for a Woo Add-On customer', () => {
		renderRow( TIERS.WOO );
		expect(
			screen.getByText( 'Example premium setting' )
		).toBeInTheDocument();
		expect( screen.queryByText( UPGRADE_TEXT ) ).not.toBeInTheDocument();
	} );

	it( 'keeps the row and drops the link for a Premium customer', () => {
		renderRow( TIERS.PREMIUM );
		expect(
			screen.getByText( 'Example premium setting' )
		).toBeInTheDocument();
		expect( screen.queryByText( UPGRADE_TEXT ) ).not.toBeInTheDocument();
	} );

	// The badge is an upsell pill ("Premium") while the field is locked and a
	// quieter "(Premium)" once the license unlocks it, so match either wording.
	it( 'keeps the tier badge for every tier', () => {
		[ TIERS.FREE, TIERS.WOO, TIERS.PREMIUM ].forEach( ( tier ) => {
			const { unmount } = renderRow( tier );
			expect( screen.getByText( /^\(?Premium\)?$/ ) ).toBeInTheDocument();
			unmount();
		} );
	} );
} );
