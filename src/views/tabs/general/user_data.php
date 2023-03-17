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
		<?php esc_html_e( 'User data', 'gtm-kit' ); ?>
	</h2>
	<p><?php esc_html_e( 'Specify which user data you wish to include in the dataLayer for use in Google Tag Manager.', 'gtm-kit' ); ?></p>
</div>

<div class="gtmkit_section_message warning">
	<div class="stuffbox">
		<h3 class="hndle"><?php esc_html_e( 'Including user data is not compatible with full page caching', 'gtm-kit' ); ?></h3>
		<div class="inside">
			<ul>
				<li><?php esc_html_e( 'Full page caching will cache user data making it the same for all users. There are ways around this, but it depends on the chosen cache solution and is only for advanced users.', 'gtm-kit' ); ?></li>
			</ul>
		</div>
	</div>
</div>

<?php
$form->setting_row(
	'checkbox-toggle',
	'datalayer_logged_in',
	__( 'Logged in', 'gtm-kit' ),
	[],
	__( 'include whether the user is logged in.', 'gtm-kit' )
);

$form->setting_row(
	'checkbox-toggle',
	'datalayer_user_id',
	__( 'User ID', 'gtm-kit' ),
	[],
	__( 'include the user ID if the user is logged in.', 'gtm-kit' )
);

$form->setting_row(
	'checkbox-toggle',
	'datalayer_user_role',
	__( 'User role', 'gtm-kit' ),
	[],
	__( 'include the user role if the user is logged in.', 'gtm-kit' )
);
