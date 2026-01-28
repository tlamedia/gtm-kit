<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Options;

/**
 * Options Factory
 *
 * Manages Options instance creation and singleton.
 */
final class OptionsFactory {

	/**
	 * Singleton instance
	 *
	 * @var Options|null
	 */
	private static ?Options $instance = null;

	/**
	 * Get a singleton instance
	 *
	 * @return Options
	 */
	public static function get_instance(): Options {
		if ( ! self::$instance ) {
			self::$instance = new Options();
		}

		return self::$instance;
	}

	/**
	 * Create a new instance (for testing)
	 *
	 * @return Options
	 */
	public static function create(): Options {
		return new Options();
	}

	/**
	 * Reset singleton (for testing)
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instance = null;
	}
}
