<?php
/**
 * Integration
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Integration;

use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options;

/**
 * AbstractIntegration
 */
abstract class AbstractIntegration {

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	protected $options;

	/**
	 * Utilities
	 *
	 * @var Util
	 */
	protected $util;

	/**
	 * Constructor.
	 *
	 * @param Options $options An instance of Options.
	 * @param Util    $util An instance of Util.
	 */
	public function __construct( Options $options, Util $util ) {
		$this->options = $options;
		$this->util    = $util;
	}

	/**
	 * Get instance
	 */
	abstract static function instance();

	/**
	 * Register frontend
	 *
	 * @param Options $options An instance of Options.
	 * @param Util $util An instance of Util.
	 */
	abstract public static function register( Options $options, Util $util ): void;
}
