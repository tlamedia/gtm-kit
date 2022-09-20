<?php

namespace TLA_Media\GTM_Kit;

use TLA_Media\GTM_Kit\Admin\HelpPanel;
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
		<?php esc_html_e( 'Post data', 'gtmkit' ); ?>
	</h2>
	<?php esc_html_e( 'Specify which post data elements you wish to include in the dataLayer for use in Google Tag Manager.', 'gtmkit' ); ?>
</div>

<?php
$form->setting_row(
	'checkbox-toggle',
	'datalayer_post_type',
	__( 'Post type', 'gtmkit' ),
	[],
	__( 'include the type of the current post or archive page.', 'gtmkit' )
);

$form->setting_row(
	'checkbox-toggle',
	'datalayer_page_type',
	__( 'Page type', 'gtmkit' ),
	[],
	__( 'include the defined page type. I.e. post, page, product, category, cart, checkout etc.', 'gtmkit' )
);

$form->setting_row(
	'checkbox-toggle',
	'datalayer_categories',
	__( 'Categories', 'gtmkit' ),
	[],
	__( 'include the categories of the current post or archive page.', 'gtmkit' )
);

$form->setting_row(
	'checkbox-toggle',
	'datalayer_tags',
	__( 'Tags', 'gtmkit' ),
	[],
	__( 'include the tags of the current post or archive page.', 'gtmkit' )
);

$form->setting_row(
	'checkbox-toggle',
	'datalayer_post_title',
	__( 'Post title', 'gtmkit' ),
	[],
	__( 'include the post title of the current post.', 'gtmkit' )
);

$form->setting_row(
	'checkbox-toggle',
	'datalayer_post_id',
	__( 'Post ID', 'gtmkit' ),
	[],
	__( 'include the Post ID of the current post.', 'gtmkit' )
);

$form->setting_row(
	'checkbox-toggle',
	'datalayer_post_date',
	__( 'Post date', 'gtmkit' ),
	[],
	__( 'include the post date.', 'gtmkit' )
);

$form->setting_row(
	'checkbox-toggle',
	'datalayer_post_author_name',
	__( 'Post author name', 'gtmkit' ),
	[],
	__( 'include the post author name.', 'gtmkit' )
);

$form->setting_row(
	'checkbox-toggle',
	'datalayer_post_author_id',
	__( 'Post author ID', 'gtmkit' ),
	[],
	__( 'include the post author ID.', 'gtmkit' )
);

?>
