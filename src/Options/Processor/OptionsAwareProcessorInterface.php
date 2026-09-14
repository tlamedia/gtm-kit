<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Options\Processor;

/**
 * A processor whose outcome depends on other options in the same save.
 *
 * The registry hands such a processor the whole set of options being saved, so
 * it judges a value against the save it belongs to rather than against the
 * stored options that save is about to replace.
 */
interface OptionsAwareProcessorInterface extends OptionProcessorInterface {

	/**
	 * Process a value in the context of the options being saved with it.
	 *
	 * @param mixed                $value New value.
	 * @param mixed                $old_value Previous value.
	 * @param array<string, mixed> $options The options being saved.
	 *
	 * @return mixed Processed value.
	 */
	public function process_with_options( $value, $old_value, array $options );
}
