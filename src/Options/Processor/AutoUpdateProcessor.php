<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Options\Processor;

use TLA_Media\GTM_Kit\Installation\AutomaticUpdates;
use TLA_Media\GTM_Kit\Admin\NotificationsHandler;

/**
 * Auto Update Processor
 *
 * Handles WordPress auto-update side effects.
 */
final class AutoUpdateProcessor implements OptionProcessorInterface {

	/**
	 * Process auto update value
	 *
	 * @param mixed $value New value.
	 * @param mixed $old_value Previous value.
	 * @return bool Processed value.
	 */
	public function process( $value, $old_value ): bool {
		// Coerce to boolean.
		return (bool) $value;
	}

	/**
	 * Handle side effects after save
	 *
	 * @param mixed $value New value.
	 * @param mixed $old_value Previous value.
	 * @return void
	 */
	public function after_save( $value, $old_value ): void {
		// Only run if the value changed.
		if ( $value !== $old_value ) {
			AutomaticUpdates::instance()->activate_auto_update( $value );
			NotificationsHandler::get()->remove_notification_by_id( 'gtmkit-auto-update' );
		}
	}
}
