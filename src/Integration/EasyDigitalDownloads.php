<?php
/**
 * Easy Digital Downloads.
 *
 * @see https://developers.google.com/analytics/devguides/collection/ga4/ecommerce?hl=en&client_type=gtm
 */

namespace TLA_Media\GTM_Kit\Integration;

use EDD\Orders\Order;
use EDD_Download;
use TLA_Media\GTM_Kit\Options;


/**
 * Easy Digital Downloads integration
 */
class EasyDigitalDownloads extends AbstractEcommerce {

	/**
	 * Constructor.
	 *
	 * @param Options $options
	 */
	public function __construct( Options $options ) {
		$this->store_currency = edd_get_currency();

		// Call parent constructor.
		parent::__construct( $options );
	}

	/**
	 * Get instance
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			$options        = new Options();
			self::$instance = new self( $options );
		}

		return self::$instance;
	}

	/**
	 * Register frontend
	 *
	 * @param Options $options
	 */
	public static function register( Options $options ): void {

		self::$instance = new self( $options );

		add_filter( 'gtmkit_datalayer_content', [ self::$instance, 'get_datalayer_content' ] );
	}

	/**
	 * Get the dataLayer content
	 *
	 * @param array $data_layer The datalayer content
	 *
	 * @return array The datalayer content
	 */
	public function get_datalayer_content( array $data_layer ): array {

		if ( is_singular( array( 'download' ) ) ) {
			$data_layer = $this->get_datalayer_content_product_page( $data_layer );
		} elseif ( edd_get_option( 'confirmation_page', false ) == get_the_ID() ) {
			$data_layer = $this->get_datalayer_content_order_received( $data_layer );
		} elseif ( is_page( edd_get_option( 'purchase_page' ) ) ) {
			$data_layer = $this->get_datalayer_content_checkout( $data_layer );
		}

		return $data_layer;
	}

	/**
	 * Get the dataLayer data for product pages
	 *
	 * @param array $data_layer The datalayer content
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
			'value' => $item['price']
		];

		return $data_layer;
	}

	/**
	 * Get the dataLayer data for checkout page
	 *
	 * @param array $data_layer The datalayer content
	 *
	 * @return array The datalayer content
	 */
	public function get_datalayer_content_checkout( array $data_layer ): array {
		if ( $this->options->get( 'general', 'datalayer_page_type' ) ) {
			$data_layer['pageType'] = 'checkout';
		}

		$data_layer['event']     = 'begin_checkout';
		$data_layer['ecommerce'] = [
			'items' => $this->global_settings['edd']['cart_items']
		];

		return $data_layer;
	}

	/**
	 * Get the dataLayer data for order_received page
	 *
	 * @param array $data_layer The datalayer content
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

		if ( $items = $order->get_items() ) {
			foreach ( $items as $item ) {
				$product = edd_get_download( $item->product_id );

				if ( 'no' === get_option( 'edd_settings' )['prices_include_tax'] ) {
					$item_price = $item->total - $item->tax;
				} else {
					$item_price = $item->total;
				}
				$price = round( $item_price, 2 );

				$order_items[] = $this->get_item_data(
					$product,
					[
						'quantity' => $item->quantity,
						'price'    => $price
					],
					'purchase'
				);
			}
		}

		$data_layer['event']     = 'purchase';
		$data_layer['ecommerce'] = [
			'transaction_id' => (int) $order->get_number(),
			'value'          => (float) $order_value,
			'tax'            => (float) $order->tax,
			'currency'       => $order->currency,
			'items'          => $order_items
		];

		edd_add_order_meta( $order_id, 'gtmkit_order_tracked', 1 );

		return apply_filters( 'gtmkit_datalayer_content_order_received', $data_layer );
	}

	/**
	 * Get item data.
	 *
	 * @param EDD_Download $download
	 * @param array $additional_item_attributes Any key-value pair that needs to be added to the item data.
	 * @param string $event_context The event context of the item data.
	 *
	 * @return array The item data.
	 */
	function get_item_data( EDD_Download $download, array $additional_item_attributes = [], string $event_context = '' ): array {

		if ( $this->options->get( 'integrations', 'edd_use_sku' ) && edd_use_skus() ) {
			$item_id = ( $download->get_sku() === '-' ) ? $download->get_ID() : $download->get_sku();
		} else {
			$item_id = $download->get_ID();
		}

		$item_data = [
			'id'        => $this->prefix_item_id( $item_id ),
			'item_id'   => $this->prefix_item_id( $item_id ),
			'item_name' => $download->post_title,
			'currency'  => $this->store_currency,
			'price'     => round( (float) $this->get_price_to_display( $download->get_ID() ), 2 ),
		];

		if ( $this->options->get( 'integrations', 'edd_google_business_vertical' ) ) {
			$item_data['google_business_vertical'] = $this->options->get( 'integrations', 'edd_google_business_vertical' );
		}

		$item_category_elements = $this->get_primary_product_category( $download->get_ID(), 'download_category' );

		$number_of_elements = count( $item_category_elements );

		if ( $number_of_elements ) {

			for ( $element = 0; $element < $number_of_elements; $element ++ ) {
				$designator                                 = ( $element == 0 ) ? '' : $element + 1;
				$item_data[ 'item_category' . $designator ] = $item_category_elements[ $element ];
			}

		}

		$item_data = array_merge( $item_data, $additional_item_attributes );

		return apply_filters( 'gtmkit_datalayer_item_data', $item_data, $download, $event_context );
	}

	/**
	 * Prefix an item ID
	 *
	 * @param string $item_id
	 *
	 * @return string
	 */
	function prefix_item_id( string $item_id ): string {
		$prefix = ( Options::init()->get( 'integrations', 'edd_product_id_prefix' ) ) ?: '';

		return $prefix . $item_id;
	}

	/**
	 * Get price to display
	 *
	 * @param int $download_id
	 * @param $price_index
	 *
	 * @return float
	 */
	function get_price_to_display( int $download_id, $price_index = null ): float {

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
	function get_payment_key() {
		global $edd_receipt_args;

		$session = edd_get_purchase_session();

		if ( isset( $_GET['payment_key'] ) ) {
			return urldecode( $_GET['payment_key'] );
		} else if ( $session && isset( $session['purchase_key'] ) ) {
			return $session['purchase_key'];
		} elseif ( $edd_receipt_args && isset( $edd_receipt_args['payment_key'] ) && $edd_receipt_args['payment_key'] ) {
			return $edd_receipt_args['payment_key'];
		} else {
			return false;
		}

	}
}
