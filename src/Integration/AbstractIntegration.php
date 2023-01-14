<?php
/**
 * Integration
 */

namespace TLA_Media\GTM_Kit\Integration;

use TLA_Media\GTM_Kit\Options;

abstract class AbstractIntegration {

	/**
	 * Plugin instance.
	 */
	protected static $instance = null;

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
	 * Get instance
	 */
	abstract static function instance();

	/**
	 * Register frontend
	 *
	 * @param Options $options
	 */
	abstract public static function register( Options $options ): void;

}
