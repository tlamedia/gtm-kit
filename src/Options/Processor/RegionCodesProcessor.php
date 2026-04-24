<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Options\Processor;

use TLA_Media\GTM_Kit\Options\OptionSchema;

/**
 * Region Codes Processor
 *
 * Normalizes the `gcm_region` array before storage by delegating to
 * {@see OptionSchema::sanitize_region_codes()}.
 */
final class RegionCodesProcessor implements OptionProcessorInterface {

	/**
	 * Process region codes value.
	 *
	 * @param mixed $value New value.
	 * @param mixed $old_value Previous value.
	 * @return array<int, string> Sanitized list of region codes.
	 */
	public function process( $value, $old_value ): array {
		return OptionSchema::sanitize_region_codes( $value );
	}

	/**
	 * No-op after_save hook.
	 *
	 * @param mixed $value New value.
	 * @param mixed $old_value Previous value.
	 * @return void
	 */
	public function after_save( $value, $old_value ): void {
	}
}
