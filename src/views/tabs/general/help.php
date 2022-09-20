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
		<h3 class="hndle"><?php esc_html_e( 'Override settings in wp-config.php', 'gtmkit' ); ?></h3>
		<div class="inside">
			<ul>
				<li><code>define( 'GTMKIT_ON', true );</code>
					// <?php esc_html_e( 'True activates constants support and false turns it off', 'gtmkit' ); ?></li>
				<li><code>define( 'GTMKIT_CONTAINER_ID', 'GTM-XXXXXXX' );</code>
					// <?php esc_html_e( 'The GTM container ID', 'gtmkit' ); ?></li>
				<li><code>define( 'GTMKIT_CONTAINER_ACTIVE', false );</code>
					// <?php esc_html_e( 'Or true, in which case the constant is ignored', 'gtmkit' ); ?></li>
			</ul>
		</div>
	</div>
</div>
