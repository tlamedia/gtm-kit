<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Frontend;

use TLA_Media\GTM_Kit\Options;

/**
 * BasicDatalayerData
 */
final class BasicDatalayerData {

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	protected $options;

	/**
	 * Set datalayer post type.
	 *
	 * @var bool
	 */
	protected $set_datalayer_post_type;

	/**
	 * Set datalayer page type.
	 *
	 * @var bool
	 */
	protected $set_datalayer_page_type;

	/**
	 * Constructor.
	 *
	 * @param Options $options An instance of Options.
	 */
	public function __construct( Options $options ) {
		$this->options                 = $options;
		$this->set_datalayer_post_type = (bool) $this->options->get( 'general', 'datalayer_post_type' );
		$this->set_datalayer_page_type = (bool) $this->options->get( 'general', 'datalayer_page_type' );
	}

	/**
	 * Register frontend
	 *
	 * @param Options $options An instance of Options.
	 */
	public static function register( Options $options ): void {
		$page = new BasicDatalayerData( $options );

		add_filter( 'gtmkit_datalayer_content', [ $page, 'get_datalayer_content' ], 9 );
		add_filter( 'gtmkit_datalayer_content', [ $page, 'get_priority_datalayer_content' ], 99 );
	}

	/**
	 * Get the basic dataLayer data
	 *
	 * @param array $datalayer The datalayer.
	 *
	 * @return array
	 */
	public function get_datalayer_content( array $datalayer ): array {

		$datalayer = $this->set_post_and_page_types( $datalayer, get_post_type() );

		if ( is_singular() ) {
			if ( is_front_page() ) {
				$datalayer = $this->set_post_and_page_types( $datalayer, 'frontpage' );
			} elseif ( is_home() ) {
				$datalayer = $this->set_post_and_page_types( $datalayer, 'blog_home' );
			} elseif ( is_search() ) {
				$datalayer = $this->set_post_and_page_types( $datalayer, 'search-results' );
				$datalayer = $this->get_site_search_datalayer_content( $datalayer );
			}
			$datalayer = $this->get_singular_datalayer_content( $datalayer );
		} elseif ( is_archive() || is_post_type_archive() ) {
			if ( ( is_tax() || is_category() ) && $this->options->get( 'general', 'datalayer_categories' ) ) {
				$categories = get_the_category();
				foreach ( $categories as $category ) {
					$datalayer['pageCategory'][] = $category->slug;
				}
			}
		} elseif ( is_404() ) {
			$datalayer = $this->set_post_and_page_types( $datalayer, '404-error' );
		}

		return $datalayer;
	}

	/**
	 * Set post and page types in the datalayer
	 *
	 * @param array  $datalayer The datalayer.
	 * @param string $post_type The post type.
	 * @param string $page_type The page type.
	 *
	 * @return array
	 */
	private function set_post_and_page_types( array $datalayer, string $post_type, string $page_type = '' ): array {

		if ( $this->set_datalayer_post_type ) {
			$datalayer['pagePostType'] = $post_type;
		}
		if ( $this->set_datalayer_page_type ) {
			$datalayer['pageType'] = ( empty( $page_type ) ) ? $post_type : $page_type;
		}

		return $datalayer;
	}

	/**
	 * Get priority dataLayer data
	 *
	 * @param array $datalayer The datalayer.
	 *
	 * @return array
	 */
	public function get_priority_datalayer_content( array $datalayer ): array {

		if ( $this->options->get( 'general', 'datalayer_page_type' ) ) {
			$page_type = get_post_meta( get_the_ID(), 'gtmkit_page_type', true );
			if ( $page_type ) {
				$datalayer['pageType'] = $page_type;
			}
		}
		return $datalayer;
	}

	/**
	 * Get the dataLayer data for singular post types
	 *
	 * @param array $datalayer The datalayer.
	 *
	 * @return array
	 */
	private function get_singular_datalayer_content( array $datalayer ): array {

		global $post;

		if ( $this->options->get( 'general', 'datalayer_categories' ) ) {
			$post_categories = get_the_category();
			if ( $post_categories ) {
				foreach ( $post_categories as $category ) {
					$datalayer['pageCategory'][] = $category->slug;
				}
			}
		}

		if ( $this->options->get( 'general', 'datalayer_tags' ) ) {
			$post_tags = get_the_tags();
			if ( $post_tags ) {
				foreach ( $post_tags as $tag ) {
					$datalayer['pageAttributes'][] = $tag->slug;
				}
			}
		}

		if ( $this->options->get( 'general', 'datalayer_post_title' ) ) {
			$datalayer['postTitle'] = $post->post_title;
		}

		if ( $this->options->get( 'general', 'datalayer_post_id' ) ) {
			$datalayer['postId'] = $post->ID;
		}

		if ( $this->options->get( 'general', 'datalayer_post_date' ) ) {
			$datalayer['postDate'] = get_the_date( 'Y-m-d' );
		}

		if ( $this->options->get( 'general', 'datalayer_post_author_id' ) ) {
			$author                  = get_userdata( $post->post_author );
			$datalayer['authorName'] = $author->display_name;
		}

		if ( $this->options->get( 'general', 'datalayer_post_author_name' ) ) {
			$datalayer['authorId'] = (int) $post->post_author;
		}

		return $datalayer;
	}

	/**
	 * Get the dataLayer data for site search
	 *
	 * @param array $datalayer The datalayer.
	 *
	 * @return array
	 */
	private function get_site_search_datalayer_content( array $datalayer ): array {
		global $wp_query;

		$datalayer['siteSearchQuery']   = get_search_query();
		$datalayer['siteSearchResults'] = $wp_query->found_posts;

		return $datalayer;
	}
}
