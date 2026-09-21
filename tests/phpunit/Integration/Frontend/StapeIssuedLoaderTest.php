<?php
/**
 * Integration tests for the container loader emitted beside a Stape-issued loader.
 *
 * The legacy fixtures were rendered by the loader code as it stood before the
 * issued loader existed. Every configuration that does not use an issued
 * loader must still produce them byte for byte.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Frontend\Frontend::get_gtm_script()}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Frontend;

use TLA_Media\GTM_Kit\Common\StapeLoader;
use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * Issued loader output, and the legacy output it must leave untouched.
 */
final class StapeIssuedLoaderTest extends WP_UnitTestCase {

	/**
	 * The query value Stape issued for the default data layer.
	 *
	 * @var string
	 */
	private const VALUE = 'GB1WNz41RDwmTC00Xj9eTgdEWV5bXg0GTB4fHQERHUYSFgY%3D';

	/**
	 * Every setting the loader code reads, at its empty value.
	 *
	 * @var array<string, mixed>
	 */
	private const BLANK = [
		'datalayer_name'            => '',
		'gtm_auth'                  => '',
		'gtm_preview'               => '',
		'sgtm_domain'               => '',
		'sgtm_container_identifier' => '',
		'sgtm_cookie_keeper'        => false,
		'sgtm_stape_issued_loader'  => false,
		'google_tag_gateway'        => false,
	];

	/**
	 * Start every test with no stored loader.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		delete_option( StapeLoader::OPTION );
		$this->configure( [] );
	}

	/**
	 * Apply settings over the blank configuration.
	 *
	 * @param array<string, mixed> $general The general settings to apply.
	 *
	 * @return void
	 */
	private function configure( array $general ): void {
		$options = OptionsFactory::get_instance();

		foreach ( self::BLANK as $key => $value ) {
			$options->set_option( 'general', $key, $general[ $key ] ?? $value );
		}
	}

	/**
	 * Store an issued loader for the current settings.
	 *
	 * @param string $path The loader file name.
	 *
	 * @return void
	 */
	private function store( string $path ): void {
		( new StapeLoader( OptionsFactory::get_instance() ) )->store(
			[
				'path'  => $path,
				'param' => '3bsw',
				'value' => self::VALUE,
			],
			StapeLoader::SOURCE_API,
			'eu'
		);
	}

	/**
	 * Render the container loader.
	 *
	 * @return string
	 */
	private function render(): string {
		$frontend = new Frontend( OptionsFactory::get_instance() );

		ob_start();
		$frontend->get_gtm_script( 'GTM-TW5FD4G7' );

		return (string) ob_get_clean();
	}

	/**
	 * Read a legacy fixture.
	 *
	 * @param string $name The fixture name.
	 *
	 * @return string
	 */
	private function legacy( string $name ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local test fixture from disk.
		return (string) file_get_contents( __DIR__ . '/fixtures/legacy-loader/' . $name . '.js' );
	}

	/**
	 * The configurations the legacy fixtures were rendered with.
	 *
	 * @return array<string, array{0: string, 1: array<string, mixed>}>
	 */
	public function data_legacy_configurations(): array {
		$sgtm = [
			'sgtm_domain'               => 'collect.example.com',
			'sgtm_container_identifier' => '38i0hixjpkyq',
		];
		$env  = [
			'datalayer_name' => 'gtmkitLayer',
			'gtm_auth'       => 'abc123',
			'gtm_preview'    => 'env-5',
		];

		return [
			'standard'                           => [ 'standard', [] ],
			'standard, data layer, environment'  => [ 'standard-custom-datalayer-environment', $env ],
			'server-side with st'                => [ 'sgtm-st', $sgtm ],
			'Cookie Keeper, 12 characters'       => [ 'cookie-keeper-12', $sgtm + [ 'sgtm_cookie_keeper' => true ] ],
			'Cookie Keeper, 8 characters, extra' => [
				'cookie-keeper-8-custom-datalayer-environment',
				[
					'sgtm_container_identifier' => 'hixjpkyq',
					'sgtm_cookie_keeper'        => true,
				] + $sgtm + $env,
			],
		];
	}

	/**
	 * With the issued loader switched off, the output is what it always was.
	 *
	 * @dataProvider data_legacy_configurations
	 *
	 * @param string               $fixture The legacy fixture.
	 * @param array<string, mixed> $general The settings it was rendered with.
	 *
	 * @return void
	 */
	public function test_switched_off_output_is_byte_identical( string $fixture, array $general ): void {
		$this->configure( $general );

		$this->assertSame( $this->legacy( $fixture ), $this->render() );
	}

	/**
	 * Switched on with nothing stored yet, the legacy loader stays in place.
	 *
	 * @return void
	 */
	public function test_switched_on_without_a_stored_loader_emits_legacy(): void {
		$this->configure(
			[
				'sgtm_domain'               => 'collect.example.com',
				'sgtm_container_identifier' => '38i0hixjpkyq',
				'sgtm_cookie_keeper'        => true,
				'sgtm_stape_issued_loader'  => true,
			]
		);

		$this->assertSame( $this->legacy( 'cookie-keeper-12' ), $this->render() );
	}

	/**
	 * A stored loader issued for other settings is not used.
	 *
	 * @return void
	 */
	public function test_a_loader_for_other_settings_emits_legacy(): void {
		$general = [
			'sgtm_domain'               => 'collect.example.com',
			'sgtm_container_identifier' => '38i0hixjpkyq',
			'sgtm_stape_issued_loader'  => true,
		];
		$this->configure( $general );
		$this->store( '38i0hixjpkyq' );

		$this->configure( [ 'sgtm_cookie_keeper' => true ] + $general );

		$this->assertSame( $this->legacy( 'cookie-keeper-12' ), $this->render() );
	}

	/**
	 * A stored loader that fails its patterns is never printed.
	 *
	 * @return void
	 */
	public function test_a_tampered_stored_loader_emits_legacy(): void {
		$general = [
			'sgtm_domain'               => 'collect.example.com',
			'sgtm_container_identifier' => '38i0hixjpkyq',
			'sgtm_stape_issued_loader'  => true,
		];
		$this->configure( $general );
		$this->store( '38i0hixjpkyq' );

		$stored          = get_option( StapeLoader::OPTION );
		$stored['value'] = "abc'</script><script>alert(1)</script>";
		update_option( StapeLoader::OPTION, $stored );

		$this->assertSame( $this->legacy( 'sgtm-st' ), $this->render() );
	}

	/**
	 * Without Cookie Keeper, the issued address replaces `st=` and the container ID.
	 *
	 * @return void
	 */
	public function test_issued_loader_without_cookie_keeper(): void {
		$this->configure(
			[
				'sgtm_domain'               => 'collect.example.com',
				'sgtm_container_identifier' => '38i0hixjpkyq',
				'sgtm_stape_issued_loader'  => true,
				'gtm_auth'                  => 'abc123',
				'gtm_preview'               => 'env-5',
			]
		);
		$this->store( '38i0hixjpkyq' );

		$output = $this->render();

		$this->assertStringContainsString( "'https://collect.example.com/38i0hixjpkyq.js?'+i+'&gtm_auth=abc123&gtm_preview=env-5&gtm_cookies_win=x';", $output );
		$this->assertStringContainsString( "})(window,document,'script','dataLayer','3bsw=" . self::VALUE . "');", $output );
		$this->assertStringNotContainsString( 'st=', $output );
		$this->assertStringNotContainsString( 'TW5FD4G7', $output );
		$this->assertStringNotContainsString( '&l=', $output );
	}

	/**
	 * Identifiers either side of the eight-character boundary.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function data_cookie_keeper_paths(): array {
		return [
			'12 characters' => [ '38i0hixjpkyq', '38i0kphixjpkyq' ],
			'8 characters'  => [ 'hixjpkyq', 'kphixjpkyq' ],
		];
	}

	/**
	 * With Cookie Keeper, Safari asks for the `kp` file with the issued query.
	 *
	 * @dataProvider data_cookie_keeper_paths
	 *
	 * @param string $path   The issued loader file name.
	 * @param string $safari The Safari loader file name.
	 *
	 * @return void
	 */
	public function test_issued_loader_with_cookie_keeper( string $path, string $safari ): void {
		$this->configure(
			[
				'sgtm_domain'               => 'collect.example.com',
				'sgtm_container_identifier' => $path,
				'sgtm_cookie_keeper'        => true,
				'sgtm_stape_issued_loader'  => true,
			]
		);
		$this->store( $path );

		$output = $this->render();

		$this->assertStringContainsString( 'o="3bsw=' . self::VALUE . '"', $output );
		$this->assertStringContainsString( 'c="' . $path . '"', $output );
		$this->assertStringContainsString( 'e=g?"' . $safari . '":c', $output );
		$this->assertStringContainsString( 'A.src=n+"/"+e+".js?"+o+v,null!=', $output );
		$this->assertStringContainsString( 'v=f?"&bi="+encodeURIComponent(f):""', $output );
		$this->assertStringNotContainsString( 'st=', $output );
		$this->assertStringNotContainsString( 'TW5FD4G7', $output );
	}
}
