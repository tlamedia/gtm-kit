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
		<?php esc_html_e( 'Google Tag Manager container', 'gtmkit' ); ?>
	</h2>
	<?php esc_html_e( 'Set Container ID and implementation method.', 'gtmkit' ); ?>

</div>

<?php
$input_help = sprintf(
	__( 'Find your GTM container ID on %1$sGoogle Tag Manager%2$s.', 'gtmkit' ),
	'<a target="_blank" href="' . esc_url( 'https://tagmanager.google.com/' ) . '" rel="noopener noreferrer">',
	'</a>'
);
?>

<?php
$form->setting_row(
	'text-input',
	'gtm_id',
	__( 'Container ID:', 'gtmkit' ),
	[],
	$input_help
);
?>

<?php
$form->setting_row(
	'checkbox-toggle',
	'container_active',
	__( 'Container Active', 'gtmkit' ),
	[],
	__( 'Removes the container code for use with custom implementations.', 'gtmkit' )
);
?>
<div class="gtmkit-setting-row gtmkit-setting-row-heading gtmkit-clear">
	<h2><?php esc_html_e( 'Google Tag Manager container code', 'gtmkit' ); ?></h2>
</div>

<?php
$field_data = [];
$field_data['options'] = [
	0 => [
		'label'      => __('Standard implementation as recommended by Google', 'gtmkit'),
	],
	1 => [
		'label'      => __('Load container when browser is idle (requestIdleCallback)', 'gtmkit'),
	],
	2 => [
		'label'      => __('Load container 2 seconds after browser is idle (requestIdleCallback + timer)', 'gtmkit'),
	]
];
$field_data['legend'] = __( 'Container code implementation:', 'gtmkit' );
$field_data['legend_attr'] = [ 'class' => 'radiogroup screen-reader-text' ];

$form->setting_row(
	'radio',
	'script_implementation',
	__( 'Container code implementation:', 'gtmkit' ),
	$field_data,
	__( 'Depending on how you use Google Tag Manager you can delay the loading of the container script until the browser is idle. You can furthermore extend te delay with a timer.', 'gtmkit' )
);
?>

<?php
$label = __( 'Container code <code>&lt;noscript&gt;</code> implementation:', 'gtmkit' );

$field_data = [];
$field_data['options'] = [
	0 => [
		'label'      => __('Just after the opening &lt;body&gt; tag', 'gtmkit'),
	],
	1 => [
		'label'      => __('Footer of the page (not recommended by Google)', 'gtmkit'),
	],
	2 => [
		'label'      => __('Custom (insert function in your template)', 'gtmkit'),
	],
	3 => [
		'label'      => __('Disable &lt;noscript&gt; implementation', 'gtmkit'),
	]

];

$legend      = __( 'Container code implementation', 'gtmkit' );
$legend_attr = [ 'class' => 'radiogroup screen-reader-text' ];
$description = __( 'The preferred method to implement the &lt;noscript&gt; container code is just after the opening &lt;body&gt; tag.', 'gtmkit' ). ' ';
$description .= __( 'This requires that your theme uses the "body_open" hook.', 'gtmkit' ). ' ';
$description .= __( 'If your theme does not support this the script can be injected in the footer or you can use the function below.', 'gtmkit' );
$description .= '<br><br>';
$description .= '<code>&lt;?php if ( function_exists( \'gtmkit_the_noscript_tag\' ) ) { gtmkit_the_noscript_tag(); } ?&gt;</code>';

$form->setting_row(
	'radio',
	'noscript_implementation',
	__( 'Container code <code>&lt;noscript&gt;</code> implementation:', 'gtmkit' ),
	$field_data,
	$description
);
?>

<?php
$form->setting_row(
	'text-input',
	'datalayer_name',
	__( 'dataLayer variable name:', 'gtmkit' ),
	[],
	__( 'The default name of the data layer object is dataLayer. If you prefer to use a different name for your data layer, you may do.', 'gtmkit')
);
?>

<!-- Server Side Section Title -->
<div class="gtmkit-setting-row gtmkit-setting-row-heading gtmkit-clear">
	<h2><?php esc_html_e( 'GTM Server Side', 'gtmkit' ); ?></h2>
</div>

<?php
$form->setting_row(
	'text-input',
	'sgtm_domain',
	__( 'GTM Server Side Domain:', 'gtmkit' ),
	[],
	__( 'Enter your custom domain name if you are using a custom server side GTM container for tracking.', 'gtmkit')
);
?>

<?php
$form->setting_row(
	'text-input',
	'sgtm_container_identifier',
	__( 'sGTM container identifier:', 'gtmkit' ),
	[],
	__( 'Only use if you are using a custom loader', 'gtmkit')
);
?>
