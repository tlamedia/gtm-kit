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
	<h2 class="gtmkit-text-2xl gtmkit-font-bold gtmkit-text-color-heading gtmkit-mb-8">
		<?php esc_html_e( 'Google Consent Mode', 'gtm-kit' ); ?>
	</h2>

	<div class="gtmkit-text-sm">
		<div class="gtmkit-my-6 gtmkit-border gtmkit-bg-white gtmkit-w-3/4 gtmkit-border-color-grey">
			<h3 class="gtmkit-font-bold gtmkit-text-lg gtmkit-px-3 gtmkit-py-2 gtmkit-border-b gtmkit-border-color-grey">
				<?php esc_html_e( 'Warning!', 'gtm-kit' ); ?>
			</h3>
			<div class="gtmkit-p-3 gtmkit-space-y-1">
				<p><?php esc_html_e( 'Most Consent Management Platforms will handle the Google Consent Mode settings for you and applying the default settings in both GTM Kit and you CMP will lead to unexpected behaviour or errors.', 'gtm-kit' ); ?></p>
				<p><?php esc_html_e( 'You should only use these settings if you do not have a Consent Management Platform that supports Google Consent Mode.', 'gtm-kit' ); ?></p>
				<p class="!gtmkit-mt-4"><?php esc_html_e( 'GTM Kit will only set the default Consent Mode settings and you must update the settings yourself when the user has given consent.', 'gtm-kit' ); ?></p>
				<p class="gtmkit-text-color-primary !gtmkit-mt-4">
					<a href="https://developers.google.com/tag-platform/security/guides/consent#implementation_example">
						<?php esc_html_e( 'See an example of how consent is updated', 'gtm-kit' ); ?>
					</a>
				</p>
			</div>
		</div>
	</div>

	<h3 class="gtmkit-font-bold gtmkit-text-lg gtmkit-mt-8 gtmkit-py-2">
		<?php esc_html_e( 'Google Consent Mode Default Settings', 'gtm-kit' ); ?>
	</h3>

	<?php
	$form->setting_row(
		'checkbox-toggle',
		'gcm_default_settings',
		__( 'Activate GCM settings', 'gtm-kit' ),
		[],
		__( 'Choose this option if you would like to activate the default settings below.', 'gtm-kit' )
	);

	$form->setting_row(
		'checkbox-toggle',
		'gcm_ad_storage',
		__( 'Ad Storage', 'gtm-kit' ),
		[],
		__( 'Enables storage, such as cookies, related to advertising', 'gtm-kit' )
	);

	$form->setting_row(
		'checkbox-toggle',
		'gcm_analytics_storage',
		__( 'Analytics Storage', 'gtm-kit' ),
		[],
		__( 'Enables storage, such as cookies, related to analytics (for example, visit duration)', 'gtm-kit' )
	);

	$form->setting_row(
		'checkbox-toggle',
		'gcm_functionality_storage',
		__( 'Functionality Storage', 'gtm-kit' ),
		[],
		__( 'Enables storage that supports the functionality of the website or app such as language settings', 'gtm-kit' )
	);

	$form->setting_row(
		'checkbox-toggle',
		'gcm_personalization_storage',
		__( 'Personalization Storage', 'gtm-kit' ),
		[],
		__( 'Enables storage related to personalization such as video recommendations', 'gtm-kit' )
	);

	$form->setting_row(
		'checkbox-toggle',
		'gcm_security_storage',
		__( 'Security Storage', 'gtm-kit' ),
		[],
		__( 'Enables storage related to security such as authentication functionality, fraud prevention, and other user protection', 'gtm-kit' )
	);

