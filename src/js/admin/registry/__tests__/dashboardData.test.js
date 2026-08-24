/**
 * Dashboard data helpers: metrics and derived health checks.
 */
import {
	getDashboardMetrics,
	getDashboardNotifications,
} from '../dashboardData';
import SettingsService from '../../services/SettingsService';

afterEach( () => {
	SettingsService.data = {};
} );

describe( 'getDashboardMetrics', () => {
	it( 'summarises container, consent and server-side', () => {
		const [ container, consent, sgtm ] = getDashboardMetrics( {
			general: {
				gtm_id: 'GTM-XYZ',
				container_active: true,
				gcm_default_settings: false,
				gcm_region: [],
				sgtm_domain: 'sgtm.example.com',
			},
		} );

		expect( getDashboardMetrics( { general: {} } ) ).toHaveLength( 3 );
		expect( container.value ).toBe( 'GTM-XYZ' );
		expect( container.badge ).toBe( 'active' );
		expect( consent.value ).toBe( 'Off' );
		expect( consent.badge ).toBe( 'off' );
		expect( consent.subtitle ).toBe( 'All regions' );
		expect( sgtm.value ).toBe( 'On' );
		expect( sgtm.badge ).toBe( 'premium' );
	} );

	it( 'marks an unset container Off and shows the consent region', () => {
		const [ container, consent, sgtm ] = getDashboardMetrics( {
			general: {
				gtm_id: '',
				container_active: false,
				gcm_default_settings: true,
				gcm_region: [ 'DK', 'DE' ],
			},
		} );

		expect( container.value ).toBe( 'Not set' );
		expect( container.badge ).toBe( 'off' );
		expect( consent.value ).toBe( 'On' );
		expect( consent.subtitle ).toBe( 'Region: DK, DE' );
		expect( sgtm.value ).toBe( 'Off' );
	} );
} );

describe( 'getDashboardNotifications', () => {
	const notice = {
		id: 'gtmkit-auto-update',
		header: 'Automatic Updates:',
		message:
			'We recommend enabling automatic updates. <a href="https://example.test/wp-admin/admin.php?page=gtmkit_general#/misc">Go to settings</a>',
	};
	const problem = {
		id: 'gtmkit-container-injection',
		header: 'Container injection',
		message: 'Your container is not being injected.',
	};

	it( 'returns an empty list when there are no active notifications', () => {
		expect( getDashboardNotifications( undefined ) ).toEqual( [] );
		expect(
			getDashboardNotifications( {
				problem: { active: [] },
				notice: { active: [] },
			} )
		).toEqual( [] );
	} );

	it( 'lists problems first, then notices, with the right severity', () => {
		const rows = getDashboardNotifications( {
			problem: { active: [ problem ] },
			notice: { active: [ notice ] },
		} );
		expect( rows.map( ( r ) => r.severity ) ).toEqual( [
			'error',
			'warning',
		] );
		expect( rows[ 0 ].id ).toBe( 'gtmkit-container-injection' );
	} );

	it( 'strips the trailing colon from the header for the title', () => {
		const [ row ] = getDashboardNotifications( {
			notice: { active: [ notice ] },
		} );
		expect( row.title ).toBe( 'Automatic Updates' );
	} );

	it( 'extracts the trailing link as an action and removes it from the description', () => {
		const [ row ] = getDashboardNotifications( {
			notice: { active: [ notice ] },
		} );
		expect( row.actions ).toEqual( [
			{
				label: 'Go to settings',
				href: 'https://example.test/wp-admin/admin.php?page=gtmkit_general#/misc',
			},
		] );
		expect( row.description ).toBe(
			'We recommend enabling automatic updates.'
		);
		expect( row.description ).not.toContain( 'Go to settings' );
	} );

	it( 'keeps every link clickable when a message offers more than one', () => {
		const [ row ] = getDashboardNotifications( {
			notice: {
				active: [
					{
						id: 'gtmkit-two-links',
						header: 'Breaking change:',
						message:
							'You need either add-on. <a href="https://example.test/wp-admin/admin.php?page=gtmkit_upgrades">Get the add-on</a> <a href="https://example.test/free">Get the free plugin</a>',
					},
				],
			},
		} );

		expect( row.actions ).toHaveLength( 2 );
		expect( row.actions[ 0 ].label ).toBe( 'Get the add-on' );
		expect( row.actions[ 1 ].label ).toBe( 'Get the free plugin' );
		// Neither link may survive as unclickable words in the description.
		expect( row.description ).toBe( 'You need either add-on.' );
	} );

	it( 'leaves the actions empty when the message has no link', () => {
		const [ row ] = getDashboardNotifications( {
			problem: { active: [ problem ] },
		} );
		expect( row.actions ).toEqual( [] );
		expect( row.description ).toBe(
			'Your container is not being injected.'
		);
	} );

	it( 'drops a link whose target is rejected as unsafe', () => {
		const [ row ] = getDashboardNotifications( {
			notice: {
				active: [
					{
						id: 'gtmkit-unsafe-link',
						header: 'Heads up:',
						message:
							'Something happened. <a href="javascript:alert(1)">Do the thing</a>',
					},
				],
			},
		} );

		expect( row.actions ).toEqual( [] );
		expect( row.description ).toBe( 'Something happened.' );
	} );
} );
