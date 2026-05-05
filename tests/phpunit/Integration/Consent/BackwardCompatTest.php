<?php
/**
 * Backward-compatibility integration tests for the Consent Mode v2 default
 * emission after the signal source registry was introduced.
 *
 * The signal source registry replaces the direct call to
 * `apply_filters('gtmkit_consent_default_state', ...)` in
 * {@see Frontend::enqueue_settings_and_data_script()}. The default
 * `gtmkit_default` source delegates to that filter, so for the
 * no-extra-sources case the rendered output must remain
 * **byte-identical** to the previous baseline.
 *
 * Approach: pin the rendered inline script to a snapshot for two
 * canonical configurations (master toggle off; master toggle on with all
 * categories denied), and pin the structural shape with literal substring
 * assertions. The snapshot is the strict regression net; the structural
 * checks make the failure mode actionable when the snapshot drifts.
 *
 * Snapshot files live in `tests/phpunit/Integration/Consent/fixtures/`.
 * To regenerate after an intentional change, delete the relevant fixture
 * and re-run the test — it will write the new fixture and pass.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Consent;

use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * Pins the legacy emission contract so the registry refactor cannot
 * silently shift any byte in the default rendered script.
 */
final class BackwardCompatTest extends WP_UnitTestCase {

	/**
	 * The seven Consent Mode v2 categories, in canonical order.
	 *
	 * @var array<int, string>
	 */
	private const CONSENT_OPTION_KEYS = [
		'gcm_ad_personalization',
		'gcm_ad_storage',
		'gcm_ad_user_data',
		'gcm_analytics_storage',
		'gcm_personalization_storage',
		'gcm_functionality_storage',
		'gcm_security_storage',
	];

	/**
	 * Reset state so every assertion observes a clean WP scripts registry
	 * and clean filter chains.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		wp_cache_delete( 'gtmkit_script_settings', 'gtmkit' );
		wp_scripts()->remove( 'gtmkit' );
		remove_all_filters( 'gtmkit_consent_signal_sources' );
		remove_all_filters( 'gtmkit_consent_default_state' );
		remove_all_filters( 'gtmkit_consent_default_settings_enabled' );
		remove_all_filters( 'gtmkit_consent_region' );
		remove_all_filters( 'gtmkit_header_script_settings' );
		remove_all_filters( 'gtmkit_header_script_data' );
	}

	/**
	 * With master toggle on and all seven categories denied, the rendered
	 * inline script preserves the canonical pre-registry byte sequence.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_settings_and_data_script
	 */
	public function test_default_emission_byte_identical_when_no_extras_registered(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 1 );
		foreach ( self::CONSENT_OPTION_KEYS as $key ) {
			$options->set_option( 'general', $key, 0 );
		}
		$options->set_option( 'general', 'gcm_region', [] );
		$options->set_option( 'general', 'gcm_wait_for_update', 0 );
		$options->set_option( 'general', 'gcm_ads_data_redaction', 0 );
		$options->set_option( 'general', 'gcm_url_passthrough', 0 );
		$options->set_option( 'general', 'console_log', '' );
		$options->set_option( 'general', 'datalayer_name', '' );

		( new Frontend( $options ) )->enqueue_settings_and_data_script();
		$inline = $this->extract_inline_script();

		$this->assertSnapshot( 'default-emission-all-denied.snapshot.js', $inline );

		// Structural asserts that make a hash mismatch readable.
		$this->assertStringContainsString( "gtag('consent', 'default'", $inline );
		$this->assertStringContainsString( "'ad_personalization': 'denied'", $inline );
		$this->assertStringContainsString( "'security_storage': 'denied'", $inline );
		$this->assertSame( 7, substr_count( $inline, "'denied'" ) );
		$this->assertSame( 0, substr_count( $inline, "'granted'" ) );

		// Negative regression: no leakage of registry/gate internals into the rendered output.
		$this->assertStringNotContainsString( 'signal_source', $inline );
		$this->assertStringNotContainsString( 'deferral_gate', $inline );
		$this->assertStringNotContainsString( 'gtmkit_consent_signal_sources', $inline );
	}

	/**
	 * With the master toggle off, the inline script emits no consent
	 * surface area — same as the pre-registry baseline.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_settings_and_data_script
	 */
	public function test_master_toggle_off_emits_no_consent_block(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 0 );
		$options->set_option( 'general', 'datalayer_name', '' );
		$options->set_option( 'general', 'console_log', '' );

		( new Frontend( $options ) )->enqueue_settings_and_data_script();
		$inline = $this->extract_inline_script();

		$this->assertSnapshot( 'master-toggle-off.snapshot.js', $inline );

		$this->assertStringNotContainsString( "gtag('consent', 'default'", $inline );
		$this->assertStringNotContainsString( 'window.gtmkit.consent', $inline );
		$this->assertStringNotContainsString( 'gtmkit:consent:updated', $inline );
	}

	/**
	 * The category emission order matches the canonical pre-registry order.
	 * Captured separately so an order regression is named in the failure.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_settings_and_data_script
	 */
	public function test_category_emission_order_is_canonical(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 1 );
		foreach ( self::CONSENT_OPTION_KEYS as $key ) {
			$options->set_option( 'general', $key, 0 );
		}

		( new Frontend( $options ) )->enqueue_settings_and_data_script();
		$inline = $this->extract_inline_script();

		$expected_order = [
			'ad_personalization',
			'ad_storage',
			'ad_user_data',
			'analytics_storage',
			'personalization_storage',
			'functionality_storage',
			'security_storage',
		];
		$last_position  = -1;
		foreach ( $expected_order as $category ) {
			$needle   = "'" . $category . "':";
			$position = strpos( $inline, $needle );
			$this->assertNotFalse( $position, "Category '{$category}' missing from emission." );
			$this->assertGreaterThan( $last_position, $position, "Category '{$category}' is out of canonical order." );
			$last_position = $position;
		}
	}

	/**
	 * Compare actual script output to a fixture file. If the fixture does
	 * not exist, write it and skip the test — so the next run pins it.
	 *
	 * @param string $fixture_filename Filename under fixtures/.
	 * @param string $actual           The rendered inline script.
	 *
	 * @return void
	 */
	private function assertSnapshot( string $fixture_filename, string $actual ): void {
		$fixture_dir = __DIR__ . '/fixtures';
		if ( ! is_dir( $fixture_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- WP_Filesystem is not initialized in the test bootstrap; tests run outside the request lifecycle that wires it up.
			mkdir( $fixture_dir, 0755, true );
		}
		$fixture_path = $fixture_dir . '/' . $fixture_filename;

		if ( ! file_exists( $fixture_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP_Filesystem is not initialized in the test bootstrap; tests run outside the request lifecycle that wires it up.
			file_put_contents( $fixture_path, $actual );
			$this->markTestSkipped(
				sprintf(
					'Snapshot fixture %s did not exist and was just written. Re-run to verify.',
					$fixture_filename
				)
			);
		}

		$this->assertStringEqualsFile(
			$fixture_path,
			$actual,
			sprintf(
				'Rendered emission diverged from snapshot %s. If this drift is intentional, delete the fixture and re-run.',
				$fixture_filename
			)
		);
	}

	/**
	 * Pull the `before`-position inline script for the `gtmkit` handle.
	 *
	 * @return string
	 */
	private function extract_inline_script(): string {
		$data = wp_scripts()->get_data( 'gtmkit', 'before' );
		if ( is_array( $data ) ) {
			return implode( '', $data );
		}
		return (string) $data;
	}
}
