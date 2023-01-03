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
		<?php esc_html_e( 'Integrations', 'gtm-kit' ); ?>
	</h2>
</div>

<div class="gtmkit-setting-row gtmkit-clear">
	<?php require_once GTMKIT_PATH . 'src/views/integrations-metabox.php'; ?>
</div>
