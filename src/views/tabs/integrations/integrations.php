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
		<?php echo esc_html__( 'Integrations', 'gtmkit' ); ?>
	</h2>
</div>

<p><?php esc_html_e( 'Integrations summary.', 'gtmkit' ); ?></p>

<table class="form-table">
<tbody>
	<tr>
		<th>Active Integrations:</th>
		<td>
			<?php if (Options::init()->get( 'integrations', 'woocommerce_integration' )): ?>
				<span class="success">
				<span class="dashicons dashicons-yes"></span>
				WooCommerce
			</span>
			<?php endif; ?>
		</td>
	</tr>
</tbody>
</table>
