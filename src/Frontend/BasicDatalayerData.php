<?php

namespace TLA_Media\GTM_Kit\Frontend;

use TLA_Media\GTM_Kit\Options;

class BasicDatalayerData {

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	protected $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Register frontend
	 *
	 * @param Options $options
	 */
	public static function register( Options $options ): void {
		$page = new static( $options );

		add_filter( 'gtmkit_datalayer_content', [ $page, 'get_datalayer_content' ], 9 );
	}

	/**
	 * Get the basic dataLayer data
	 */
	public function get_datalayer_content( array $datalayer ): array {

		$set_datalayer_post_type = $this->options->get( 'general', 'datalayer_post_type' );
		$set_datalayer_page_type = $this->options->get( 'general', 'datalayer_page_type' );

		if ( $set_datalayer_post_type ) {
			$datalayer['pagePostType'] = get_post_type();
		}

		if ( $this->options->get( 'general', 'datalayer_page_type' ) ) {
			$datalayer['pageType'] = get_post_type();
		}

		if ( is_singular() ) {

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
			if ( $set_datalayer_post_type ) {
				$datalayer['pagePostType'] = 'search-results';
			}
			if ( $set_datalayer_page_type ) {
				$datalayer['pageType'] = 'search-results';
			}

			global $wp_query;

			$datalayer['siteSearchQuery']   = get_search_query();
			$datalayer['siteSearchResults'] = $wp_query->found_posts;
		}

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

}
