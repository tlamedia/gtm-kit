import { safeHref } from '../safeUrl';

describe( 'safeHref', () => {
	it( 'allows absolute http and https URLs', () => {
		expect( safeHref( 'https://gtmkit.com/pricing/' ) ).toBe(
			'https://gtmkit.com/pricing/'
		);
		expect( safeHref( 'http://example.com/' ) ).toBe(
			'http://example.com/'
		);
	} );

	it( 'allows mailto links', () => {
		expect( safeHref( 'mailto:support@gtmkit.com' ) ).toBe(
			'mailto:support@gtmkit.com'
		);
	} );

	it( 'allows relative and same-origin paths', () => {
		expect( safeHref( '/wp-admin/admin.php?page=gtmkit_general' ) ).toBe(
			'/wp-admin/admin.php?page=gtmkit_general'
		);
		expect( safeHref( 'admin.php?page=gtmkit_general#/upgrades' ) ).toBe(
			'admin.php?page=gtmkit_general#/upgrades'
		);
	} );

	it( 'rejects javascript: URLs', () => {
		expect( safeHref( 'javascript:alert(1)' ) ).toBe( '' );
		// eslint-disable-next-line no-script-url
		expect( safeHref( ' JaVaScRiPt:alert(1)' ) ).toBe( '' );
	} );

	it( 'rejects data: and other non-allowlisted protocols', () => {
		expect( safeHref( 'data:text/html,<script>alert(1)</script>' ) ).toBe(
			''
		);
		expect( safeHref( 'vbscript:msgbox(1)' ) ).toBe( '' );
		expect( safeHref( 'file:///etc/passwd' ) ).toBe( '' );
	} );

	it( 'rejects non-string and empty values', () => {
		expect( safeHref( undefined ) ).toBe( '' );
		expect( safeHref( null ) ).toBe( '' );
		expect( safeHref( 42 ) ).toBe( '' );
		expect( safeHref( '' ) ).toBe( '' );
		expect( safeHref( '   ' ) ).toBe( '' );
	} );
} );
