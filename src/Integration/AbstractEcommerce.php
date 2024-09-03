<?php
/**
 * E-commerce integration
 *
 * @see https://developers.google.com/analytics/devguides/collection/ga4/ecommerce?hl=en&client_type=gtm
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Integration;

use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options;

/**
 * AbstractEcommerce
 */
abstract class AbstractEcommerce extends AbstractIntegration {

	/**
	 * Store currency.
	 *
	 * @var string
	 */
	protected string $store_currency;

	/**
	 * Grouped product list position.
	 *
	 * @var int
	 */
	protected int $grouped_product_position;

	/**
	 * Global data.
	 *
	 * @var array<string, mixed>
	 */
	protected array $global_data;

	/**
	 * Constructor.
	 *
	 * @param Options $options The Options instance.
	 * @param Util    $util The Util instance.
	 */
	public function __construct( Options $options, Util $util ) {
		$this->grouped_product_position = 1;

		// Call parent constructor.
		parent::__construct( $options, $util );
	}

	/**
	 * Get instance
	 */
	abstract public static function instance(): self;

	/**
	 * Register frontend
	 *
	 * @param Options $options The Options instance.
	 * @param Util    $util The Util instance.
	 */
	abstract public static function register( Options $options, Util $util ): void;

	/**
	 * Get the primary product category of a product. If the  primary category is not available the first assigned category will be returned.
	 *
	 * @param int    $product_id The product ID.
	 * @param string $taxonomy The taxonomy slug.
	 *
	 * @return array<int|string, mixed> The category breadcrumb for the given product ID.
	 */
	public function get_primary_product_category( int $product_id, string $taxonomy ): array {

		$primary_product_category = [];

		$primary_term_id = false;

		if ( function_exists( 'yoast_get_primary_term_id' ) ) {
			$primary_term_id = yoast_get_primary_term_id( $taxonomy, $product_id );
		} elseif ( function_exists( 'rank_math' ) ) {
			$primary_cat_id = get_post_meta( $product_id, 'rank_math_primary_' . $taxonomy, true );
			if ( ! empty( $primary_cat_id ) && intval( $primary_cat_id ) ) {
				$primary_term_id = $primary_cat_id;
			}
		}

		if ( $primary_term_id === false ) {
			$product_categories = wp_get_post_terms(
				$product_id,
				$taxonomy,
				array(
					'orderby' => 'parent',
					'order'   => 'ASC',
				)
			);

			if ( count( $product_categories ) ) {
				$first_product_category = array_pop( $product_categories );
				$primary_term_id        = $first_product_category->term_id;
			} else {
				$primary_term_id = false;
			}
		}

		if ( $primary_term_id ) {
			$primary_product_category = $this->get_category_breadcrumb( $primary_term_id, $taxonomy );
		}

		return $primary_product_category;
	}

	/**
	 * Get the product category breadcrumb elements as an array.
	 *
	 * @param int    $category_id The ID of the product category.
	 * @param string $taxonomy The taxonomy slug.
	 *
	 * @return array<int, string> The category path elements as an array.
	 */
	public function get_category_breadcrumb( int $category_id, string $taxonomy ): array {
		static $categories = [];

		if ( isset( $categories[ $category_id ] ) ) {
			return $categories[ $category_id ];
		}

		$category_hierarchy = [];
		$category           = get_term( $category_id, $taxonomy );
		if ( $category ) {
			$parents = get_ancestors( $category_id, $taxonomy, 'taxonomy' );
			array_unshift( $parents, $category_id );
			foreach ( $parents as $category_id ) {
				$parent = get_term( $category_id, $taxonomy );
				if ( $parent ) {
					array_unshift( $category_hierarchy, $parent->name );
				}
			}
		}
		$categories[ $category_id ] = $category_hierarchy;

		return $category_hierarchy; }

	/**
	 * Get product term value.
	 *
	 * @param int    $product_id A product ID.
	 * @param string $taxonomy The taxonomy slug.
	 *
	 * @return string Returns the first assigned taxonomy value.
	 */
	public function get_product_term( int $product_id, string $taxonomy ): string {

		$product_terms = wp_get_post_terms(
			$product_id,
			$taxonomy,
			[
				'orderby' => 'parent',
				'order'   => 'ASC',
			]
		);

		return ( is_array( $product_terms ) && count( $product_terms ) ) ? $product_terms[0]->name : '';
	}

	/**
	 * Prefix an item ID
	 *
	 * @param string $item_id The item ID.
	 *
	 * @return string
	 */
	abstract public function prefix_item_id( string $item_id ): string;
}
