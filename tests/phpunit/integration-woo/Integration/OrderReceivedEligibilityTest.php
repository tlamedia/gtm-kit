<?php
/**
 * Integration tests for who may receive the order-received purchase event.
 *
 * WooCommerce refuses to render order details on the order-received page to
 * anyone it cannot tie to the order: a registered customer's order requires
 * that customer to be logged in, and a guest order requires an identifiable
 * session or a verified email address. The data layer has to make the same
 * call, because otherwise the order contents, its value and the shopper's
 * details reach a page WooCommerce deliberately rendered empty.
 *
 * The first two tests here are the ones that matter most: they pin the normal
 * purchase flow, where a regression would silently cost merchants their
 * purchase data with no visible symptom.
 *
 * Runs against real WordPress and real WooCommerce so the eligibility rules
 * under test are the production ones.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\IntegrationWoo\Integration;

use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Integration\WooCommerce;
use TLA_Media\GTM_Kit\Options\Options;
use WC_Helper_Order;
use WC_Order;
use WP_UnitTestCase;

/**
 * Integration tests for {@see WooCommerce::get_datalayer_content_order_received()}.
 */
final class OrderReceivedEligibilityTest extends WP_UnitTestCase {

	/**
	 * The integration under test.
	 *
	 * @var WooCommerce
	 */
	private $integration;

	/**
	 * Boot the integration against the seeded store config.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! class_exists( WC_Helper_Order::class ) ) {
			$this->markTestSkipped( 'WooCommerce test helpers are not installed.' );
		}

		$options           = new Options();
		$this->integration = new WooCommerce( $options, new Util( $options, new RestAPIServer() ) );
	}

	/**
	 * Clear the request state each test writes into.
	 *
	 * The WooCommerce session outlives the per-test database rollback, so an
	 * adopted guest email would otherwise carry into the next test and let a
	 * visitor that should fail verification pass it.
	 */
	public function tear_down(): void {
		global $wp;

		unset( $wp->query_vars['order-received'], $_GET['key'], $_POST['email'], $_POST['check_submission'], $_POST['_wpnonce'] );
		wp_set_current_user( 0 );

		if ( WC()->session ) {
			WC()->session->set( 'customer', null );
		}

		parent::tear_down();
	}

	/**
	 * A guest who verifies their email on the block order confirmation gets
	 * the purchase event.
	 *
	 * The case WooCommerce's email check exists for: the guest returns after
	 * the grace period from a session WooCommerce cannot tie to the order,
	 * such as a payment app reopening the return link in another browser.
	 * The block template posts its nonce as `_wpnonce`.
	 */
	public function test_guest_verifying_email_on_the_block_template_gets_the_purchase_event(): void {
		$order = $this->create_order( 0 );
		$this->age_past_grace_period( $order );

		$_POST['_wpnonce'] = wp_create_nonce( 'wc_verify_email' );
		$_POST['email']    = $order->get_billing_email();

		$this->request_order_received( $order );

		$this->assertSame( 'purchase', $this->data_layer()['event'] ?? null );
	}

	/**
	 * The same on the classic template, which names the nonce `check_submission`.
	 */
	public function test_guest_verifying_email_on_the_classic_template_gets_the_purchase_event(): void {
		$order = $this->create_order( 0 );
		$this->age_past_grace_period( $order );

		$_POST['check_submission'] = wp_create_nonce( 'wc_verify_email' );
		$_POST['email']            = $order->get_billing_email();

		$this->request_order_received( $order );

		$this->assertSame( 'purchase', $this->data_layer()['event'] ?? null );
	}

	/**
	 * A verification with the wrong email is still turned away.
	 */
	public function test_guest_verifying_with_the_wrong_email_gets_nothing(): void {
		$order = $this->create_order( 0 );
		$this->age_past_grace_period( $order );

		$_POST['_wpnonce'] = wp_create_nonce( 'wc_verify_email' );
		$_POST['email']    = 'someone.else@example.test';

		$this->request_order_received( $order );

		$this->assertSame( [], $this->data_layer() );
	}

	/**
	 * Create an order in a state a shopper reaches straight after checkout.
	 *
	 * The helper leaves orders as `pending`; `processing` is what a completed
	 * checkout produces and what the data layer is expected to report on.
	 *
	 * @param int $customer_id The customer the order belongs to, 0 for a guest.
	 *
	 * @return WC_Order
	 */
	private function create_order( int $customer_id ): WC_Order {
		$order = WC_Helper_Order::create_order( $customer_id );
		$order->set_billing_email( 'shopper@example.test' );
		$order->set_status( 'processing' );
		$order->save();

		return $order;
	}

	/**
	 * Put the given order on the request the way WooCommerce's endpoint does.
	 *
	 * @param WC_Order $order The order being viewed.
	 * @param string   $key   The order key supplied in the URL, defaults to the real one.
	 */
	private function request_order_received( WC_Order $order, string $key = null ): void {
		global $wp;

		$wp->query_vars['order-received'] = (string) $order->get_id();
		$_GET['key']                      = $key ?? $order->get_order_key();
	}

	/**
	 * Build the order-received data layer for the current request.
	 *
	 * @return array<string, mixed>
	 */
	private function data_layer(): array {
		return $this->integration->get_datalayer_content_order_received( [] );
	}

	/**
	 * Adopt the order's billing email into the session, which is what
	 * WooCommerce uses to recognise a returning guest as the purchaser.
	 *
	 * @param WC_Order $order The order just placed.
	 */
	private function adopt_guest_session( WC_Order $order ): void {
		WC()->session->set( 'customer', [ 'email' => $order->get_billing_email() ] );
	}

	/**
	 * Push the order's creation date outside WooCommerce's verification grace
	 * period, so the session/email checks decide the outcome rather than the
	 * few minutes of leeway that follow checkout.
	 *
	 * @param WC_Order $order The order to age.
	 */
	private function age_past_grace_period( WC_Order $order ): void {
		$order->set_date_created( time() - HOUR_IN_SECONDS );
		$order->save();
	}

	/**
	 * A logged-in customer arriving on their own receipt gets the full event.
	 */
	public function test_logged_in_customer_viewing_own_order_gets_the_purchase_event(): void {
		$customer_id = $this->factory()->user->create( [ 'role' => 'customer' ] );
		$order       = $this->create_order( $customer_id );

		wp_set_current_user( $customer_id );
		$this->request_order_received( $order );

		$data_layer = $this->data_layer();

		$this->assertSame( 'purchase', $data_layer['event'] ?? null );
		$this->assertSame( (string) $order->get_order_number(), $data_layer['ecommerce']['transaction_id'] ?? null );
		$this->assertNotEmpty( $data_layer['ecommerce']['items'] ?? [] );
	}

	/**
	 * A guest arriving on their receipt straight after checkout gets the full
	 * event. WooCommerce allows this on the strength of the grace period that
	 * follows order creation.
	 */
	public function test_guest_viewing_own_order_after_checkout_gets_the_purchase_event(): void {
		$order = $this->create_order( 0 );

		$this->request_order_received( $order );

		$data_layer = $this->data_layer();

		$this->assertSame( 'purchase', $data_layer['event'] ?? null );
		$this->assertSame( (string) $order->get_order_number(), $data_layer['ecommerce']['transaction_id'] ?? null );
		$this->assertNotEmpty( $data_layer['ecommerce']['items'] ?? [] );
	}

	/**
	 * A guest returning later in the same session is still recognised by the
	 * email WooCommerce stored on that session, so the event still fires once
	 * the grace period has passed.
	 */
	public function test_guest_recognised_by_session_email_gets_the_purchase_event(): void {
		$order = $this->create_order( 0 );
		$this->age_past_grace_period( $order );
		$this->adopt_guest_session( $order );

		$this->request_order_received( $order );

		$this->assertSame( 'purchase', $this->data_layer()['event'] ?? null );
	}

	/**
	 * A logged-out stranger holding a valid link to a registered customer's
	 * order gets nothing. WooCommerce shows them a login form.
	 */
	public function test_logged_out_visitor_viewing_registered_customers_order_gets_nothing(): void {
		$customer_id = $this->factory()->user->create( [ 'role' => 'customer' ] );
		$order       = $this->create_order( $customer_id );

		$this->request_order_received( $order );

		$this->assertSame( [], $this->data_layer() );
	}

	/**
	 * The same for a logged-in visitor who is not the purchaser.
	 */
	public function test_other_logged_in_user_viewing_registered_customers_order_gets_nothing(): void {
		$customer_id = $this->factory()->user->create( [ 'role' => 'customer' ] );
		$stranger_id = $this->factory()->user->create( [ 'role' => 'customer' ] );
		$order       = $this->create_order( $customer_id );

		wp_set_current_user( $stranger_id );
		$this->request_order_received( $order );

		$this->assertSame( [], $this->data_layer() );
	}

	/**
	 * A guest order opened from a session WooCommerce cannot tie to the
	 * purchaser gets nothing; WooCommerce asks for email verification.
	 */
	public function test_unverified_guest_order_view_gets_nothing(): void {
		$order = $this->create_order( 0 );
		$this->age_past_grace_period( $order );

		$this->request_order_received( $order );

		$this->assertSame( [], $this->data_layer() );
	}

	/**
	 * A merchant who relaxes WooCommerce's own rule gets the event back with
	 * no GTM Kit setting involved.
	 */
	public function test_relaxing_the_woocommerce_filter_restores_the_event(): void {
		$customer_id = $this->factory()->user->create( [ 'role' => 'customer' ] );
		$order       = $this->create_order( $customer_id );

		add_filter( 'woocommerce_order_received_verify_known_shoppers', '__return_false' );
		$this->request_order_received( $order );

		$data_layer = $this->data_layer();

		remove_filter( 'woocommerce_order_received_verify_known_shoppers', '__return_false' );

		$this->assertSame( 'purchase', $data_layer['event'] ?? null );
	}

	/**
	 * A withheld view must not consume the one-shot tracking flag: the real
	 * customer's later visit still has to produce the purchase event.
	 */
	public function test_withheld_view_does_not_consume_the_tracking_flag(): void {
		$customer_id = $this->factory()->user->create( [ 'role' => 'customer' ] );
		$order       = $this->create_order( $customer_id );

		// A stranger looks first.
		$this->request_order_received( $order );
		$this->assertSame( [], $this->data_layer() );

		$after_stranger = wc_get_order( $order->get_id() );
		$this->assertNotSame( 1, (int) $after_stranger->get_meta( '_gtmkit_order_tracked' ) );

		// The customer then opens their own receipt.
		wp_set_current_user( $customer_id );
		$this->request_order_received( $order );

		$this->assertSame( 'purchase', $this->data_layer()['event'] ?? null );
	}

	/**
	 * The customer block describes the purchaser, taken off the order, rather
	 * than whatever the viewer's own session happens to hold.
	 */
	public function test_customer_block_is_sourced_from_the_order(): void {
		$customer_id = $this->factory()->user->create(
			[
				'role'       => 'customer',
				'first_name' => 'Account',
				'last_name'  => 'Holder',
			]
		);

		$order = $this->create_order( $customer_id );
		$order->set_billing_first_name( 'Ordered' );
		$order->set_billing_last_name( 'Byme' );
		$order->set_billing_city( 'Order City' );
		$order->set_billing_phone( '+1 555 0100' );
		$order->save();

		// A session that describes somebody else entirely. Nothing emitted may
		// come from here.
		WC()->customer->set_billing_first_name( 'Session' );
		WC()->customer->set_billing_last_name( 'Leak' );
		WC()->customer->set_billing_city( 'Leak City' );
		WC()->customer->set_billing_email( 'leak@example.test' );

		update_option( 'gtmkit', $this->options_with_customer_data() );

		wp_set_current_user( $customer_id );
		$this->request_order_received( $order );

		$data_layer = ( new WooCommerce( new Options(), new Util( new Options(), new RestAPIServer() ) ) )
			->get_datalayer_content_order_received( [] );

		$customer = $data_layer['ecommerce']['customer'] ?? [];

		$this->assertSame( 'Ordered', $customer['billing_first_name'] ?? null );
		$this->assertSame( 'Byme', $customer['billing_last_name'] ?? null );
		$this->assertSame( 'Order City', $customer['billing_city'] ?? null );
		$this->assertSame( 'shopper@example.test', $customer['billing_email'] ?? null );
		$this->assertSame( $customer_id, $customer['id'] ?? null );

		// The account's own name still reports the account, which is what the
		// non-billing name fields have always described.
		$this->assertSame( 'Account', $customer['first_name'] ?? null );

		$this->assertSame(
			hash( 'sha256', 'shopper@example.test' ),
			$data_layer['user_data']['sha256_email_address'] ?? null
		);
		$this->assertSame( 'Order City', $data_layer['user_data']['address']['city'] ?? null );

		$encoded = wp_json_encode( $data_layer );
		$this->assertStringNotContainsString( 'Leak', is_string( $encoded ) ? $encoded : '' );
	}

	/**
	 * A guest order reports the buyer's own billing details and falls back to
	 * the order's value for the lifetime figures it cannot know.
	 */
	public function test_guest_customer_block_is_sourced_from_the_order(): void {
		$order = $this->create_order( 0 );
		$order->set_billing_first_name( 'Guest' );
		$order->set_billing_last_name( 'Shopper' );
		$order->save();

		update_option( 'gtmkit', $this->options_with_customer_data() );

		$this->request_order_received( $order );

		$data_layer = ( new WooCommerce( new Options(), new Util( new Options(), new RestAPIServer() ) ) )
			->get_datalayer_content_order_received( [] );

		$customer = $data_layer['ecommerce']['customer'] ?? [];

		$this->assertSame( 0, $customer['id'] ?? null );
		$this->assertSame( 'Guest', $customer['first_name'] ?? null );
		$this->assertSame( 'Guest', $customer['billing_first_name'] ?? null );
		$this->assertSame( 'Shopper', $customer['billing_last_name'] ?? null );
		$this->assertSame( 1, $customer['order_count'] ?? null );
	}

	/**
	 * The seeded store config with the customer block switched on.
	 *
	 * @return array<string, mixed>
	 */
	private function options_with_customer_data(): array {
		$options = get_option( 'gtmkit' );
		$options = is_array( $options ) ? $options : [];

		$options['integrations']['woocommerce_include_customer_data'] = '1';

		return $options;
	}

	/**
	 * An order key that does not match is rejected.
	 */
	public function test_mismatched_order_key_gets_nothing(): void {
		$order = $this->create_order( 0 );

		$this->request_order_received( $order, 'wc_order_wrongkey' );

		$this->assertSame( [], $this->data_layer() );
	}
}
