<?php
/**
 * Integration
 */

namespace TLA_Media\GTM_Kit\Integration;

use TLA_Media\GTM_Kit\Common\Util;
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
	 * @var Util
	 */
	protected $util;

	/**
	 * Constructor.
	 *
	 * @param Options $options
	 * @param Util $util
	 */
	public function __construct( Options $options, Util $util ) {
		$this->options = $options;
		$this->util = $util;
	}

	/**
	 * Get instance
	 */
	abstract static function instance();

	/**
	 * Register frontend
	 *
	 * @param Options $options
	 * @param Util $util
	 */
	abstract public static function register( Options $options, Util $util): void;

}
