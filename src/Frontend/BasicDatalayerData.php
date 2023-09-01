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
	 * Constructor.
	 *
	 * @param Options $options An instance of Options.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
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

		$set_datalayer_post_type = (bool) $this->options->get( 'general', 'datalayer_post_type' );
		$set_datalayer_page_type = (bool) $this->options->get( 'general', 'datalayer_page_type' );

		if ( $set_datalayer_post_type ) {
			$datalayer['pagePostType'] = get_post_type();
		}

		if ( $this->options->get( 'general', 'datalayer_page_type' ) ) {
			$datalayer['pageType'] = get_post_type();
		}

		if ( is_singular() ) {
			$datalayer = $this->get_singular_datalayer_content( $datalayer );
		}

		if ( is_archive() || is_post_type_archive() ) {
			if ( ( is_tax() || is_category() ) && $this->options->get( 'general', 'datalayer_categories' ) ) {
				$categories = get_the_category();
				foreach ( $categories as $category ) {
					$datalayer['pageCategory'][] = $category->slug;
				}
			}
		}

		if ( is_search() ) {
			$datalayer = $this->get_site_search_datalayer_content( $datalayer, $set_datalayer_post_type, $set_datalayer_page_type );
		}

		if ( is_front_page() || is_home() ) {
			$datalayer = $this->get_frontpage_datalayer_content( $datalayer, $set_datalayer_post_type, $set_datalayer_page_type );
		}

		if ( is_404() ) {
			if ( $set_datalayer_post_type ) {
				$datalayer['pagePostType'] = '404-error';
			}
			if ( $set_datalayer_page_type ) {
				$datalayer['pageType'] = '404-error';
			}
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
	 * Get the dataLayer data for the frontpage and home page
	 *
	 * @param array $datalayer The datalayer.
	 * @param bool  $set_datalayer_post_type Whether the pagePostType property is active.
	 * @param bool  $set_datalayer_page_type Whether the pageType property is active.
	 *
	 * @return array
	 */
	private function get_frontpage_datalayer_content( array $datalayer, bool $set_datalayer_post_type, bool $set_datalayer_page_type ): array {

		if ( is_front_page() ) {
			if ( $set_datalayer_post_type ) {
				$datalayer['pagePostType'] = 'frontpage';
			}
			if ( $set_datalayer_page_type ) {
				$datalayer['pageType'] = 'frontpage';
			}
		}

		if ( ! is_front_page() && is_home() ) {
			if ( $set_datalayer_post_type ) {
				$datalayer['pagePostType'] = 'blog_home';
			}
			if ( $set_datalayer_page_type ) {
				$datalayer['pageType'] = 'blog_home';
			}
		}

		return $datalayer;
	}

	/**
	 * Get the dataLayer data for site search
	 *
	 * @param array $datalayer The datalayer.
	 * @param bool  $set_datalayer_post_type Whether the pagePostType property is active.
	 * @param bool  $set_datalayer_page_type Whether the pageType property is active.
	 *
	 * @return array
	 */
	private function get_site_search_datalayer_content( array $datalayer, bool $set_datalayer_post_type, bool $set_datalayer_page_type ): array {

		if ( $set_datalayer_post_type ) {
			$datalayer['pagePostType'] = 'search-results';
		}
		if ( $set_datalayer_page_type ) {
			$datalayer['pageType'] = 'search-results';
		}

		global $wp_query;

		$datalayer['siteSearchQuery']   = get_search_query();
		$datalayer['siteSearchResults'] = $wp_query->found_posts;

		return $datalayer;
	}
}
