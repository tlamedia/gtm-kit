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
		<?php esc_html_e( 'Help', 'gtm-kit' ); ?>
	</h2>
</div>

<div class="gtmkit_section_message">
	<div class="stuffbox">
		<h3 class="hndle"><?php esc_html_e( 'Tutorials', 'gtm-kit' ); ?></h3>
		<div class="inside">
			<ul>
				<li><a href="https://gtmkit.com/documentation/getting-started-with-gtm-kit/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=getting-started&utm_content=help-tutorials" target="_blank">Getting started with GTM Kit</a></li>
				<li><a href="https://gtmkit.com/documentation/woocommerce/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=woocommerce&utm_content=help-tutorials" target="_blank">WooCommerce Integration</a></li>
				<li><a href="https://gtmkit.com/documentation/contact-form-7-integration/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=contactform7&utm_content=help-tutorials" target="_blank">Contact Form 7 Integration</a></li>
				<li><a href="https://gtmkit.com/documentation/set-up-easy-digital-downloads-for-google-tag-manager/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=set-up-easy-digital-downloads-for-google-tag-manager&utm_content=help-tutorials" target="_blank">Easy Digital Downloads integration</a></li>
				<li><a href="https://gtmkit.com/documentation/advanced-gtm-container-implementation/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=advanced-container-implementation&utm_content=help-tutorials" target="_blank">Advanced GTM container implementation</a></li>
				<li><a href="https://gtmkit.com/documentation/settings-actions-and-filters-for-developers/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=settings-actions-and-filters-for-developers&utm_content=help-tutorials" target="_blank">Settings, actions and filters for developers</a></li>
			</ul>
		</div>
	</div>
</div>

<div class="gtmkit_section_message">
	<div class="stuffbox">
		<h3 class="hndle"><?php esc_html_e( 'Support', 'gtm-kit' ); ?></h3>
		<div class="inside">
			<ul>
				<li><a href="https://wordpress.org/support/plugin/gtm-kit/" target="_blank"><?php esc_html_e( 'Support forum', 'gtm-kit' ); ?></a></li>
				<li><a href="https://gtmkit.com/" target="_blank"><?php esc_html_e( 'Plugin Homepage', 'gtm-kit' ); ?></a> (gtmkit.com)</li>
				<li><a href="https://wordpress.org/plugins/gtm-kit/" target="_blank"><?php esc_html_e( 'WordPress.org Plugin Page', 'gtm-kit' ); ?></a></li>
			</ul>
		</div>
	</div>
</div>

<div class="gtmkit_section_message">
	<div class="stuffbox">
		<h3 class="hndle">
			<?php esc_html_e( 'About GTM Kit', 'gtm-kit' ); ?>
			<span class="version">(<?php esc_html_e( 'Version:', 'gtm-kit' ); ?> <?php echo esc_html( GTMKIT_VERSION ); ?>)</span>
		</h3>
		<div class="inside">
			<ul>
				<li>
					<?php esc_html_e( 'The plugin is open source (GPL v3 license) and contributions are welcome:', 'gtm-kit' ); ?>
					<a href="https://github.com/tlamedia/gtm-kit" target="_blank"><?php esc_attr_e( 'GTM Kit Repository', 'gtm-kit' ); ?></a>
				</li>
			</ul>
		</div>
	</div>
</div>
