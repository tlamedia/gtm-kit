<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Options\Processor;

/**
 * GTM ID Processor
 *
 * Sanitizes and validates GTM Container ID.
 */
final class GTMIdProcessor implements OptionProcessorInterface {

	/**
	 * Process GTM ID value
	 *
	 * @param mixed $value New value.
	 * @param mixed $old_value Previous value.
	 * @return string Processed value.
	 */
	public function process( $value, $old_value ): string {
		// Sanitize.
		$value = \sanitize_text_field( $value );

		// Ensure GTM- prefix (but allow empty values).
		if ( ! empty( $value ) ) {
			$value = strtoupper( trim( $value ) );
			if ( ! str_starts_with( $value, 'GTM-' ) ) {
				$value = 'GTM-' . $value;
			}
		}

		return $value;
	}

	/**
	 * Handle side effects after save
	 *
	 * @param mixed $value New value.
	 * @param mixed $old_value Previous value.
	 * @return void
	 */
	public function after_save( $value, $old_value ): void {
		// Clear cache when GTM ID changes.
		if ( $value !== $old_value ) {
			\wp_cache_delete( 'gtmkit_script_settings', 'gtmkit' );
		}
	}
}
