<?php
/**
 * e-commerce integration
 *
 * @see https://developers.google.com/analytics/devguides/collection/ga4/ecommerce?hl=en&client_type=gtm
 */

namespace TLA_Media\GTM_Kit\Integration;

use TLA_Media\GTM_Kit\Options;

abstract class AbstractEcommerce extends AbstractIntegration {

	/**
	 * Store currency.
	 *
	 * @var string
	 */
	protected $store_currency;

	/**
	 * Grouped product list position.
	 *
	 * @var int
	 */
	protected $grouped_product_position;

	/**
	 * Global Settings.
	 *
	 * @var array
	 */
	protected $global_settings;

	/**
	 * Constructor.
	 *
	 * @param Options $options
	 */
	public function __construct( Options $options ) {
		$this->options                  = $options;
		$this->grouped_product_position = 1;
	}

	/**
	 * Get instance
	 */
	abstract static function instance();

	/**
	 * Register frontend
	 *
	 * @param Options $options
	 */
	abstract public static function register( Options $options ): void;

	/**
	 * Get the primary product category of a product. If the  primary category is not available the first assigned category will be returned.
	 *
	 * @param int $product_id The product ID
	 *
	 * @return array The category breadcrumb for the given product ID.
	 */
	function get_primary_product_category( int $product_id, string $taxonomy ): array {

		$primary_product_category = [];

		$primary_term_id = false;

		if ( function_exists( 'yoast_get_primary_term_id' ) ) {
			$primary_term_id = yoast_get_primary_term_id( $taxonomy, $product_id );
		} elseif ( function_exists( 'rank_math' ) ) {
			$primary_cat_id = get_post_meta( $product_id, 'rank_math_primary_' . $taxonomy, true );
			if ( isset( $primary_cat_id ) && ! empty( $primary_cat_id ) && intval( $primary_cat_id ) ) {
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
	 * @param int $category_id The ID of the product category.
	 *
	 * @return array The category path elements as an array.
	 */
	function get_category_breadcrumb( int $category_id, string $taxonomy ): array {
		$category_hierarchy = [];

		$category = get_term( $category_id, $taxonomy );

		if ( ! $category ) {
			return $category_hierarchy;
		}

		$parents = get_ancestors( $category_id, $taxonomy, 'taxonomy' );

		array_unshift( $parents, $category_id );

		foreach ( array_reverse( $parents ) as $category_id ) {
			$parent               = get_term( $category_id, $taxonomy );
			$category_hierarchy[] = $parent->name;

		}

		return $category_hierarchy;
	}

	/**
	 * Get product term value.
	 *
	 * @param int $product_id A product ID
	 * @param string $taxonomy The taxonomy slug
	 *
	 * @return string Returns the first assigned taxonomy value.
	 */
	function get_product_term( int $product_id, string $taxonomy ): string {

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
	 * @param string $item_id
	 *
	 * @return string
	 */
	abstract public function prefix_item_id( string $item_id ): string;

}
