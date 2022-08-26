<?php

namespace TLA\GTM_Kit;

use TLA\GTM_Kit\Admin\HelpPanel;
use TLA\GTM_Kit\Admin\OptionsForm;

/** @var OptionsForm $form */

if ( ! defined( 'GTMKIT_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}
?>
<div class="gtmkit-setting-row gtmkit-setting-row-heading gtmkit-clear">
	<h2>
		<?php echo esc_html__( 'Post data', 'gtmkit' ); ?>
	</h2>
	<?php echo __( 'Specify which post data elements you wish to include in the dataLayer for use in Google Tag Manager.', 'gtmkit' ); ?>
</div>

<?php
$form->checkbox_toggle(
	'datalayer_post_type',
	__('Post type', 'gtmkit'),
	__('include the type of the current post or archive page.', 'gtmkit')
);

$form->checkbox_toggle(
	'datalayer_categories',
	__('Categories', 'gtmkit'),
	__('include the categories of the current post or archive page.', 'gtmkit')
);

$form->checkbox_toggle(
	'datalayer_tags',
	__('Tags', 'gtmkit'),
	__('include the tags of the current post or archive page.', 'gtmkit')
);

$form->checkbox_toggle(
	'datalayer_post_title',
	__('Post title', 'gtmkit'),
	'include the post title of the current post.'
);

$form->checkbox_toggle(
	'datalayer_post_id',
	__('Post ID', 'gtmkit'),
	__('include the Post ID of the current post.', 'gtmkit')
);

$form->checkbox_toggle(
	'datalayer_post_date',
	__('Post date', 'gtmkit'),
	__('include the post date.', 'gtmkit')
);

$form->checkbox_toggle(
	'datalayer_post_author_name',
	__('Post author name', 'gtmkit'),
	__('include the post author name.', 'gtmkit')
);

$form->checkbox_toggle(
	'datalayer_post_author_id',
	__('Post author ID', 'gtmkit'),
	__('include the post author ID.', 'gtmkit')
);

?>
