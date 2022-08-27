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
		<?php esc_html_e( 'Overview', 'gtmkit' ); ?>
	</h2>
</div>

<table class="form-table">
<tbody>
	<tr>
		<th>Status:</th>
		<td>
			<?php if (Options::init()->get( 'general', 'gtm_id' ) && Options::init()->get( 'general', 'container_active' )): ?>
			<span class="success">
				<span class="dashicons dashicons-yes"></span>
				<?php esc_html_e('GTM container is active', 'gtmkit'); ?>
			</span>
			<?php else: ?>
			<span class="dashicons dashicons-no"></span>
				<?php esc_html_e('GTM container is not active', 'gtmkit'); ?>

			<?php endif; ?>
		</td>
	</tr>
</tbody>
</table>

<div class="gtmkit_section_message space-top">
<div class="stuffbox">
	<h3 class="hndle"><?php esc_html_e('About GTM Kit', 'gtmkit'); ?> <span class="version">(Version <?php echo GTMKIT_VERSION; ?>)</span></h3>
	<div class="inside">
		<ul>
			<li><?php esc_html_e('The goal of GTM Kit is to provide a flexible tool for generating the data layer for Google Tag Manager.', 'gtmkit'); ?></li>
		</ul>
	</div>
</div>
</div>
