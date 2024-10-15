<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit;

use TLA_Media\GTM_Kit\Admin\NotificationsHandler;
use TLA_Media\GTM_Kit\Installation\AutomaticUpdates;

/**
 * Options
 */
final class Options {

	/**
	 * The option_name in wp_options table.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'gtmkit';

	/**
	 * All the options.
	 *
	 * @var array<string, mixed>
	 */
	private array $options;

	/**
	 * Map of all the default options
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	private static array $map = [
		'general'      => [
			'gtm_id'                  => [
				'default'  => '',
				'constant' => 'GTMKIT_CONTAINER_ID',
			],
			'script_implementation'   => [ 'default' => 0 ],
			'noscript_implementation' => [ 'default' => 0 ],
			'container_active'        => [
				'default'  => true,
				'constant' => 'GTMKIT_CONTAINER_ACTIVE',
				'type'     => 'boolean',
			],
			'sgtm_domain'             => [ 'default' => '' ],
			'event_inspector'         => [
				'default'  => false,
				'constant' => 'GTMKIT_EVENT_INSPECTOR',
				'type'     => 'boolean',
			],
			'console_log'             => [
				'default'  => false,
				'constant' => 'GTMKIT_CONSOLE_LOG',
				'type'     => 'boolean',
			],
			'debug_log'               => [
				'default'  => false,
				'constant' => 'GTMKIT_DEBUG_LOG',
				'type'     => 'boolean',
			],
			'gtm_auth'                => [
				'default'  => '',
				'constant' => 'GTMKIT_AUTH',
			],
			'gtm_preview'             => [
				'default'  => '',
				'constant' => 'GTMKIT_PREVIEW',
			],
			'datalayer_page_type'     => [
				'default' => true,
				'type'    => 'boolean',
			],
			'exclude_user_roles'      => [ 'default' => [] ],
		],
		'integrations' => [
			'woocommerce_shipping_info'             => [ 'default' => 1 ],
			'woocommerce_payment_info'              => [ 'default' => 1 ],
			'woocommerce_variable_product_tracking' => [ 'default' => 0 ],
			'woocommerce_view_item_list_limit'      => [ 'default' => 0 ],
			'woocommerce_debug_track_purchase'      => [
				'default'  => false,
				'constant' => 'GTMKIT_WC_DEBUG_TRACK_PURCHASE',
				'type'     => 'boolean',
			],
			'cf7_load_js'                           => [ 'default' => 1 ],
			'edd_debug_track_purchase'              => [
				'default'  => false,
				'constant' => 'GTMKIT_EDD_DEBUG_TRACK_PURCHASE',
				'type'     => 'boolean',
			],
		],
		'premium'      => [
			'addon_installed' => [
				'default' => false,
				'type'    => 'boolean',
			],
		],
		'misc'         => [
			'auto_update' => [
				'default' => true,
				'type'    => 'boolean',
			],
		],
	];

	/**
	 * Construct
	 */
	public function __construct() {
		$this->options = \get_option( self::OPTION_NAME, [] );

		\add_filter( 'pre_update_option_gtmkit', [ $this, 'pre_update_option' ], 10, 2 );
	}

	/**
	 * Initialize options
	 *
	 * @return Options
	 * @example Options::init()->get('general', 'gtm_id');
	 */
	public static function init(): self {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Pre update option
	 *
	 * @param mixed $new_value The new value.
	 * @param mixed $old_value The old value.
	 *
	 * @return array<string, mixed>|null
	 */
	public function pre_update_option( $new_value, $old_value ): ?array {
		if ( ! is_array( $new_value ) || ! is_array( $old_value ) ) {
			return $new_value;
		}
		return array_merge( $old_value, $new_value );
	}

	/**
	 * The default options.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {

		if ( ! function_exists( 'is_plugin_active' ) ) {
			// @phpstan-ignore-next-line
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$map = apply_filters( 'gtmkit_options_defaults', self::$map );

		if ( \is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$map['integrations']['woocommerce_integration'] = [
				'default' => true,
			];
		}
		if ( \is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {
			$map['integrations']['cf7_integration'] = [
				'default' => true,
			];
		}
		if ( ( \is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) || \is_plugin_active( 'easy-digital-downloads-pro/easy-digital-downloads.php' ) ) ) {
			$map['integrations']['edd_integration'] = [
				'default' => true,
			];
		}

		return $map;
	}

	/**
	 * Get options by a group and a key.
	 *
	 * @param string $group The option group.
	 * @param string $key The option key.
	 * @param bool   $strip_slashes If the slashes should be stripped from string values.
	 *
	 * @return mixed|null Null if value doesn't exist anywhere: in constants, in DB, in a map. So it's completely custom or a typo.
	 * @example Options::init()->get( 'general', 'gtm_id' ).
	 */
	public function get( string $group, string $key, bool $strip_slashes = true ) {
		$map = $this->get_default_key_value( $group, $key );

		if ( $this->is_const_defined( $group, $key ) ) {
			$value = constant( $map['constant'] );
		} elseif ( isset( $this->options[ $group ][ $key ] ) ) {
			$value = $this->options[ $group ][ $key ];
		} elseif ( $map ) {
			$value = $map['default'];
		} else {
			return null;
		}

		return is_string( $value ) && $strip_slashes && ! $this->is_const_defined( $group, $key )
			? stripslashes( $value )
			: $value;
	}

	/**
	 * Is overriding options with constants enabled or not.
	 *
	 * @return bool
	 */
	public function is_const_enabled(): bool {

		return defined( 'GTMKIT_ON' ) && GTMKIT_ON === true;
	}

	/**
	 * Get default value for a key
	 *
	 * @param string $group The option group.
	 * @param string $key The option key.
	 *
	 * @return array<string, mixed>|null
	 */
	protected function get_default_key_value( string $group, string $key ): ?array {
		$defaults = $this->get_defaults();
		return $defaults[ $group ][ $key ] ?? null;
	}

	/**
	 * Is constant defined.
	 *
	 * @param string $group The option group.
	 * @param string $key The option key.
	 *
	 * @return bool
	 */
	public function is_const_defined( string $group, string $key ): bool {

		if ( ! $this->is_const_enabled() ) {
			return false;
		}

		$map = $this->get_default_key_value( $group, $key );
		if ( ! $map || ! isset( $map['constant'] ) || ! defined( $map['constant'] ) ) {
			return false;
		}

		$value_type = gettype( constant( $map['constant'] ) );

		if ( isset( $map['type'] ) && $map['type'] !== $value_type ) {
			return false;
		}

		return true;
	}

	/**
	 * Set plugin options.
	 *
	 * @param array<string, mixed> $options Plugin options.
	 * @param bool                 $first_install Add option on first install.
	 * @param bool                 $overwrite_existing Overwrite existing settings or merge.
	 */
	public function set( array $options, bool $first_install = false, bool $overwrite_existing = true ): void {

		if ( ! $overwrite_existing ) {
			$options = self::array_merge_recursive( $this->get_all_raw(), $options );
		}

		if ( $first_install === false ) {
			$options = $this->process_options( $options );
		}

		// Whether to update existing options or to add these options only once if they don't exist yet.
		if ( $first_install ) {
			\add_option( self::OPTION_NAME, $options, '', true );
		} elseif ( is_multisite() ) {
			\update_blog_option( get_current_blog_id(), self::OPTION_NAME, $options );
		} else {
			\update_option( self::OPTION_NAME, $options, true );
		}

		$this->clear_cache();
	}

	/**
	 * Set single option.
	 *
	 * @param string $group Option group.
	 * @param string $key Option key.
	 * @param mixed  $value Option value.
	 */
	public function set_option( string $group, string $key, $value ): void {

		$options = $this->get_all_raw();

		$options[ $group ][ $key ] = $value;

		if ( is_multisite() ) {
			\update_blog_option( get_current_blog_id(), self::OPTION_NAME, $options );
		} else {
			\update_option( self::OPTION_NAME, $options, true );
		}

		$this->clear_cache();
	}

	/**
	 * Clear the cache
	 *
	 * @return void
	 */
	private function clear_cache(): void {
		wp_cache_delete( self::OPTION_NAME, 'options' );
		wp_cache_delete( 'gtmkit_script_settings', 'gtmkit' );
		$this->options = get_option( self::OPTION_NAME, [] );
	}

	/**
	 * Process the plugin options.
	 *
	 * @param array<string, mixed> $options The options array.
	 *
	 * @return array<string, mixed>
	 */
	private function process_options( array $options ): array {

		$old_options = $this->get_all_raw();

		foreach ( $options as $group => $keys ) {
			foreach ( $keys as $option_name => $option_value ) {
				switch ( $group ) {
					case 'general':
						if ( $option_name === 'gtm_id' ) {
							$options[ $group ][ $option_name ] = \sanitize_text_field( $option_value );
						}
						break;

					case 'misc':
						if ( $option_name === 'auto_update' ) {
							if ( $old_options[ $group ][ $option_name ] !== $option_value ) {
								$this->auto_update_setting( $option_value );
							}
						}
				}
			}
		}

		return $options;
	}

	/**
	 * Merge recursively, including a proper substitution of values in sub-arrays when keys are the same.
	 *
	 * @return array<string, mixed>
	 */
	public static function array_merge_recursive(): array {

		$arrays = func_get_args();

		if ( count( $arrays ) < 2 ) {
			return $arrays[0] ?? [];
		}

		$merged = [];

		while ( $arrays ) {
			$array = array_shift( $arrays );

			if ( ! is_array( $array ) ) {
				return [];
			}

			if ( empty( $array ) ) {
				continue;
			}

			foreach ( $array as $key => $value ) {
				if ( is_string( $key ) ) {
					if (
						is_array( $value ) &&
						array_key_exists( $key, $merged ) &&
						is_array( $merged[ $key ] )
					) {
						$merged[ $key ] = call_user_func( __METHOD__, $merged[ $key ], $value );
					} else {
						$merged[ $key ] = $value;
					}
				} else {
					$merged[] = $value;
				}
			}
		}

		return $merged;
	}

	/**
	 * Get all the options, but without stripping the slashes.
	 *
	 * @return array<string, mixed>
	 */
	public function get_all_raw(): array {

		$options = $this->options;

		foreach ( $options as $group => $g_value ) {
			foreach ( $g_value as $key => $value ) {
				$options[ $group ][ $key ] = $this->get( $group, $key, false );
			}
		}

		return $options;
	}

	/**
	 * Auto-update setting
	 *
	 * @param bool $activate Activate or deactivate auto-updates.
	 */
	private function auto_update_setting( bool $activate ): void {
		AutomaticUpdates::instance()->activate_auto_update( $activate );
		NotificationsHandler::get()->remove_notification_by_id( 'gtmkit-auto-update' );
	}
}
