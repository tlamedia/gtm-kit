<?php
/**
 * Admin sidebar
 */

if ( ! defined( 'GTMKIT_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}
?>
<div class="stuffbox">
	<h3 class="hndle"><?php _e( 'Support', 'gtmkit' ); ?></h3>
	<div class="inside">
		<ul>
			<li><?php _e( 'If you are having problems with this plugin, please talk about them in the', 'gtmkit' ); ?>
				<a href="https://wordpress.org/support/plugin/gtm-kit/"><?php _e( 'Support forum', 'gtmkit' ); ?></a>.
			</li>
			<li><?php _e( 'You can also find documentation and examples of implementations on the', 'gtmkit' ); ?> <a
					href="https://gtmkit.com/"><?php _e( 'plugin homepage', 'gtmkit' ); ?></a>.
			</li>
		</ul>
	</div>
</div>
<div class="stuffbox">
	<h3 class="hndle"><?php _e( 'About this Plugin', 'gtmkit' ); ?></h3>
	<div class="inside">
		<ul>
			<li><a href="https://gtmkit.com/"><?php _e( 'Plugin Homepage', 'gtmkit' ); ?></a></li>
			<li><a href="https://wordpress.org/extend/plugins/gtm-kit/"><?php _e( 'Plugin at Wordpress.org', 'gtmkit' ); ?></a></li>
		</ul>
	</div>
</div>
<div id="credit">
	Developed by<br/>
	<a href="https://www.tlamedia.dk/"><img src="<?php echo esc_url( GTMKIT_URL . 'assets/images/tla-media.svg' ); ?>" width="170" height="70" alt="TLA Media"/></a>
</div>
