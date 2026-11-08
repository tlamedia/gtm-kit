<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Options;

use TLA_Media\GTM_Kit\Common\StapeLoader;
use TLA_Media\GTM_Kit\Common\StapeLoaderClient;
use TLA_Media\GTM_Kit\Options\Processor\GoogleTagGatewayProcessor;
use TLA_Media\GTM_Kit\Options\Processor\OptionProcessorRegistry;
use WP_Error;

/**
 * Saves settings the way the settings screen saves them.
 *
 * Every route that changes settings on a user's behalf goes through here,
 * so a change is validated, normalised, refused and read back the same way
 * whichever route it took.
 *
 * A save rejected by validation writes nothing. A save whose values read
 * back differently from the values written is reported rather than passed
 * off as a success. A save that switches on a Google tag gateway that
 * cannot serve is refused before anything is written.
 *
 * A save that changes the settings a Stape-issued loader is issued for asks
 * Stape for a new one. That request never refuses the save, because a
 * failure leaves the standard loader working, so the save lands and the
 * result says what became of the loader.
 *
 * The preview answers what a save would store without writing anything and
 * without sending any request.
 */
final class SettingsWriter {

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options An instance of Options.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Save the submitted settings.
	 *
	 * @param mixed $submitted The settings to save, by group, as the settings screen sends them.
	 *
	 * @return array{data: array<string, mixed>, sgtm_loader: array<string, mixed>}|WP_Error The saved settings and the loader state, or why the save failed.
	 */
	public function apply( $submitted ) {
		$gateway_refused = $this->refuse_gateway_switch_on( $submitted );

		if ( null !== $gateway_refused ) {
			return $gateway_refused;
		}

		// Record the value WordPress is about to store, after validation, type
		// coercion and every processor, so the comparison below never flags a
		// value that was normalised on purpose. The last write wins if saving
		// triggers another update of the same option.
		$written = null;
		$capture = static function ( $value ) use ( &$written ) {
			$written = $value;
			return $value;
		};

		// Validation rejects the whole save without writing anything, and
		// reports why only through this action.
		$rejected = [];
		$collect  = static function ( $errors ) use ( &$rejected ) {
			$rejected = is_array( $errors ) ? $errors : [];
		};

		$sgtm_loader   = new StapeLoader( $this->options );
		$loader_before = $sgtm_loader->get_save_baseline();

		add_action( 'gtmkit_options_validation_failed', $collect );
		add_filter( 'pre_update_option_' . Options::OPTION_NAME, $capture, PHP_INT_MAX );
		$this->options->set( $submitted );
		remove_filter( 'pre_update_option_' . Options::OPTION_NAME, $capture, PHP_INT_MAX );
		remove_action( 'gtmkit_options_validation_failed', $collect );

		if ( [] !== $rejected ) {
			return self::reject_invalid_options( $rejected );
		}

		if ( is_array( $submitted ) && is_array( $written ) ) {
			$not_kept = self::find_options_not_kept( $submitted, $written, $this->options->get_persisted() );

			if ( [] !== $not_kept ) {
				return new WP_Error(
					'gtmkit_save_not_kept',
					self::get_not_kept_message( count( $not_kept ) ),
					[
						'status'   => 409,
						'settings' => $not_kept,
					]
				);
			}
		}

		$outcome = $sgtm_loader->sync_after_save( new StapeLoaderClient( $this->options ), $loader_before );

		return [
			'data'        => $this->options->get_all_raw(),
			'sgtm_loader' => array_merge( $outcome, $sgtm_loader->get_client_state() ),
		];
	}

	/**
	 * Work out what saving a change would store, without storing it.
	 *
	 * The change is laid over the saved settings, validated as a whole the
	 * way a save validates it, and each changed value is coerced and run
	 * through its processor. Nothing is written and no request is sent: a
	 * Google tag gateway switch-on is only checked for the reason that needs
	 * no request, and the checks that do are left to the save.
	 *
	 * @param array<string, mixed> $changes The settings to change, by group.
	 *
	 * @return array{settings: array<string, array{current: mixed, proposed: mixed, kept: bool}>, invalid: array<string, string>, gateway_refusal: string|null, gateway_checked_on_save: bool}
	 */
	public function preview( array $changes ): array {
		$current = $this->options->get_all_raw();
		$merged  = $this->merge( $changes );
		$invalid = [];

		foreach ( ( new OptionValidator() )->validate_all( $merged ) as $option_key => $result ) {
			if ( ! $result->is_valid() ) {
				$invalid[ $option_key ] = self::describe_invalid( $result );
			}
		}

		$registry  = new OptionProcessorRegistry();
		$settings  = [];
		$requested = [];
		$switch    = false;

		foreach ( $changes as $group => $keys ) {
			if ( ! is_array( $keys ) ) {
				continue;
			}

			foreach ( $keys as $key => $value ) {
				$option_key = $group . '.' . $key;

				if ( isset( $invalid[ $option_key ] ) ) {
					continue;
				}

				$old_value = $current[ $group ][ $key ] ?? null;
				$schema    = OptionSchema::get_option_schema( $group, (string) $key );
				$coerced   = isset( $schema['type'] ) ? self::coerce( $value, (string) $schema['type'] ) : $value;

				// The gateway processor runs the network checks when the
				// gateway is switched on, so the switch is judged below
				// instead of through the processor.
				if ( OptionKeys::GENERAL_GOOGLE_TAG_GATEWAY === $option_key && true === $coerced && ! Options::to_boolean( $old_value ) ) {
					$switch   = true;
					$proposed = true;
				} else {
					$proposed = $registry->process( $option_key, $coerced, $old_value, $merged );
				}

				$merged[ $group ][ $key ] = $proposed;
				$settings[ $option_key ]  = [
					'current'  => $old_value ?? $this->options->get( $group, (string) $key, false ),
					'proposed' => $proposed,
					'kept'     => true,
				];
				$requested[ $option_key ] = $coerced;
			}
		}

		// The same whole-save pass a save runs after its processors.
		$processed = \apply_filters( 'gtmkit_process_options', $merged );

		foreach ( $settings as $option_key => $setting ) {
			$parts = explode( '.', $option_key, 2 );

			if ( is_array( $processed ) && isset( $processed[ $parts[0] ] ) && is_array( $processed[ $parts[0] ] ) && array_key_exists( $parts[1], $processed[ $parts[0] ] ) ) {
				$setting['proposed'] = $processed[ $parts[0] ][ $parts[1] ];
			}

			$setting['kept']         = $setting['proposed'] === $requested[ $option_key ];
			$settings[ $option_key ] = $setting;
		}

		$gateway_refusal = null;

		if ( $switch && '' !== (string) ( $merged['general']['sgtm_domain'] ?? '' ) ) {
			$gateway_refusal = self::get_gateway_refusal_message( GoogleTagGatewayProcessor::REFUSED_SGTM_DOMAIN );
		}

		return [
			'settings'                => $settings,
			'invalid'                 => $invalid,
			'gateway_refusal'         => $gateway_refusal,
			'gateway_checked_on_save' => $switch && null === $gateway_refusal,
		];
	}

	/**
	 * Lay a change over the saved settings.
	 *
	 * The settings screen saves every setting of a group at once, and a group
	 * a save carries replaces the stored group, so a change to a few settings
	 * is saved as the full set with those settings replaced.
	 *
	 * @param array<string, mixed> $changes The settings to change, by group.
	 *
	 * @return array<string, mixed>
	 */
	public function merge( array $changes ): array {
		return Options::array_merge_recursive( $this->options->get_all_raw(), $changes );
	}

	/**
	 * Explain why the Google tag gateway could not be switched on.
	 *
	 * @param string $reason One of the gateway processor's refusal reasons.
	 *
	 * @return string
	 */
	public static function get_gateway_refusal_message( string $reason ): string {
		switch ( $reason ) {
			case GoogleTagGatewayProcessor::REFUSED_SGTM_DOMAIN:
				return __( 'Your changes were not saved, because the Google tag gateway cannot be switched on while a custom server-side tagging domain is set. Clear the domain or leave the gateway off, then save again.', 'gtm-kit' );

			case GoogleTagGatewayProcessor::REFUSED_SERVICE:
				return __( 'Your changes were not saved, because the Google tag gateway cannot be switched on: your server could not connect to the gateway service. Some hosts block outgoing connections. Ask your host to allow outgoing HTTPS connections, or leave the gateway off, then save again.', 'gtm-kit' );

			default:
				return __( 'Your changes were not saved, because the Google tag gateway cannot be switched on: the address that serves the Google tag from your own domain did not serve your container. Check that the container is published in Google Tag Manager, and that your host, firewall or security plugin does not block this address, or leave the gateway off, then save again.', 'gtm-kit' );
		}
	}

	/**
	 * Find the submitted options whose written value did not survive the read-back.
	 *
	 * Only keys the request carried and the save actually wrote are compared,
	 * and strictly: both sides went through the same serialisation, so any
	 * difference is a real one.
	 *
	 * @param array<string, mixed> $submitted The options the request carried.
	 * @param array<string, mixed> $written The options WordPress stored.
	 * @param array<string, mixed> $persisted The options as read back.
	 *
	 * @return array<int, string> The `group.key` of each option not kept.
	 */
	public static function find_options_not_kept( array $submitted, array $written, array $persisted ): array {
		$not_kept = [];

		foreach ( $submitted as $group => $keys ) {
			if ( ! is_array( $keys ) || ! isset( $written[ $group ] ) || ! is_array( $written[ $group ] ) ) {
				continue;
			}

			$kept = ( isset( $persisted[ $group ] ) && is_array( $persisted[ $group ] ) ) ? $persisted[ $group ] : [];

			foreach ( array_keys( $keys ) as $key ) {
				if ( ! array_key_exists( $key, $written[ $group ] ) ) {
					continue;
				}

				if ( ! array_key_exists( $key, $kept ) || $kept[ $key ] !== $written[ $group ][ $key ] ) {
					$not_kept[] = $group . '.' . $key;
				}
			}
		}

		return $not_kept;
	}

	/**
	 * Refuse a save that switches the Google tag gateway on when it cannot serve.
	 *
	 * The checks run afresh here rather than from a stored result, because
	 * the site owner may have just fixed what failed last time. A refusal
	 * rejects the whole save and says why, rather than letting the rest land
	 * while the toggle quietly flips back off.
	 *
	 * @param mixed $submitted The settings to save.
	 *
	 * @return WP_Error|null
	 */
	private function refuse_gateway_switch_on( $submitted ): ?WP_Error {
		if ( ! is_array( $submitted ) || ! isset( $submitted['general'] ) || ! is_array( $submitted['general'] ) || Options::to_boolean( $this->options->get( 'general', 'google_tag_gateway' ) ) ) {
			return null;
		}

		// Only the values the validator accepts as "on" count as a switch-on.
		// Anything else is either off or rejected by validation, which then
		// names the setting, so a string such as "false" never triggers the
		// network checks or a refusal that blames the gateway.
		if ( ! in_array( $submitted['general']['google_tag_gateway'] ?? false, [ true, 1, '1', 'true' ], true ) ) {
			return null;
		}

		$reason = GoogleTagGatewayProcessor::refusal( $submitted, true );

		if ( null === $reason ) {
			return null;
		}

		return new WP_Error(
			'gtmkit_gateway_refused',
			self::get_gateway_refusal_message( $reason ),
			[
				'status' => 400,
				'params' => [ 'general.google_tag_gateway' => $reason ],
			]
		);
	}

	/**
	 * Explain a save that validation rejected, naming each setting and why.
	 *
	 * The message is for the user. `params` follows WordPress's convention
	 * for invalid REST parameters and carries the validator's own detail per
	 * setting, for support and debugging.
	 *
	 * @param array<mixed> $errors Validation results keyed by `group.key`.
	 *
	 * @return WP_Error
	 */
	private static function reject_invalid_options( array $errors ): WP_Error {
		$named  = [];
		$params = [];

		foreach ( $errors as $option_key => $result ) {
			if ( ! $result instanceof ValidationResult ) {
				continue;
			}

			$parts = explode( '.', (string) $option_key, 2 );

			/* translators: 1: the name of a setting, 2: why its value was rejected. */
			$named[]                        = sprintf( __( '%1$s (%2$s)', 'gtm-kit' ), $parts[1] ?? $parts[0], self::describe_invalid( $result ) );
			$params[ (string) $option_key ] = $result->get_error_message();
		}

		$count = count( $named );

		return new WP_Error(
			'gtmkit_settings_rejected',
			sprintf(
				/* translators: 1: a number of settings, 2: the settings, each with the reason its value was rejected. */
				_n(
					'Your changes were not saved, because %1$d setting has a value GTM Kit cannot accept: %2$s. Correct it and save again.',
					'Your changes were not saved, because %1$d settings have values GTM Kit cannot accept: %2$s. Correct them and save again.',
					$count,
					'gtm-kit'
				),
				$count,
				wp_sprintf( '%l', $named )
			),
			[
				'status' => 400,
				'params' => $params,
			]
		);
	}

	/**
	 * Say in plain words why a value was rejected.
	 *
	 * @param ValidationResult $result A failed validation result.
	 *
	 * @return string
	 */
	private static function describe_invalid( ValidationResult $result ): string {
		return ( 'type_mismatch' === $result->get_error_code() )
			? __( 'wrong type of value', 'gtm-kit' )
			: __( 'value not accepted', 'gtm-kit' );
	}

	/**
	 * Explain a save that did not stick, naming the likely cause.
	 *
	 * @param int $count The number of settings not kept.
	 *
	 * @return string
	 */
	private static function get_not_kept_message( int $count ): string {
		if ( wp_using_ext_object_cache() ) {
			return sprintf(
				/* translators: %d is a number of settings. */
				_n(
					'Your changes were sent, but %d setting still reads back with its old value. Your site uses a persistent object cache, which is most likely serving an outdated copy of the settings. Flush the object cache, from your caching plugin or your host\'s control panel, then save again.',
					'Your changes were sent, but %d settings still read back with their old values. Your site uses a persistent object cache, which is most likely serving an outdated copy of the settings. Flush the object cache, from your caching plugin or your host\'s control panel, then save again.',
					$count,
					'gtm-kit'
				),
				$count
			);
		}

		return sprintf(
			/* translators: %d is a number of settings. */
			_n(
				'Your changes were sent, but %d setting still reads back with its old value, so the database most likely did not keep the change. Ask your host to check the database error log, then save again.',
				'Your changes were sent, but %d settings still read back with their old values, so the database most likely did not keep the change. Ask your host to check the database error log, then save again.',
				$count,
				'gtm-kit'
			),
			$count
		);
	}

	/**
	 * Coerce a value to its schema type the way a save does.
	 *
	 * Mirrors the coercion Options applies before its processors, so the
	 * value a preview proposes is the value the save stores.
	 *
	 * @param mixed  $value The value.
	 * @param string $type The schema type.
	 *
	 * @return mixed
	 */
	private static function coerce( $value, string $type ) {
		switch ( $type ) {
			case 'integer':
				return (int) $value;
			case 'boolean':
				return Options::to_boolean( $value );
			case 'string':
				return (string) $value;
			case 'array':
				return is_array( $value ) ? $value : [];
			default:
				return $value;
		}
	}
}
