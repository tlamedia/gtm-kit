<?php

namespace TLA\GTM_Kit\Admin;

use TLA\GTM_Kit\Options;

class AdminNotice {

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	protected $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Register admin notices
	 *
	 * @param Options $options
	 */
	public static function register( Options $options ): void {
		$page = new static( $options );

		add_action( 'admin_notices', [ $page, 'show_warnings' ] );
	}

	public function show_warnings(): void {

		if (empty($this->options->get('general', 'gtm_id'))) { ?>
			<div class="gtmkit-notice notice notice-error">
				<p>
					<strong>
						<?php printf(
							__( 'To start using GTM Kit, please <a href="%s">enter your GTM ID</a>', 'gtmkit' ),
							menu_page_url( 'gtmkit_general', false ) . '#top#container'
						); ?>
					</strong>
				</p>
			</div>';
		<?php
		}
	}

}
