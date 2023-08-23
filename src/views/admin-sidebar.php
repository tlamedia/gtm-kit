<?php
/**
 * Admin sidebar
 *
 * @package GTM Kit
 */

if ( ! defined( 'GTMKIT_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}
?>
<div id="credit">
	Developed by<br/>
	<a href="https://www.tlamedia.dk/">
		<img src="<?php echo esc_url( GTMKIT_URL . 'assets/images/tla-media.svg' ); ?>"	width="170" height="70" alt="TLA Media"/>
	</a>
</div>
