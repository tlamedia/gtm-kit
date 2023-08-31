<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit;

use TLA_Media\GTM_Kit\Admin\OptionsForm;

/** @var OptionsForm $form */ // phpcs:ignore

if ( ! defined( 'GTMKIT_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}
?>
<div class="gtmkit-setting-row gtmkit-setting-row-heading gtmkit-clear">
	<h2>
		<?php esc_html_e( 'Google Tag Manager container', 'gtm-kit' ); ?>
	</h2>
	<?php esc_html_e( 'Set Container ID and implementation method.', 'gtm-kit' ); ?>

</div>

<?php
$gtmkit_input_help = sprintf(
	/* translators: %1$s: opening <a> tag%1$s: opening <a> tag %2$s: closin </a> tag */
	__( 'Find your GTM container ID on %1$sGoogle Tag Manager%2$s.', 'gtm-kit' ),
	'<a target="_blank" href="' . esc_url( 'https://tagmanager.google.com/' ) . '" rel="noopener noreferrer">',
	'</a>'
);
?>

<?php
$form->setting_row(
	'text-input',
	'gtm_id',
	__( 'Container ID:', 'gtm-kit' ),
	[],
	$gtmkit_input_help
);
?>

<?php
$form->setting_row(
	'checkbox-toggle',
	'just_the_container',
	__( 'Just the container', 'gtm-kit' ),
	[],
	__( 'Setting this to On will reduce the functionality to just the GTM container code. No additional data will be pushed to the datalayer regardless of any other settings.', 'gtm-kit' )
);
?>

<div class="gtmkit-setting-row gtmkit-setting-row-heading gtmkit-clear">
	<h2><?php esc_html_e( 'Google Tag Manager container code', 'gtm-kit' ); ?></h2>
</div>

<?php
$form->setting_row(
	'checkbox-toggle',
	'container_active',
	__( 'Container Code', 'gtm-kit' ),
	[],
	__( 'Setting this to Off will remove the Google Tag Manager container code but the data layer will remain.', 'gtm-kit' )
);
?>

<?php
$gtmkit_field_data                = [];
$gtmkit_field_data['options']     = [
	0 => [
		'label' => __( 'Standard implementation as recommended by Google (no delay)', 'gtm-kit' ),
	],
	1 => [
		'label' => __( 'Load container when browser is idle (requestIdleCallback)', 'gtm-kit' ),
	],
];
$gtmkit_field_data['legend']      = __( 'Container code implementation:', 'gtm-kit' );
$gtmkit_field_data['legend_attr'] = [ 'class' => 'radiogroup screen-reader-text' ];

$form->setting_row(
	'radio',
	'script_implementation',
	__( 'Container code implementation:', 'gtm-kit' ),
	$gtmkit_field_data,
	__( 'Depending on how you use Google Tag Manager you can delay the loading of the container script until the browser is idle.', 'gtm-kit' )
);
?>

<?php
$gtmkit_label = __( 'Container code <code>&lt;noscript&gt;</code> implementation:', 'gtm-kit' );

$gtmkit_field_data            = [];
$gtmkit_field_data['options'] = [
	0 => [
		'label' => __( 'Just after the opening &lt;body&gt; tag', 'gtm-kit' ),
	],
	1 => [
		'label' => __( 'Footer of the page (not recommended by Google)', 'gtm-kit' ),
	],
	2 => [
		'label' => __( 'Custom (insert function in your template)', 'gtm-kit' ),
	],
	3 => [
		'label' => __( 'Disable &lt;noscript&gt; implementation', 'gtm-kit' ),
	],

];

$gtmkit_description  = __( 'The preferred method to implement the &lt;noscript&gt; container code is just after the opening &lt;body&gt; tag.', 'gtm-kit' ) . ' ';
$gtmkit_description .= __( 'This requires that your theme uses the "body_open" hook.', 'gtm-kit' ) . ' ';
$gtmkit_description .= __( 'If your theme does not support this the script can be injected in the footer or you can use the function below.', 'gtm-kit' );
$gtmkit_description .= '<br><br>';
$gtmkit_description .= '<code>&lt;?php if ( function_exists( \'gtmkit_the_noscript_tag\' ) ) { gtmkit_the_noscript_tag(); } ?&gt;</code>';

$form->setting_row(
	'radio',
	'noscript_implementation',
	$gtmkit_label,
	$gtmkit_field_data,
	$gtmkit_description
);
?>

<?php
$form->setting_row(
	'text-input',
	'datalayer_name',
	__( 'dataLayer variable name:', 'gtm-kit' ),
	[],
	__( 'The default name of the data layer object is dataLayer. If you prefer to use a different name for your data layer, you may do.', 'gtm-kit' )
);
?>

<!-- Server Side Section Title -->
<div class="gtmkit-setting-row gtmkit-setting-row-heading gtmkit-clear">
	<h2><?php esc_html_e( 'GTM Server Side', 'gtm-kit' ); ?></h2>
</div>

<?php
$form->setting_row(
	'text-input',
	'sgtm_domain',
	__( 'GTM Server Side Domain:', 'gtm-kit' ),
	[],
	__( 'Enter your custom domain name if you are using a custom server side GTM container for tracking.', 'gtm-kit' )
);
?>

<?php
$form->setting_row(
	'text-input',
	'sgtm_container_identifier',
	__( 'sGTM container identifier:', 'gtm-kit' ),
	[],
	__( 'Only use if you are using a custom loader', 'gtm-kit' )
);
?>
