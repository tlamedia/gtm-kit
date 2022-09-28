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
		<?php esc_html_e( 'Overview', 'gtmkit' ); ?>
	</h2>
</div>

<div class="gtmkit-setting-row gtmkit-setting-row-heading gtmkit-clear">
<table class="form-table">
	<tbody>
	<tr>
		<th>Status:</th>
		<td>
			<?php if ( Options::init()->get( 'general', 'gtm_id' ) && Options::init()->get( 'general', 'container_active' ) ): ?>
				<span class="success">
				<span class="dashicons dashicons-yes"></span>
				<?php esc_html_e( 'GTM container is active', 'gtmkit' ); ?>
			</span>
			<?php else: ?>
				<span class="dashicons dashicons-no"></span>
				<?php esc_html_e( 'GTM container is not active', 'gtmkit' ); ?>

			<?php endif; ?>
		</td>
	</tr>
	</tbody>
</table>
</div>

<div class="gtmkit-setting-row gtmkit-setting-row-heading gtmkit-clear">
	<h2><?php esc_html_e( 'Integrations', 'gtmkit' ); ?></h2>
	<?php require_once GTMKIT_PATH . 'src/views/integrations-metabox.php'; ?>
</div>

