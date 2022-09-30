<?php

namespace TLA_Media\GTM_Kit;

use TLA_Media\GTM_Kit\Admin\OptionsForm;

/** @var OptionsForm $form */

if ( ! defined( 'GTMKIT_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

$cf7_is_inactive = ! is_plugin_active( 'contact-form-7/wp-contact-form-7.php' );
?>
	<div class="gtmkit-setting-row gtmkit-setting-row-heading gtmkit-clear">
		<h2>
			<?php esc_html_e( 'Contact Form 7 Integration', 'gtmkit' ); ?>
		</h2>
		<p><?php esc_html_e( 'Just another contact form plugin for WordPress. Simple but flexible', 'gtmkit' ) . ': <a href="https://contactform7.com/" target="_blank">Contact Form 7</a>'; ?></p>
		<?php if ( $cf7_is_inactive ): ?>
			<p>
				<span class="error"><?php esc_html_e( 'Contact Form 7 is not installed', 'gtmkit' ); ?></span>.
				<?php
				printf(
					__( 'You can download %s here.', 'gtmkit' ),
					'<a href="https://wordpress.org/plugins/contact-form-7/" target="_blank">Contact Form 7</a>'
				);
				?>
			</p>
		<?php endif; ?>
	</div>
	<?php
$form->setting_row(
	'checkbox-toggle',
	'cf7_integration',
	__( 'Track Contact Form 7', 'gtmkit' ),
	[
		'attributes' => [
			'disabled' => $cf7_is_inactive,
		]
	],
	__( 'Choose this option if you would like to track form submissions.', 'gtmkit' )
);

$field_data                = [
	'attributes' => [
		'disabled' => $cf7_is_inactive,
	]
];
$field_data['options']     = [
	1 => [
		'label' => __( 'On all pages', 'gtmkit' ),
	],
	2 => [
		'label' => __( 'Only on pages where where the Contact Form 7 is loaded.', 'gtmkit' ),
	],
];
$field_data['legend']      = __( 'Where do you want load the integration JavaScript?', 'gtmkit' );
$field_data['legend_attr'] = [ 'class' => 'radiogroup' ];

$form->setting_row(
	'radio',
	'cf7_load_js',
	__( 'Load JavaScript', 'gtmkit' ),
	$field_data
);
