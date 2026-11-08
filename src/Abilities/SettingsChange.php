<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Abilities;

use TLA_Media\GTM_Kit\Options\Options;
use TLA_Media\GTM_Kit\Options\OptionSchema;
use TLA_Media\GTM_Kit\Options\SettingsWriter;
use WP_Error;

/**
 * Changes a short list of settings in two steps: preview, then confirm.
 *
 * The Abilities API has no confirmation step of its own, and the hints an
 * ability carries (read-only, destructive) are only hints a client may
 * ignore. So the confirmation is enforced here. A preview works out what a
 * change would store, writes nothing, and hands back a single-use code. Only
 * that code applies the change, and it applies exactly the change that was
 * previewed: the apply step takes no change of its own.
 *
 * A code is refused once it has been used, once it has expired, when anyone
 * but the user it was issued to presents it, and when the settings changed
 * after the preview, since the preview no longer describes what would
 * happen. Only a hash of the code is stored.
 *
 * Both steps save through {@see SettingsWriter}, the save path the settings
 * screen uses, so a change is validated, normalised and refused the same way
 * on either route.
 */
final class SettingsChange {

	/**
	 * How long a confirmation code stays valid, in seconds.
	 *
	 * @var int
	 */
	public const TOKEN_LIFETIME = 5 * MINUTE_IN_SECONDS;

	/**
	 * The prefix of the transient holding a confirmation code's record.
	 *
	 * The record outlives the code by an hour, so a late or repeated
	 * apply is told the code expired or was used rather than that it is
	 * unknown.
	 *
	 * @var string
	 */
	private const TOKEN_PREFIX = 'gtmkit_settings_change_';

	/**
	 * The prefix of the option that claims a code for one apply while it runs.
	 *
	 * @var string
	 */
	private const LOCK_PREFIX = 'gtmkit_settings_change_lock_';

	/**
	 * The settings core lets an assistant change.
	 *
	 * @var array<int, string>
	 */
	private const WRITABLE = [
		'general.gtm_id',
		'general.sgtm_domain',
		'general.google_tag_gateway',
		'general.datalayer_name',
		'general.gcm_default_settings',
		'general.gcm_ad_storage',
		'general.gcm_analytics_storage',
		'general.gcm_ad_user_data',
		'general.gcm_ad_personalization',
		'general.gcm_personalization_storage',
		'general.gcm_functionality_storage',
		'general.gcm_security_storage',
		'integrations.woocommerce_integration',
		'integrations.cf7_integration',
		'integrations.edd_integration',
	];

	/**
	 * The settings no assistant may change, whatever the filter adds.
	 *
	 * Container environment values and the loader Stape issued are secrets
	 * or state GTM Kit manages itself. Any setting naming a licence is
	 * refused as well, by pattern.
	 *
	 * @var array<int, string>
	 */
	private const NEVER_WRITABLE = [
		'general.gtm_auth',
		'general.gtm_preview',
		'general.sgtm_stape_issued_loader',
	];

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * The shared save path.
	 *
	 * @var SettingsWriter
	 */
	private SettingsWriter $writer;

	/**
	 * Constructor.
	 *
	 * @param Options $options An instance of Options.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
		$this->writer  = new SettingsWriter( $options );
	}

	/**
	 * Whether GTM Kit registers the abilities that change settings.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		/**
		 * Filters whether AI assistants may change GTM Kit settings through the WordPress Abilities API.
		 *
		 * Off by default. Returning true registers two more abilities for
		 * users who can manage options: one that previews a change to a
		 * short list of settings (the container ID, the server-side tagging
		 * domain, the Google tag gateway, the Consent Mode defaults and the
		 * integrations) and one that applies a previewed change. A change is
		 * only ever applied with the single-use code its preview returned,
		 * within five minutes, by the same user, and only while the settings
		 * are as they were at the preview.
		 *
		 * The abilities are registered only while `gtmkit_abilities_enabled`
		 * is true as well. WordPress builds the abilities registry once per
		 * request, so add this filter from a plugin or the theme's
		 * functions.php rather than later in the request.
		 *
		 * @param bool $enabled Whether to register the abilities that change settings. Default false.
		 */
		return (bool) apply_filters( 'gtmkit_write_abilities_enabled', false );
	}

	/**
	 * The settings an assistant may change, as `group.key`.
	 *
	 * @return array<int, string>
	 */
	public static function get_writable(): array {
		/**
		 * Filters the settings AI assistants may change through the Abilities API.
		 *
		 * Add the `group.key` of a setting to let an assistant change it.
		 * Only additions take effect: the settings GTM Kit lists are always
		 * kept, and container environment values, the Stape-issued loader and
		 * anything naming a licence are always removed.
		 *
		 * @param array<int, string> $settings The writable settings, as `group.key`.
		 */
		$filtered = apply_filters( 'gtmkit_abilities_writable_settings', self::WRITABLE );
		$filtered = is_array( $filtered ) ? array_filter( $filtered, 'is_string' ) : [];
		$writable = array_values( array_unique( array_merge( self::WRITABLE, $filtered ) ) );

		return array_values(
			array_filter(
				$writable,
				static fn( string $key ): bool => 1 === preg_match( '/^[a-z0-9_]+\.[a-z0-9_]+$/', $key ) && ! self::is_never_writable( $key )
			)
		);
	}

	/**
	 * Whether a setting may never be changed by an assistant.
	 *
	 * @param string $option_key The setting, as `group.key`.
	 *
	 * @return bool
	 */
	private static function is_never_writable( string $option_key ): bool {
		return in_array( $option_key, self::NEVER_WRITABLE, true ) || 1 === preg_match( '/licen[cs]e/i', $option_key );
	}

	/**
	 * The input schema of the change, typed from the settings schema.
	 *
	 * Typed so that a value sent as text, as a GET request sends every
	 * value, reaches the save as the type the setting holds. Unknown
	 * settings are let through to be refused by name.
	 *
	 * @return array<string, mixed>
	 */
	public static function changes_schema(): array {
		$groups = [];

		foreach ( self::get_writable() as $option_key ) {
			[ $group, $key ] = explode( '.', $option_key, 2 );

			$schema = OptionSchema::get_option_schema( $group, $key );
			$type   = ( isset( $schema['type'] ) && in_array( $schema['type'], [ 'string', 'boolean', 'integer' ], true ) )
				? $schema['type']
				: [ 'string', 'boolean', 'integer', 'number' ];

			$groups[ $group ]['type']                 = 'object';
			$groups[ $group ]['additionalProperties'] = true;
			$groups[ $group ]['properties'][ $key ]   = [ 'type' => $type ];
		}

		return [
			'type'                 => 'object',
			'description'          => __( 'The settings to change, by group and key, such as {"general": {"gtm_id": "GTM-XXXXXXX"}}.', 'gtm-kit' ),
			'minProperties'        => 1,
			'additionalProperties' => true,
			'properties'           => $groups,
		];
	}

	/**
	 * Preview a change, writing nothing, and issue the code that applies it.
	 *
	 * @param array<string, mixed> $input The ability input.
	 *
	 * @return array<string, mixed>
	 */
	public function preview( array $input ): array {
		$changes = is_array( $input['changes'] ?? null ) ? $input['changes'] : [];
		// A value the schema cannot coerce is left as sent, for the save's own
		// validation to refuse by name.
		$sanitized = rest_sanitize_value_from_schema( $changes, self::changes_schema() );
		$changes   = is_array( $sanitized ) ? $sanitized : $changes;

		$writable = self::get_writable();
		$allowed  = [];
		$refused  = [];

		foreach ( $changes as $group => $keys ) {
			if ( ! is_array( $keys ) ) {
				$refused[] = self::refusal( (string) $group, __( 'Not a group of settings.', 'gtm-kit' ) );
				continue;
			}

			foreach ( $keys as $key => $value ) {
				$option_key = $group . '.' . $key;

				if ( ! in_array( $option_key, $writable, true ) ) {
					$refused[] = self::refusal( $option_key, __( 'This setting cannot be changed by an assistant.', 'gtm-kit' ) );
					continue;
				}

				$allowed[ $group ][ $key ] = $value;
			}
		}

		$preview  = $this->writer->preview( $allowed );
		$settings = [];

		foreach ( $preview['invalid'] as $option_key => $reason ) {
			/* translators: %s: why the value was rejected, such as "wrong type of value". */
			$refused[] = self::refusal( (string) $option_key, sprintf( __( 'The value was rejected: %s.', 'gtm-kit' ), $reason ) );
		}

		$changed  = false;
		$warnings = [];

		foreach ( $preview['settings'] as $option_key => $setting ) {
			$changes_value = $setting['current'] !== $setting['proposed'];
			$changed       = $changed || $changes_value;
			$settings[]    = [
				'setting'  => (string) $option_key,
				'current'  => $setting['current'],
				'proposed' => $setting['proposed'],
				'kept'     => $setting['kept'],
				'changes'  => $changes_value,
			];

			// An empty name means the default, so only a change of the name the pages use is a rename.
			if ( 'general.datalayer_name' === (string) $option_key && self::datalayer_name( $setting['current'] ) !== self::datalayer_name( $setting['proposed'] ) ) {
				$warnings[] = __( 'Renaming the data layer affects scripts outside GTM Kit: theme code, a consent platform or any other script that pushes to the old name stops reaching Google Tag Manager until it is updated to the new name.', 'gtm-kit' );
			}
		}

		$result = [
			'settings'                 => $settings,
			'refused'                  => $refused,
			'warnings'                 => $warnings,
			'gateway_refusal'          => $preview['gateway_refusal'],
			'gateway_checked_on_apply' => $preview['gateway_checked_on_save'],
			'confirmation_token'       => null,
			'expires_at'               => null,
		];

		// A change that would store nothing new, or that the save would
		// refuse anyway, gets no code to apply it with.
		if ( ! $changed || [] !== $preview['invalid'] || null !== $preview['gateway_refusal'] ) {
			return $result;
		}

		$token   = bin2hex( random_bytes( 32 ) );
		$expires = time() + self::TOKEN_LIFETIME;

		set_transient(
			self::TOKEN_PREFIX . self::hash( $token ),
			[
				'user_id'      => get_current_user_id(),
				'changes'      => $allowed,
				'options_hash' => $this->options_hash(),
				'expires_at'   => $expires,
				'used'         => false,
			],
			self::TOKEN_LIFETIME + HOUR_IN_SECONDS
		);

		$result['confirmation_token'] = $token;
		$result['expires_at']         = $expires;

		return $result;
	}

	/**
	 * The data layer name the pages use for a stored value.
	 *
	 * @param mixed $value The stored value.
	 *
	 * @return string The name, with an empty value read as the default.
	 */
	private static function datalayer_name( $value ): string {
		return ( is_string( $value ) && '' !== $value ) ? $value : 'dataLayer';
	}

	/**
	 * Apply the change a confirmation code was issued for.
	 *
	 * The code is used up by the attempt, whether the change lands or not.
	 *
	 * @param array<string, mixed> $input The ability input.
	 *
	 * @return array<string, mixed>|WP_Error What was written, or why the code was refused.
	 */
	public function apply( array $input ) {
		$token = is_string( $input['confirmation_token'] ?? null ) ? $input['confirmation_token'] : '';

		if ( '' === $token ) {
			return self::refuse( self::unknown_message() );
		}

		// Claimed atomically before the record is read, so two applies of one
		// code arriving at the same moment cannot both find it unused.
		$lock = self::LOCK_PREFIX . self::hash( $token );

		if ( ! self::claim( $lock ) ) {
			return self::refuse( self::used_message() );
		}

		try {
			return $this->apply_claimed( self::TOKEN_PREFIX . self::hash( $token ) );
		} finally {
			self::release( $lock );
		}
	}

	/**
	 * Apply the change a claimed confirmation code was issued for.
	 *
	 * @param string $name The transient holding the code's record.
	 *
	 * @return array<string, mixed>|WP_Error What was written, or why the code was refused.
	 */
	private function apply_claimed( string $name ) {
		$record = get_transient( $name );

		if ( ! is_array( $record ) || ! isset( $record['user_id'], $record['changes'], $record['options_hash'], $record['expires_at'] ) ) {
			return self::refuse( self::unknown_message() );
		}

		if ( ! empty( $record['used'] ) ) {
			return self::refuse( self::used_message() );
		}

		$record['used'] = true;
		set_transient( $name, $record, max( 1, (int) $record['expires_at'] + HOUR_IN_SECONDS - time() ) );

		if ( (int) $record['user_id'] !== get_current_user_id() ) {
			return self::refuse( __( 'This confirmation code was issued to another user. Preview the change yourself to get a code.', 'gtm-kit' ) );
		}

		if ( time() > (int) $record['expires_at'] ) {
			return self::refuse( __( 'This confirmation code has expired. Preview the change again to get a new code.', 'gtm-kit' ) );
		}

		if ( ! hash_equals( (string) $record['options_hash'], $this->options_hash() ) ) {
			return self::refuse( __( 'The settings changed after this change was previewed, so the preview no longer shows what would happen. Preview the change again.', 'gtm-kit' ) );
		}

		$changes = is_array( $record['changes'] ) ? $record['changes'] : [];
		$keys    = [];

		foreach ( $changes as $group => $group_changes ) {
			foreach ( array_keys( (array) $group_changes ) as $key ) {
				$keys[] = $group . '.' . $key;
			}
		}

		$saved    = $this->writer->apply( $this->writer->merge( $changes ) );
		$not_kept = [];
		$refusal  = null;

		if ( $saved instanceof WP_Error ) {
			$refusal = $saved->get_error_message();
			$data    = $saved->get_error_data();

			if ( 'gtmkit_save_not_kept' === $saved->get_error_code() && is_array( $data ) && isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
				$not_kept = array_values( array_intersect( $keys, $data['settings'] ) );
			}
		}

		// A save that did not keep every value was still written.
		$written = ( null === $refusal || [] !== $not_kept ) ? array_values( array_diff( $keys, $not_kept ) ) : [];

		if ( null === $refusal ) {
			/**
			 * Fires after an AI assistant changed settings through the Abilities API.
			 *
			 * Carries the settings changed, never their values, so a site can
			 * log who changed what without storing secrets.
			 *
			 * @param int                $user_id The user who confirmed the change.
			 * @param array<int, string> $settings The settings written, as `group.key`.
			 */
			do_action( 'gtmkit_settings_changed_by_ability', get_current_user_id(), $written );
		}

		$abilities = new Abilities( $this->options );

		return [
			'applied'        => null === $refusal,
			'written'        => $written,
			'not_kept'       => $not_kept,
			'refusal'        => $refusal,
			'configuration'  => $abilities->get_configuration(),
			'consent_status' => $abilities->get_consent_status(),
		];
	}

	/**
	 * Claim a confirmation code for one apply, atomically.
	 *
	 * The database's unique option name decides which of two simultaneous
	 * requests wins, which neither a transient nor a non-persistent object
	 * cache can.
	 *
	 * @param string $lock The claim's option name.
	 *
	 * @return bool Whether this request holds the claim.
	 */
	private static function claim( string $lock ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- An atomic insert is the point; the option is never read through the options API.
		return 1 === (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)", $lock, (string) time(), 'off' ) );
	}

	/**
	 * Release a claim once its apply has finished.
	 *
	 * @param string $lock The claim's option name.
	 *
	 * @return void
	 */
	private static function release( string $lock ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Removes the row the claim inserted directly.
		$wpdb->delete( $wpdb->options, [ 'option_name' => $lock ], [ '%s' ] );
	}

	/**
	 * The refusal of a code that is not known.
	 *
	 * @return string
	 */
	private static function unknown_message(): string {
		return __( 'This confirmation code is not known. Preview the change again to get a new code.', 'gtm-kit' );
	}

	/**
	 * The refusal of a code that was already used.
	 *
	 * @return string
	 */
	private static function used_message(): string {
		return __( 'This confirmation code has already been used. Preview the change again to get a new code.', 'gtm-kit' );
	}

	/**
	 * A refusal of a confirmation code. Nothing was written.
	 *
	 * @param string $message Why, in plain text.
	 *
	 * @return WP_Error
	 */
	private static function refuse( string $message ): WP_Error {
		return new WP_Error( 'gtmkit_confirmation_refused', $message, [ 'status' => 409 ] );
	}

	/**
	 * A setting refused in a preview, with the reason.
	 *
	 * @param string $setting The setting, as `group.key`.
	 * @param string $reason Why, in plain text.
	 *
	 * @return array{setting: string, reason: string}
	 */
	private static function refusal( string $setting, string $reason ): array {
		return [
			'setting' => $setting,
			'reason'  => $reason,
		];
	}

	/**
	 * Hash a confirmation code for storage.
	 *
	 * @param string $token The code.
	 *
	 * @return string
	 */
	private static function hash( string $token ): string {
		return hash( 'sha256', $token );
	}

	/**
	 * A hash of the settings as they are stored.
	 *
	 * @return string
	 */
	private function options_hash(): string {
		return hash( 'sha256', (string) maybe_serialize( $this->options->get_persisted() ) );
	}
}
