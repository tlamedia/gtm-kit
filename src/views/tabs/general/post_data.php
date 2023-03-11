<?php

namespace TLA_Media\GTM_Kit;

use TLA_Media\GTM_Kit\Admin\OptionsForm;

/** @var OptionsForm $form */

if ( ! defined( 'GTMKIT_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}
?>
<div class="gtmkit-setting-row gtmkit-setting-row-heading gtmkit-clear">
	<h2>
		<?php esc_html_e( 'Post data', 'gtm-kit' ); ?>
	</h2>
	<?php esc_html_e( 'Specify which post data elements you wish to include in the dataLayer for use in Google Tag Manager.', 'gtm-kit' ); ?>
</div>

<?php
$form->setting_row(
	'checkbox-toggle',
	'datalayer_post_type',
	__( 'Post type', 'gtm-kit' ),
	[],
	__( 'include the type of the current post or archive page.', 'gtm-kit' )
);

$form->setting_row(
	'checkbox-toggle',
	'datalayer_page_type',
	__( 'Page type', 'gtm-kit' ),
	[],
	__( 'include the defined page type. I.e. post, page, product, category, cart, checkout etc. You may override this on page-level and set you own page type i.e. "campaign".', 'gtm-kit' )
);

$form->setting_row(
	'checkbox-toggle',
	'datalayer_categories',
	__( 'Categories', 'gtm-kit' ),
	[],
	__( 'include the categories of the current post or archive page.', 'gtm-kit' )
);

$form->setting_row(
	'checkbox-toggle',
	'datalayer_tags',
	__( 'Tags', 'gtm-kit' ),
	[],
	__( 'include the tags of the current post or archive page.', 'gtm-kit' )
);

$form->setting_row(
	'checkbox-toggle',
	'datalayer_post_title',
	__( 'Post title', 'gtm-kit' ),
	[],
	__( 'include the post title of the current post.', 'gtm-kit' )
);

$form->setting_row(
	'checkbox-toggle',
	'datalayer_post_id',
	__( 'Post ID', 'gtm-kit' ),
	[],
	__( 'include the Post ID of the current post.', 'gtm-kit' )
);

$form->setting_row(
	'checkbox-toggle',
	'datalayer_post_date',
	__( 'Post date', 'gtm-kit' ),
	[],
	__( 'include the post date.', 'gtm-kit' )
);

$form->setting_row(
	'checkbox-toggle',
	'datalayer_post_author_name',
	__( 'Post author name', 'gtm-kit' ),
	[],
	__( 'include the post author name.', 'gtm-kit' )
);

$form->setting_row(
	'checkbox-toggle',
	'datalayer_post_author_id',
	__( 'Post author ID', 'gtm-kit' ),
	[],
	__( 'include the post author ID.', 'gtm-kit' )
);

?>
