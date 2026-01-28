<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Options\Processor;

/**
 * Option Processor Interface
 *
 * Processors handle business logic for specific options.
 */
interface OptionProcessorInterface {

	/**
	 * Process option value before storage
	 *
	 * @param mixed $value New value.
	 * @param mixed $old_value Previous value (if any).
	 * @return mixed Processed value.
	 */
	public function process( $value, $old_value );

	/**
	 * Handle side effects after the option is stored
	 *
	 * @param mixed $value New value.
	 * @param mixed $old_value Previous value.
	 * @return void
	 */
	public function after_save( $value, $old_value ): void;
}
