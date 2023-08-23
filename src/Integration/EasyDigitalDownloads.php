<?php
/**
 * Easy Digital Downloads.
 *
 * @see https://developers.google.com/analytics/devguides/collection/ga4/ecommerce?hl=en&client_type=gtm
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Integration;

use EDD\Orders\Order;
use EDD_Download;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options;


/**
 * Easy Digital Downloads integration
 */
final class EasyDigitalDownloads extends AbstractEcommerce {

	/**
	 * Instance.
	 *
	 * @var EasyDigitalDownloads
	 */
	protected static $instance = null;

	/**
	 * Constructor.
	 *
	 * @param Options $options The Options instance.
	 * @param Util    $util The Util Instance.
	 */
	public function __construct( Options $options, Util $util ) {
		$this->store_currency = edd_get_currency();

		// Call parent constructor.
		parent::__construct( $options, $util );
	}

	/**
	 * Get instance
	 */
	public static function instance(): ?EasyDigitalDownloads {
		if ( is_null( self::$instance ) ) {
			$options         = new Options();
			$rest_api_server = new RestAPIServer();
			$util            = new Util( $rest_api_server );
			self::$instance  = new self( $options, $util );
		}

		return self::$instance;
	}

	/**
	 * Register frontend
	 *
	 * @param Options $options The Options instance.
	 * @param Util    $util The Util Instance.
	 */
	public static function register( Options $options, Util $util ): void {

		self::$instance = new self( $options, $util );

		add_filter( 'gtmkit_header_script_data', [ self::$instance, 'get_global_settings' ] );
		add_filter( 'gtmkit_header_script_data', [ self::$instance, 'get_global_data' ] );
		add_filter( 'gtmkit_datalayer_content', [ self::$instance, 'get_datalayer_content' ] );
		add_action( 'wp_enqueue_scripts', [ self::$instance, 'enqueue_scripts' ] );
		add_action( 'edd_purchase_link_end', [ self::$instance, 'add_to_cart_tracking' ] );
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts(): void {

		if ( $this->options->get( 'integrations', 'edd_dequeue_script' ) ) {
			return;
		}

		if ( ! edd_is_checkout() ) {
			wp_enqueue_script(
				'gtmkit-edd',
				GTMKIT_URL . 'assets/js/edd.js',
				[ 'jquery' ],
				$this->util->get_plugin_version(),
				true
			);
		}

		if ( edd_is_checkout() ) {
			wp_enqueue_script(
				'gtmkit-edd-checkout',
				GTMKIT_URL . 'assets/js/edd-checkout.js',
				[ 'jquery' ],
				$this->util->get_plugin_version(),
				true
			);
		}
	}

	/**
	 * Get the global script settings
	 *
	 * @param array $global_settings Script settings.
	 *
	 * @return array
	 */
	public function get_global_settings( array $global_settings ): array {

		$global_settings['edd']['use_sku']                    = (bool) $this->options->get( 'integrations', 'edd_use_sku' );
		$global_settings['edd']['add_payment_info']['config'] = (int) Options::init()->get( 'integrations', 'edd_payment_info' );
		$global_settings['edd']['text']                       = [
			'payment-method-not-found' => __( 'Payment method not found', 'gtm-kit' ),
		];

		return $global_settings;
	}

	/**
	 * Get the global script data
	 *
	 * @param array $global_data Script data.
	 *
	 * @return array
	 */
	public function get_global_data( array $global_data ): array {

		$global_data['edd']['currency']                  = $this->store_currency;
		$global_data['edd']['is_checkout']               = ( is_page( edd_get_option( 'purchase_page' ) ) );
		$global_data['edd']['add_payment_info']['fired'] = false;

		if ( is_page( edd_get_option( 'purchase_page' ) ) ) {
			$global_data['edd']['cart_items'] = $this->get_cart_items( 'begin_checkout' );
			$global_data['edd']['cart_value'] = edd_cart_total( false );
		}

		$this->global_data = $global_data;

		return $global_data;
	}

	/**
	 * Get the dataLayer content
	 *
	 * @param array $data_layer The datalayer content.
	 *
	 * @return array The datalayer content
	 */
	public function get_datalayer_content( array $data_layer ): array {

		if ( is_singular( array( 'download' ) ) ) {
			$data_layer = $this->get_datalayer_content_product_page( $data_layer );
		} elseif ( is_tax( 'download_category' ) ) {
			$data_layer = $this->get_datalayer_content_product_category( $data_layer );
		} elseif ( is_tax( 'download_tag' ) ) {
			$data_layer = $this->get_datalayer_content_product_tag( $data_layer );
		} elseif ( is_page( edd_get_option( 'confirmation_page', edd_get_option( 'success_page', 0 ) ) ) ) {
			$data_layer = $this->get_datalayer_content_order_received( $data_layer );
		} elseif ( is_page( edd_get_option( 'purchase_page' ) ) ) {
			$data_layer = $this->get_datalayer_content_checkout( $data_layer );
		}

		return $data_layer;
	}

	/**
	 * Get the dataLayer data for product pages
	 *
	 * @param array $data_layer The datalayer content.
	 *
	 * @return array The datalayer content
	 */
	public function get_datalayer_content_product_page( array $data_layer ): array {

		$product = edd_get_download( get_the_ID() );

		if ( ! ( $product instanceof EDD_Download ) ) {
			return $data_layer;
		}

		if ( $this->options->get( 'general', 'datalayer_page_type' ) ) {
			$data_layer['pageType'] = 'product-page';
		}

		$item = $this->get_item_data( $product );

		$data_layer['event']     = 'view_item';
		$data_layer['ecommerce'] = [
			'items' => [ $item ],
			'value' => $item['price'],
		];

		return $data_layer;
	}

	/**
	 * Get the dataLayer data for category pages
	 *
	 * @param array $data_layer The datalayer content.
	 *
	 * @return array The datalayer content
	 */
	public function get_datalayer_content_product_category( array $data_layer ): array {

		if ( $this->options->get( 'general', 'datalayer_page_type' ) ) {
			$data_layer['pageType'] = 'product-category';
		}

		return $data_layer;
	}

	/**
	 * Get the dataLayer data for product tag pages
	 *
	 * @param array $data_layer The datalayer content.
	 *
	 * @return array The datalayer content
	 */
	public function get_datalayer_content_product_tag( array $data_layer ): array {

		if ( $this->options->get( 'general', 'datalayer_page_type' ) ) {
			$data_layer['pageType'] = 'product-tag';
		}

		return $data_layer;
	}

	/**
	 * Get the dataLayer data for checkout page
	 *
	 * @param array $data_layer The datalayer content.
	 *
	 * @return array The datalayer content
	 */
	public function get_datalayer_content_checkout( array $data_layer ): array {
		if ( $this->options->get( 'general', 'datalayer_page_type' ) ) {
			$data_layer['pageType'] = 'checkout';
		}

		$data_layer['event']     = 'begin_checkout';
		$data_layer['ecommerce'] = [
			'items' => $this->global_data['data']['edd']['cart_items'],
		];

		return $data_layer;
	}

	/**
	 * Get the dataLayer data for order_received page
	 *
	 * @param array $data_layer The datalayer content.
	 *
	 * @return array The datalayer content
	 */
	public function get_datalayer_content_order_received( array $data_layer ): array {

		$payment_key = $this->get_payment_key();
		$order_id    = edd_get_purchase_id_by_key( $payment_key );

		if ( ! $order_id ) {
			return $data_layer;
		}

		$order = edd_get_order( $order_id );

		if ( $order instanceof Order ) {
			if ( ! $order->is_complete() ) {
				return $data_layer;
			}

			if ( ( 1 === (int) edd_get_order_meta( $order->id, 'gtmkit_order_tracked', true ) ) ) {
				return $data_layer;
			}
		} else {
			return $data_layer;
		}

		if ( $this->options->get( 'general', 'datalayer_page_type' ) ) {
			$data_layer['pageType'] = 'order-received';
		}

		if ( $this->options->get( 'integrations', 'edd_exclude_tax' ) ) {
			$order_value = $order->total - $order->tax;
		} else {
			$order_value = $order->total;
		}

		$order_items = [];

		$items = $order->get_items();

		if ( $items ) {
			foreach ( $items as $item ) {
				$product = edd_get_download( $item->product_id );

				if ( 'no' === get_option( 'edd_settings' )['prices_include_tax'] ) {
					$item_price = ( $item->total - $item->tax ) / $item->quantity;
				} else {
					$item_price = $item->total / $item->quantity;
				}
				$price = round( $item_price, 2 );

				$order_items[] = $this->get_item_data(
					$product,
					[],
					[
						'quantity' => $item->quantity,
						'price'    => $price,
					],
					'purchase'
				);
			}
		}

		$data_layer['event']     = 'purchase';
		$data_layer['ecommerce'] = [
			'transaction_id' => (string) $order->get_number(),
			'value'          => (float) $order_value,
			'tax'            => (float) $order->tax,
			'currency'       => $order->currency,
			'items'          => $order_items,
		];

		edd_add_order_meta( $order_id, 'gtmkit_order_tracked', 1 );

		return apply_filters( 'gtmkit_datalayer_content_order_received', $data_layer );
	}

	/**
	 * Get cart items.
	 *
	 * @param string $event_context The event context of the item data.
	 *
	 * @return array The cart items.
	 */
	public function get_cart_items( string $event_context ): array {
		$cart_items = [];

		foreach ( EDD()->cart->get_contents() as $cart_item ) {
			$product = edd_get_download( $cart_item['id'] );
			$options = $cart_item['options'];

			$cart_items[] = $this->get_item_data( $product, $options, [ 'quantity' => $cart_item['quantity'] ], $event_context );
		}

		return $cart_items;
	}

	/**
	 * Get item data.
	 *
	 * @param EDD_Download $download An instance of EDD_Download.
	 * @param array        $options Optional options.
	 * @param array        $additional_item_attributes Any key-value pair that needs to be added to the item data.
	 * @param string       $event_context The event context of the item data.
	 *
	 * @return array The item data.
	 */
	public function get_item_data( EDD_Download $download, array $options = [], array $additional_item_attributes = [], string $event_context = '' ): array {

		if ( $this->options->get( 'integrations', 'edd_use_sku' ) && edd_use_skus() ) {
			$item_id = ( $download->get_sku() === '-' ) ? $download->get_ID() : $download->get_sku();
		} else {
			$item_id = $download->get_ID();
		}

		if ( isset( $options['price_id'] ) ) {
			$prices      = edd_get_variable_prices( $download->get_ID() );
			$name_suffix = ' - ' . $prices[ $options['price_id'] ]['name'];
		} else {
			$name_suffix = '';
		}

		$item_data = [
			'id'        => $this->prefix_item_id( $item_id ),
			'item_id'   => $this->prefix_item_id( $item_id ),
			'item_name' => $download->post_title . $name_suffix,
			'currency'  => $this->store_currency,
			'price'     => round( (float) $this->get_price_to_display( $download->get_ID(), $options['price_id'] ?? null ), 2 ),
			'download'  => [
				'download_id' => $download->get_ID(),
			],
		];

		if ( isset( $options['price_id'] ) ) {
			$item_data['download']['price_id'] = $options['price_id'];
		}

		if ( $this->options->get( 'integrations', 'edd_google_business_vertical' ) ) {
			$item_data['google_business_vertical'] = $this->options->get( 'integrations', 'edd_google_business_vertical' );
		}

		$item_category_elements = $this->get_primary_product_category( $download->get_ID(), 'download_category' );

		$number_of_elements = count( $item_category_elements );

		if ( $number_of_elements ) {

			for ( $element = 0; $element < $number_of_elements; $element++ ) {
				$designator                                 = ( $element === 0 ) ? '' : $element + 1;
				$item_data[ 'item_category' . $designator ] = $item_category_elements[ $element ];
			}
		}

		$item_data = array_merge( $item_data, $additional_item_attributes );

		return apply_filters( 'gtmkit_datalayer_item_data', $item_data, $download, $event_context );
	}


	/**
	 * Add-to-cart tracing on single product.
	 *
	 * @hook woocommerce_after_add_to_cart_button
	 *
	 * @param int $download_id The download ID.
	 *
	 * @return void
	 */
	public function add_to_cart_tracking( int $download_id ): void {

		$product = edd_get_download( $download_id );

		if ( ( $product instanceof EDD_Download ) ) {
			$item_data = $this->get_item_data( $product );
			echo '<input type="hidden" class="gtmkit_product_data" name="gtmkit_product_data' . '" value="' . esc_attr( json_encode( $item_data ) ) . '" />' . "\n"; // phpcs:ignore
		}
	}

	/**
	 * Prefix an item ID
	 *
	 * @param string $item_id The item ID.
	 *
	 * @return string
	 */
	public function prefix_item_id( string $item_id ): string {
		return Options::init()->get( 'integrations', 'edd_product_id_prefix' ) . $item_id;
	}

	/**
	 * Get price to display
	 *
	 * @param int         $download_id The download ID.
	 * @param string|null $price_index The price index.
	 *
	 * @return float
	 */
	public function get_price_to_display( int $download_id, string $price_index = null ): float {

		if ( edd_has_variable_prices( $download_id ) ) {

			$prices = edd_get_variable_prices( $download_id );

			if ( $price_index !== null ) {

				$price = isset( $prices[ $price_index ] ) ? $prices[ $price_index ]['amount'] : 0;

			} else {

				$default_option = edd_get_default_variable_price( $download_id );
				$price          = $prices[ $default_option ]['amount'];

			}
		} else {

			$price = edd_get_download_price( $download_id );

		}

		return (float) $price;
	}

	/**
	 * Get payment key
	 *
	 * @return false|mixed|string
	 */
	public function get_payment_key() {
		global $edd_receipt_args;

		$session = edd_get_purchase_session();

		if ( isset( $_GET['payment_key'] ) ) { // phpcs:ignore
			return urldecode( $_GET['payment_key'] ); // phpcs:ignore
		} elseif ( $session && isset( $session['purchase_key'] ) ) {
			return $session['purchase_key'];
		} elseif ( $edd_receipt_args && isset( $edd_receipt_args['payment_key'] ) && $edd_receipt_args['payment_key'] ) {
			return $edd_receipt_args['payment_key'];
		} else {
			return false;
		}
	}
}
