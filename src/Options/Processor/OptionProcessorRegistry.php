<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Options\Processor;

use TLA_Media\GTM_Kit\Options\OptionKeys;

/**
 * Option Processor Registry
 *
 * Manages registered processors for options.
 */
final class OptionProcessorRegistry {

	/**
	 * Registered processors
	 *
	 * @var array<string, OptionProcessorInterface>
	 */
	private array $processors = [];

	/**
	 * Constructor - Register default processors
	 */
	public function __construct() {
		$this->register_defaults();
	}

	/**
	 * Register default processors
	 *
	 * @return void
	 */
	private function register_defaults(): void {
		$this->register( OptionKeys::GENERAL_GTM_ID, new GTMIdProcessor() );
		$this->register( OptionKeys::GENERAL_SGTM_DOMAIN, new DomainProcessor() );
		$this->register( OptionKeys::MISC_AUTO_UPDATE, new AutoUpdateProcessor() );
	}

	/**
	 * Register processor for an option
	 *
	 * @param string                   $option_key Full option key (e.g., 'general.gtm_id').
	 * @param OptionProcessorInterface $processor Processor instance.
	 * @return void
	 */
	public function register( string $option_key, OptionProcessorInterface $processor ): void {
		$this->processors[ $option_key ] = $processor;
	}

	/**
	 * Get processor for an option
	 *
	 * @param string $option_key Full option key.
	 * @return OptionProcessorInterface|null
	 */
	public function get( string $option_key ): ?OptionProcessorInterface {
		return $this->processors[ $option_key ] ?? null;
	}

	/**
	 * Process value if processor exists
	 *
	 * @param string $option_key Full option key.
	 * @param mixed  $value New value.
	 * @param mixed  $old_value Previous value.
	 * @return mixed Processed value.
	 */
	public function process( string $option_key, $value, $old_value ) {
		$processor = $this->get( $option_key );
		return $processor ? $processor->process( $value, $old_value ) : $value;
	}

	/**
	 * Run after_save hooks if the processor exists
	 *
	 * @param string $option_key Full option key.
	 * @param mixed  $value New value.
	 * @param mixed  $old_value Previous value.
	 * @return void
	 */
	public function after_save( string $option_key, $value, $old_value ): void {
		$processor = $this->get( $option_key );
		if ( $processor ) {
			$processor->after_save( $value, $old_value );
		}
	}
}
