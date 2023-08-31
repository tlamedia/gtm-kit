<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit;

if ( ! defined( 'GTMKIT_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

$gtmkit_integrations_page = $_GET['page'] === 'gtmkit_integrations'; // phpcs:ignore

$gtmkit_integrations = [
	[
		'name'               => 'WooCommerce',
		'description'        => __( 'The #1 open source eCommerce platform built for WordPress', 'gtm-kit' ),
		'plugin_active'      => is_plugin_active( 'woocommerce/woocommerce.php' ),
		'integration_active' => Options::init()->get( 'integrations', 'woocommerce_integration' ),
		'tab_id'             => 'woocommerce',
		'plugin_search'      => 'woocommerce',
	],
	[
		'name'               => 'Contact Form 7',
		'description'        => __( 'Just another contact form plugin for WordPress. Simple but flexible', 'gtm-kit' ),
		'plugin_active'      => is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ),
		'integration_active' => Options::init()->get( 'integrations', 'cf7_integration' ),
		'tab_id'             => 'cf7',
		'plugin_search'      => 'Contact Form 7',
	],
	[
		'name'               => 'Easy Digital Downloads',
		'description'        => __( 'Easy way to sell Digital Products With WordPress', 'gtm-kit' ),
		'plugin_active'      => ( is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) || is_plugin_active( 'easy-digital-downloads-pro/easy-digital-downloads.php' ) ),
		'integration_active' => Options::init()->get( 'integrations', 'edd_integration' ),
		'tab_id'             => 'edd',
		'plugin_search'      => 'Easy Digital Downloads',
	],
];
?>
<div class="gtmkit-items-metabox gtmkit-metabox">
	<div class="gtmkit-items-list">
		<ul>
			<?php foreach ( $gtmkit_integrations as $gtmkit_integration ) : ?>
				<?php $gtmkit_integration_active = ( $gtmkit_integration['plugin_active'] && $gtmkit_integration['integration_active'] ); ?>
			<li class="gtmkit-list-item
				<?php
				if ( $gtmkit_integration_active ) :
					?>
				gtmkit-list-item-has-pill<?php endif; ?>">
				<h3><?php echo esc_html( $integration['name'] ); ?></h3>
				<?php if ( $integration_active ) : ?>
					<span class="gtmkit-list-item-pill gtmkit-list-item-pill-green">
						<?php esc_html_e( 'Active', 'gtm-kit' ); ?>
					</span>
				<?php endif; ?>
				<div class="gtmkit-list-item-actions">
					<div class="gtmkit-list-item-description">
						<p><?php echo esc_html( $integration['description'] ); ?></p>
					</div>
					<div class="gtmkit-list-item-buttons">
						<a
							<?php if ( ! $integration['plugin_active'] && ! $integration_active ) : ?>
								href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=search&type=term&s=' . rawurlencode( $integration['plugin_search'] ) ) ); ?>"
							<?php else : ?>
								href="<?php echo esc_url( admin_url( 'admin.php?page=gtmkit_integrations#top#' . $integration['tab_id'] ) ); ?>"
							<?php endif; ?>

							<?php
							if ( $gtmkit_integrations_page ) {
								echo 'id="gtmkit-open-tab-' . esc_attr( $integration['tab_id'] ) . '"';}
							?>
							class="gtmkit-button
							<?php
							if ( $gtmkit_integrations_page && $integration['plugin_active'] ) :
								?>
								gtmkit-open-tab<?php endif; ?>"
						>
							<?php if ( $integration_active ) : ?>
								<?php esc_html_e( 'Edit integration', 'gtm-kit' ); ?>
							<?php elseif ( $integration['plugin_active'] ) : ?>
								<?php esc_html_e( 'Setup integration', 'gtm-kit' ); ?>
							<?php else : ?>
								<?php esc_html_e( 'Install plugin', 'gtm-kit' ); ?>
							<?php endif; ?>
						</a>
					</div>
				</div>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>
