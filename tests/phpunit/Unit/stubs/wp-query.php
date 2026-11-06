<?php
/**
 * Bare `WP_Query` stub for the BrainMonkey unit harness.
 *
 * @package TLA_Media\GTM_Kit
 */

if ( class_exists( 'WP_Query' ) ) {
	return;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound, Squiz.Commenting.ClassComment.Missing -- Mirrors the WordPress core class name so unit-test typehints resolve without booting WP.
class WP_Query {

	/**
	 * The number of posts the query found.
	 *
	 * @var int
	 */
	public $found_posts = 0;

	/**
	 * The current post.
	 *
	 * @var object|null
	 */
	public $post = null;
}
