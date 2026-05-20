<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Options\Processor;

use TLA_Media\GTM_Kit\Options\OptionSchema;

/**
 * Excluded URL Patterns Processor
 *
 * Normalizes the `excluded_url_patterns` array before storage by
 * delegating to {@see OptionSchema::sanitize_excluded_url_patterns()}.
 */
final class ExcludedUrlPatternsProcessor implements OptionProcessorInterface {

	/**
	 * Process the excluded URL patterns value.
	 *
	 * @param mixed $value New value.
	 * @param mixed $old_value Previous value.
	 * @return array<int, array{pattern: string, mode: string}>
	 */
	public function process( $value, $old_value ): array {
		return OptionSchema::sanitize_excluded_url_patterns( $value );
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
