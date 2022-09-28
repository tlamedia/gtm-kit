<?php

namespace TLA_Media\GTM_Kit;

if ( ! defined( 'GTMKIT_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

$gtmkit_integrations = $_GET['page'] == 'gtmkit_integrations';

$integrations = [
	[
		'name' => 'WooCommerce',
		'description' => __( 'The #1 open source eCommerce platform built for WordPress', 'gtmkit' ),
		'plugin_active' => is_plugin_active( 'woocommerce/woocommerce.php' ),
		'integration_active' => Options::init()->get( 'integrations', 'woocommerce_integration' ),
		'tab_id' => 'woocommerce',
		'plugin_search' => 'woocommerce',
	],
];

$woocommerce_active = is_plugin_active( 'woocommerce/woocommerce.php' );
$woocommerce_integration = ( $woocommerce_active && Options::init()->get( 'integrations', 'woocommerce_integration' ) );
$cf7_active = is_plugin_active( 'contact-form-7/wp-contact-form-7.php' );
$cf7_integration = ( $cf7_active && Options::init()->get( 'integrations', 'cf7_integration' ) );

?>
<div class="gtmkit-items-metabox gtmkit-metabox">
	<div class="gtmkit-items-list">
		<ul>
			<?php foreach ($integrations as $integration): ?>
			<?php $integration_active = ( $integration['plugin_active'] && $integration['integration_active'] ); ?>
			<li class="gtmkit-list-item <?php if ( $integration_active ): ?>gtmkit-list-item-has-pill<?php endif; ?>">
				<h3><?php echo esc_html($integration['name']) ?></h3>
				<?php if ( $integration_active ): ?>
					<span class="gtmkit-list-item-pill gtmkit-list-item-pill-green">
						<?php esc_html_e( 'Active', 'gtmkit' ); ?>
					</span>
				<?php endif; ?>
				<div class="gtmkit-list-item-actions">
					<div class="gtmkit-list-item-description">
						<p><?php esc_html_e( 'The #1 open source eCommerce platform built for WordPress', 'gtmkit' ); ?></p>
					</div>
					<div class="gtmkit-list-item-buttons">
						<a
							<?php if ( ! $integration['plugin_active'] && ! $integration_active ): ?>
								href="<?php echo admin_url( 'plugin-install.php?tab=search&type=term&s=' . urlencode( $integration['plugin_search'] ) ); ?>"
							<?php else: ?>
								href="<?php echo admin_url( 'admin.php?page=gtmkit_integrations#top#' . $integration['tab_id'] ); ?>"
							<?php endif; ?>

							<?php if ( $gtmkit_integrations ) echo 'id="gtmkit-open-tab-' . esc_attr($integration['tab_id']) . '"'; ?>
							class="gtmkit-button <?php if ( $gtmkit_integrations && $integration['plugin_active'] ): ?>gtmkit-open-tab<?php endif; ?>"
						>
							<?php if ( $integration_active ): ?>
								<?php esc_html_e( 'Edit integration', 'gtmkit' ); ?>
							<?php elseif ( $integration['plugin_active'] ): ?>
								<?php esc_html_e( 'Setup integration', 'gtmkit' ); ?>
							<?php else: ?>
								<?php esc_html_e( 'Install plugin', 'gtmkit' ); ?>
							<?php endif; ?>
						</a>
					</div>
				</div>
			</li>
			<?php endforeach; ?>
			<li class="gtmkit-list-item disabled">
				<h3><?php esc_html_e( 'Coming soon...', 'gtmkit' ); ?></h3>
				<p><?php esc_html_e( 'The next integration is under way.', 'gtmkit' ); ?></p>
			</li>

		</ul>
	</div>
</div>
