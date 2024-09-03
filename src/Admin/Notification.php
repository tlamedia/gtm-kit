<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

/**
 * Notification.
 */
class Notification {

	/**
	 * Notification type.
	 *
	 * @var string
	 */
	public const PROBLEM = 'problem';

	/**
	 * Notification type.
	 *
	 * @var string
	 */
	public const NOTICE = 'notice';

	/**
	 * Options of this Notification.
	 *
	 * @var array<string, mixed>
	 */
	private array $options;

	/**
	 * The default values for the optional arguments.
	 *
	 * @var array<string, mixed>
	 */
	private array $defaults = [
		'id'       => '',
		'user_id'  => null,
		'type'     => self::NOTICE,
		'priority' => 0.5,
	];

	/**
	 * The header for the notification.
	 *
	 * @var string
	 */
	private string $header;

	/**
	 * The message for the notification.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * Notification class constructor.
	 *
	 * @param string               $message Message string.
	 * @param string               $header The header.
	 * @param array<string, mixed> $options Set of options.
	 */
	public function __construct( string $message, string $header = '', array $options = [] ) {
		$this->header  = $header;
		$this->message = $message;
		$this->options = $this->normalize_options( $options );
	}

	/**
	 * Get the notification ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->options['id'];
	}

	/**
	 * Get the id of the user to show the notification for.
	 *
	 * @return int The user id
	 */
	public function get_user_id(): int {
		return ( $this->options['user_id'] ?? get_current_user_id() );
	}

	/**
	 * Get the type of the notification.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->options['type'];
	}

	/**
	 * Get the priority of the notification.
	 *
	 * @return float Returns the priority between 0 and 1.
	 */
	public function get_priority(): float {
		return $this->options['priority'];
	}

	/**
	 * Check if the notification is relevant for the current user.
	 *
	 * @return bool True if a user needs to see this notification, false if not.
	 */
	public function display_for_current_user(): bool {
		$capability = \apply_filters( 'gtmkit_admin_capability', \is_multisite() ? 'manage_network_options' : 'manage_options' );

		return $this->has_capability( $capability );
	}

	/**
	 * Array filter function to find matched capabilities.
	 *
	 * @param string $capability Capability to test.
	 *
	 * @return bool
	 */
	private function has_capability( string $capability ): bool {
		$user_id = $this->options['user_id'];
		if ( ! is_numeric( $user_id ) ) {
			return false;
		}
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		return $user->has_cap( $capability );
	}

	/**
	 * Return the object properties as an array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'header'  => $this->header,
			'message' => $this->message,
			'options' => $this->options,
		];
	}

	/**
	 * Adds string (view) behaviour to the notification.
	 *
	 * @return string
	 */
	public function __toString(): string {
		return wp_json_encode( $this->render() );
	}

	/**
	 * Renders the notification as an array for use in the settings.
	 *
	 * @return array<string, string> The rendered notification.
	 */
	public function render(): array {
		return [
			'id'      => $this->get_id(),
			'header'  => $this->header,
			'message' => $this->message,
		];
	}

	/**
	 * Make sure we only have values that we can work with.
	 *
	 * @param array<string, mixed> $options Options to normalize.
	 *
	 * @return array<string, mixed>
	 */
	private function normalize_options( array $options ): array {
		$options = wp_parse_args( $options, $this->defaults );

		// The default is 0.5, and it should not exceed 0 or 1.
		$options['priority'] = min( 1, max( 0, $options['priority'] ) );

		if ( $options['user_id'] === null ) {
			$options['user_id'] = get_current_user_id();
		}

		return $options;
	}
}
