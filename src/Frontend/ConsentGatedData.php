<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Frontend;

/**
 * Separates the buyer's contact details from the purchase event.
 *
 * Contact details, hashed or plain, follow the visitor's advertising consent:
 * where the page sets a Consent Mode state for `ad_storage` or `ad_user_data`,
 * they are sent only once both are granted. The page is built before that is
 * known and may be cached, so the decision is made in the browser: the
 * details are printed apart from the event, and the event is pushed with them
 * only when consent allows it.
 *
 * The split happens where the event is printed, after every filter has run,
 * so an integration or filter that adds the standard fields is covered too.
 * The customer id, order count and total spent are not contact details and
 * stay on the event.
 */
final class ConsentGatedData {

	/**
	 * Top-level keys held back whole.
	 *
	 * @var array<int, string>
	 */
	private const HELD_KEYS = [ 'user_data' ];

	/**
	 * Keys under `ecommerce.customer` that hold contact details.
	 *
	 * @var array<int, string>
	 */
	private const HELD_CUSTOMER_KEYS = [
		'name',
		'first_name',
		'last_name',
		'billing_first_name',
		'billing_last_name',
		'billing_company',
		'billing_address_1',
		'billing_address_2',
		'billing_city',
		'billing_postcode',
		'billing_country',
		'billing_state',
		'billing_email',
		'billing_email_hash',
		'billing_phone',
		'shipping_firstName',
		'shipping_lastName',
		'shipping_company',
		'shipping_address_1',
		'shipping_address_2',
		'shipping_city',
		'shipping_postcode',
		'shipping_country',
		'shipping_state',
	];

	/**
	 * Split the contact details off a purchase event.
	 *
	 * Any other payload is returned unchanged with nothing held.
	 *
	 * @param array<string, mixed> $data The data layer payload.
	 *
	 * @return array{0: array<string, mixed>, 1: array<string, mixed>} The payload without the details, and the details shaped like the payload.
	 */
	public static function split( array $data ): array {

		if ( ( $data['event'] ?? '' ) !== 'purchase' ) {
			return [ $data, [] ];
		}

		$held = [];

		foreach ( self::HELD_KEYS as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$held[ $key ] = $data[ $key ];
				unset( $data[ $key ] );
			}
		}

		if ( isset( $data['ecommerce']['customer'] ) && is_array( $data['ecommerce']['customer'] ) ) {
			foreach ( self::HELD_CUSTOMER_KEYS as $key ) {
				if ( array_key_exists( $key, $data['ecommerce']['customer'] ) ) {
					$held['ecommerce']['customer'][ $key ] = $data['ecommerce']['customer'][ $key ];
					unset( $data['ecommerce']['customer'][ $key ] );
				}
			}
		}

		return [ $data, $held ];
	}
}
