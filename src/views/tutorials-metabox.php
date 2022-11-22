<?php

namespace TLA_Media\GTM_Kit;

if ( ! defined( 'GTMKIT_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

$tutorials = [
	[
		'name' => __( 'Getting started', 'gtmkit' ),
		'description' => __( 'How to get the most out of Google Tag Manager with GTM Kit', 'gtmkit' ),
		'url' => 'https://gtmkit.com/documentation/getting-started-with-gtm-kit/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=getting-started&utm_content=dashboard-tutorials',
	],
	[
		'name' => __( 'WooCommerce integration', 'gtmkit' ),
		'description' => __( 'Integrate WooCommerce with Google Tag Manager and Google Analytics', 'gtmkit' ),
		'url' => 'https://gtmkit.com/documentation/woocommerce/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=woocommerce&utm_content=dashboard-tutorials',
	],
	[
		'name' => __( 'See all tutorials...', 'gtmkit' ),
		'description' => __( 'See all our tutorial and get the most out of GTM Kit', 'gtmkit' ),
		'url' => 'https://gtmkit.com/documentation/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=documentation&utm_content=dashboard-tutorials',
	],
];
?>
<div class="gtmkit-items-metabox gtmkit-metabox">
	<div class="gtmkit-items-list">
		<ul>
			<?php foreach ($tutorials as $tutorial): ?>
			<li class="gtmkit-list-item">
				<h3><?php echo esc_html($tutorial['name']) ?></h3>
				<div class="gtmkit-list-item-actions">
					<div class="gtmkit-list-item-description">
						<p><?php echo esc_html( $tutorial['description'] ); ?></p>
					</div>
					<div class="gtmkit-list-item-buttons">
						<a class="gtmkit-button" target="_blank" href="<?php echo esc_url( $tutorial['url'] ); ?>">
							<?php esc_html_e( 'Read article', 'gtmkit' ); ?>
						</a>
					</div>
				</div>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>
