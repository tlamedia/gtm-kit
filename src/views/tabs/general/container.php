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
		<?php echo esc_html__( 'Google Tag Manager container', 'gtmkit' ); ?>
	</h2>
	<?php echo __( 'Set Container ID and implementation method.', 'gtmkit' ); ?>

</div>

<?php
$input_help = sprintf(
	esc_html__( 'Find your GTM container ID on %1$sGoogle Tag Manager%2$s.', 'gtmkit' ),
	'<a target="_blank" href="' . esc_url( 'https://tagmanager.google.com/' ) . '" rel="noopener noreferrer">',
	'</a>'
);
?>
<?php $form->text_input( 'gtm_id', __( 'Container ID:', 'gtmkit' ), [], $input_help ); ?>

<?php
$form->checkbox_toggle(
'container_active',
__('Container Active', 'gtmkit'),
__('Removes the container code for use with custom implementations.', 'gtmkit')
);
?>
<div class="gtmkit-setting-row gtmkit-setting-row-heading gtmkit-clear">
	<h2><?php echo esc_html__( 'Google Tag Manager container code', 'gtmkit' ); ?></h2>
</div>

<?php
$label = __( 'Container code implementation:', 'gtmkit' );
$codeImplementationOptions = [
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

$legend      = __( 'Container code implementation:', 'gtmkit' );
$legend_attr = [ 'class' => 'radiogroup screen-reader-text' ];
$description = __( 'Depending on how you use Google Tag Manager you can delay the loading of the container script until the browser is idle. You can furthermore extend te delay with a timer.', 'gtmkit' );

$form->radio( 'script_implementation', $label, $codeImplementationOptions, $legend, $legend_attr, $description );
?>

<?php
$label = __( 'Container code <code>&lt;noscript&gt;</code> implementation:', 'gtmkit' );

$codeImplementationOptions = [
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

$form->radio( 'noscript_implementation', $label, $codeImplementationOptions, $legend, $legend_attr, $description );
?>

<?php
$input_help = __( 'The default name of the data layer object is dataLayer. If you prefer to use a different name for your data layer, you may do.', 'gtmkit');
$form->text_input( 'datalayer_name', __( 'dataLayer variable name:', 'gtmkit' ), [], $input_help ); ?>

<!-- Server Side Section Title -->
<div class="gtmkit-setting-row gtmkit-setting-row-heading gtmkit-clear">
	<h2><?php esc_html_e( 'GTM Server Side', 'gtmkit' ); ?></h2>
</div>

<?php $form->text_input( 'sgtm_domain', __( 'GTM Server Side Domain:', 'gtmkit' ), [], __('Enter your custom domain name if you are using a custom server side GTM container for tracking', 'gtmkit') ); ?>

<?php $form->text_input( 'sgtm_container_identifier', __( 'sGTM container identifier:', 'gtmkit' ), [], __('Only use if you are using a custom loader', 'gtmkit') ); ?>
