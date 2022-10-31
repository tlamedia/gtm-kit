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
	<h2><?php esc_attr_e( 'Status', 'gtmkit' ); ?></h2>
	<div class="gtmkit-items-list single-item">
		<ul>
			<li class="gtmkit-list-item gtmkit-list-item-has-pill">
				<h3>Google Tag Manager Container</h3>
				<?php if ( Options::init()->get( 'general', 'gtm_id' ) && Options::init()->get( 'general', 'container_active' ) ): ?>
				<span class="gtmkit-list-item-pill gtmkit-list-item-pill-green">
					<?php esc_attr_e( 'Active', 'gtmkit' ); ?>
				</span>
				<div class="gtmkit-list-item-actions">
					<div class="gtmkit-list-item-description">
						<p>
							<?php esc_html_e( 'Container ID:', 'gtmkit'); ?>
							<?php echo esc_html( Options::init()->get( 'general', 'gtm_id' ) ); ?>
						</p>
					</div>
					<div class="gtmkit-list-item-buttons">
						<a href="<?php echo admin_url( 'admin.php?page=gtmkit_general#top#container' ); ?>" id="gtmkit-open-tab-container" class="gtmkit-button gtmkit-open-tab">
							<?php esc_html_e( 'Edit container', 'gtmkit' ); ?>
						</a>
					</div>
				</div>
				<?php else: ?>
				<span class="gtmkit-list-item-pill gtmkit-list-item-pill-red">
					<?php esc_attr_e( 'Inactive', 'gtmkit' ); ?>
				</span>
				<div class="gtmkit-list-item-actions">
					<div class="gtmkit-list-item-description">
						<p>
							<?php esc_html_e( 'The container is not active but the datalayer is generated.', 'gtmkit'); ?>
						</p>
					</div>
					<div class="gtmkit-list-item-buttons">
						<a href="<?php echo admin_url( 'admin.php?page=gtmkit_general#top#container' ); ?>" id="gtmkit-open-tab-container" class="gtmkit-button gtmkit-open-tab">
							<?php esc_html_e( 'Edit container', 'gtmkit' ); ?>
						</a>
					</div>
				</div>
				<?php endif; ?>
			</li>
		</ul>
	</div>
</div>

<div class="gtmkit-setting-row gtmkit-setting-row-heading gtmkit-clear">
	<h2><?php esc_html_e( 'Integrations', 'gtmkit' ); ?></h2>
	<?php require_once GTMKIT_PATH . 'src/views/integrations-metabox.php'; ?>
</div>

