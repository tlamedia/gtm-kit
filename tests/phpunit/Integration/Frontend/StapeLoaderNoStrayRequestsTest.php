<?php
/**
 * Integration tests proving Stape is asked for a loader only from the settings screen.
 *
 * The site is configured so that a fetch would be due: the issued loader is
 * switched on, every setting it needs is filled in, and nothing is stored.
 * Every outbound request is answered locally, and requests to Stape are
 * counted.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Frontend;

use TLA_Media\GTM_Kit\Common\GoogleTagGatewayHealth;
use TLA_Media\GTM_Kit\Common\SnippetScan;
use TLA_Media\GTM_Kit\Common\StapeLoader;
use TLA_Media\GTM_Kit\Common\SupportSync;
use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * No page view, scheduled event or settings change outside the screen reaches Stape.
 */
final class StapeLoaderNoStrayRequestsTest extends WP_UnitTestCase {

	/**
	 * Requests that reached Stape.
	 *
	 * @var int
	 */
	private int $stape_requests = 0;

	/**
	 * Configure a site on which a fetch would be due.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		OptionsFactory::get_instance()->set(
			[
				'general' => [
					'gtm_id'                    => 'GTM-TW5FD4G7',
					'sgtm_domain'               => 'collect.gtmkit.com',
					'sgtm_container_identifier' => '38i0hixjpkyq',
					'sgtm_cookie_keeper'        => true,
					'sgtm_stape_issued_loader'  => true,
					'google_tag_gateway'        => false,
				],
			]
		);

		delete_option( StapeLoader::OPTION );

		$this->stape_requests = 0;
		add_filter( 'pre_http_request', [ $this, 'answer_locally' ], 10, 3 );
	}

	/**
	 * Remove the HTTP stub.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', [ $this, 'answer_locally' ], 10 );

		parent::tear_down();
	}

	/**
	 * Answer every outbound request locally, counting those addressed to Stape.
	 *
	 * @param false|array<string, mixed> $pre  The short-circuit value.
	 * @param array<string, mixed>       $args The request arguments.
	 * @param string                     $url  The request URL.
	 *
	 * @return array<string, mixed>
	 */
	public function answer_locally( $pre, $args, $url ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- Signature fixed by the pre_http_request filter.
		if ( strpos( (string) $url, 'stape.io' ) !== false ) {
			++$this->stape_requests;
		}

		return [
			'headers'  => [],
			'body'     => '<!DOCTYPE html><html><head></head><body></body></html>',
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	/**
	 * Rendering a page does not ask Stape.
	 *
	 * @return void
	 */
	public function test_a_page_view_does_not_ask_stape(): void {
		$this->go_to( home_url( '/' ) );

		ob_start();
		( new Frontend( OptionsFactory::get_instance() ) )->get_gtm_script( 'GTM-TW5FD4G7' );
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Firing WordPress's own page hooks to render a page.
		do_action( 'wp_enqueue_scripts' );
		do_action( 'wp_head' );
		do_action( 'wp_footer' );
		// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		ob_end_clean();

		$this->assertSame( 0, $this->stape_requests );
	}

	/**
	 * Running GTM Kit's scheduled events does not ask Stape.
	 *
	 * @return void
	 */
	public function test_scheduled_events_do_not_ask_stape(): void {
		$hooks = [ SnippetScan::SCAN_HOOK, GoogleTagGatewayHealth::CHECK_HOOK, SupportSync::PUSH_HOOK ];

		foreach ( (array) _get_cron_array() as $events ) {
			foreach ( array_keys( (array) $events ) as $hook ) {
				if ( strpos( (string) $hook, 'gtmkit' ) === 0 ) {
					$hooks[] = (string) $hook;
				}
			}
		}

		foreach ( array_unique( $hooks ) as $hook ) {
			do_action( $hook ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Every hook here starts with gtmkit.
		}

		$this->assertSame( 0, $this->stape_requests );
	}

	/**
	 * A settings change written outside the settings screen, as an import,
	 * an upgrade routine or WP-CLI writes it, does not ask Stape.
	 *
	 * @return void
	 */
	public function test_settings_written_outside_the_settings_screen_do_not_ask_stape(): void {
		OptionsFactory::get_instance()->set(
			[
				'general' => [
					'sgtm_container_identifier' => 'importedid',
					'datalayer_name'            => 'importedLayer',
				],
			]
		);

		$this->assertSame( 0, $this->stape_requests );
		$this->assertFalse( get_option( StapeLoader::OPTION ) );
	}
}
