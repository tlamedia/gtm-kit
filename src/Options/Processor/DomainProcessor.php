<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Options\Processor;

/**
 * Domain Processor
 *
 * Extracts and normalizes domain from URLs.
 */
final class DomainProcessor implements OptionProcessorInterface {

	/**
	 * Process domain value
	 *
	 * @param mixed $value New value.
	 * @param mixed $old_value Previous value.
	 * @return string Processed value.
	 */
	public function process( $value, $old_value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Extract domain from URL if the full URL is provided.
		if ( str_starts_with( $value, 'http://' ) || str_starts_with( $value, 'https://' ) ) {
			$url_parts = \wp_parse_url( $value );
			$value     = $url_parts['host'] ?? '';
		}

		// Remove trailing slash.
		return rtrim( $value, '/' );
	}

	/**
	 * Handle side effects after save
	 *
	 * @param mixed $value New value.
	 * @param mixed $old_value Previous value.
	 * @return void
	 */
	public function after_save( $value, $old_value ): void {
		// No side effects needed for domain changes.
	}
}
