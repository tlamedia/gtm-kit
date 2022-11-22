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
		<?php esc_html_e( 'Help', 'gtmkit' ); ?>
	</h2>
</div>

<div class="gtmkit_section_message">
	<div class="stuffbox">
		<h3 class="hndle"><?php esc_html_e( 'Tutorials', 'gtmkit' ); ?></h3>
		<div class="inside">
			<ul>
				<li><a href="https://gtmkit.com/documentation/getting-started-with-gtm-kit/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=getting-started&utm_content=help-tutorials" target="_blank">Getting started with GTM Kit</a></li>
				<li><a href="https://gtmkit.com/documentation/woocommerce/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=woocommerce&utm_content=help-tutorials" target="_blank">WooCommerce Integration</a></li>
				<li><a href="https://gtmkit.com/documentation/contact-form-7-integration/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=contactform7&utm_content=help-tutorials" target="_blank">Contact Form 7 Integration</a></li>
				<li><a href="https://gtmkit.com/documentation/advanced-gtm-container-implementation/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=advanced-container-implementation&utm_content=help-tutorials" target="_blank">Advanced GTM container implementation</a></li>
				<li><a href="https://gtmkit.com/documentation/settings-actions-and-filters-for-developers/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=settings-actions-and-filters-for-developers&utm_content=help-tutorials" target="_blank">Settings, actions and filters for developers</a></li>
			</ul>
		</div>
	</div>
</div>

<div class="gtmkit_section_message">
	<div class="stuffbox">
		<h3 class="hndle"><?php esc_html_e( 'Support', 'gtmkit' ); ?></h3>
		<div class="inside">
			<ul>
				<li><a href="https://wordpress.org/support/plugin/gtm-kit/" target="_blank">Support forum</a></li>
				<li><a href="https://gtmkit.com/" target="_blank">Plugin Homepage</a> (gtmkit.com)</li>
				<li><a href="https://wordpress.org/plugins/gtm-kit/" target="_blank">WordPress.org Plugin Page</a></li>
			</ul>
		</div>
	</div>
</div>

<div class="gtmkit_section_message space-top">
	<div class="stuffbox">
		<h3 class="hndle">
			<?php esc_html_e( 'About GTM Kit', 'gtmkit' ); ?>
			<span class="version">(Version <?php echo esc_html( GTMKIT_VERSION ); ?>)</span>
		</h3>
		<div class="inside">
			<ul>
				<li><?php esc_html_e( 'The goal of GTM Kit is to provide a flexible tool for generating the data layer for Google Tag Manager.', 'gtmkit' ); ?></li>
				<li>
					<?php esc_html_e( 'The plugin is open source (GPL v3 license) and contributions are welcome:', 'gtmkit' ); ?>
					<a href="https://github.com/tlamedia/gtm-kit" target="_blank"><?php esc_attr_e( 'GTM Kit Repository', 'gtmkit' ); ?></a>
				</li>
			</ul>
		</div>
	</div>
</div>
