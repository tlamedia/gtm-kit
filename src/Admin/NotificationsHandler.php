<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

/**
 * Notifications  Handler
 */
final class NotificationsHandler {

	/**
	 * Option name to store notifications.
	 *
	 * @var string
	 */
	public const STORAGE_KEY = 'gtmkit_notifications';

	/**
	 * Singleton instance.
	 *
	 * @var NotificationsHandler|null
	 */
	private static ?NotificationsHandler $instance = null;

	/**
	 * Notifications array.
	 *
	 * @var Notification[][]
	 */
	private array $notifications = [];

	/**
	 * Queued transactions before notifications retrieval.
	 *
	 * @var array<int, array{0: callable, 1: array<int, mixed>}>
	 */
	private array $queued_transactions = [];

	/**
	 * Flag whether notifications have been retrieved.
	 *
	 * @var bool
	 */
	private bool $notifications_retrieved = false;

	/**
	 * Flag whether notifications need to be updated.
	 *
	 * @var bool
	 */
	private bool $notifications_need_storage = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'setup_current_notifications' ], 1 );
		add_action( 'gtmkit_deactivate', [ $this, 'deactivate_hook' ] );
		add_action( 'shutdown', [ $this, 'update_storage' ] );
	}

	/**
	 * Get singleton instance.
	 *
	 * @return NotificationsHandler
	 */
	public static function get(): NotificationsHandler {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Check if the user has dismissed a notification.
	 *
	 * @param Notification $notification The notification to check for dismissal.
	 * @param int|null     $user_id      User ID to check on.
	 *
	 * @return bool
	 */
	public static function is_notification_dismissed( Notification $notification, ?int $user_id = null ): bool {
		$user_id       = self::get_user_id( $user_id );
		$current_value = get_user_option( $notification->get_id(), $user_id );

		return ! empty( $current_value );
	}

	/**
	 * Check if the notification is being dismissed.
	 *
	 * @param Notification $notification Notification to check dismissal of.
	 *
	 * @return bool True if dismissed.
	 */
	public static function maybe_dismiss_notification( Notification $notification ): bool {
		return self::is_notification_dismissed( $notification ) || self::dismiss_notification( $notification );
	}

	/**
	 * Dismiss a notification.
	 *
	 * @param Notification $notification Notification to dismiss.
	 *
	 * @return bool True if dismissed, false otherwise.
	 */
	public static function dismiss_notification( Notification $notification ): bool {
		return update_user_option( get_current_user_id(), $notification->get_id(), 'dismissed' ) !== false;
	}

	/**
	 * Restores a notification.
	 *
	 * @param Notification $notification Notification to restore.
	 *
	 * @return bool True if restored, false otherwise.
	 */
	public static function restore_notification( Notification $notification ): bool {
		return delete_user_option( get_current_user_id(), $notification->get_id() );
	}

	/**
	 * Clear dismissal information for the specified Notification.
	 *
	 * @param string|Notification $notification Notification to clear the dismissal of.
	 *
	 * @return bool True if successfully cleared, false otherwise.
	 */
	public function clear_dismissal( $notification ): bool {
		global $wpdb;

		if ( $notification instanceof Notification ) {
			$dismissal_key = $notification->get_id();
		}

		if ( is_string( $notification ) ) {
			$dismissal_key = $notification;
		}

		if ( empty( $dismissal_key ) ) {
			return false;
		}

		// Remove notification dismissal for all users.
		return delete_metadata( 'user', 0, $wpdb->get_blog_prefix() . $dismissal_key, '', true );
	}

	/**
	 * Set up current notifications.
	 *
	 * Retrieves notifications from the storage and merges in previous notification changes.
	 *
	 * @return void
	 */
	public function setup_current_notifications(): void {
		$this->retrieve_notifications_from_storage( get_current_user_id() );

		foreach ( $this->queued_transactions as $transaction ) {
			list( $callback, $args ) = $transaction;

			call_user_func_array( $callback, $args );
		}

		$this->queued_transactions = [];
	}

	/**
	 * Add notification.
	 *
	 * @param Notification $notification Notification object instance.
	 *
	 * @return void
	 */
	public function add_notification( Notification $notification ): void {
		if ( $this->queue_transaction( [ $this, __FUNCTION__ ], func_get_args() ) ) {
			return;
		}

		if ( ! $notification->display_for_current_user() ) {
			return;
		}

		$notification_id = $notification->get_id();
		$user_id         = $notification->get_user_id();

		if ( $notification_id ) {
			// If notification ID exists in notifications, don't add again.
			$present_notification = $this->get_notification_by_id( $notification_id, $user_id );

			if ( $present_notification ) {
				$this->remove_notification( $present_notification, false );
			}
		}

		$this->notifications[ $user_id ][] = $notification;
		$this->notifications_need_storage  = true;
	}

	/**
	 * Get the notification by ID and user ID.
	 *
	 * @param string   $notification_id Notification ID.
	 * @param int|null $user_id         User ID.
	 *
	 * @return Notification|null
	 */
	public function get_notification_by_id( string $notification_id, ?int $user_id = null ): ?Notification {
		$user_id = self::get_user_id( $user_id );

		$notifications = $this->get_notifications_for_user( $user_id );

		foreach ( $notifications as $notification ) {
			if ( $notification_id === $notification->get_id() ) {
				return $notification;
			}
		}

		return null;
	}

	/**
	 * The default notifications array
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function default_notifications_array(): array {
		return [
			'metrics'             => [
				'total'               => 0,
				Notification::PROBLEM => 0,
				Notification::NOTICE  => 0,
			],
			Notification::PROBLEM => [
				'total'     => 0,
				'active'    => [],
				'dismissed' => [],
			],
			Notification::NOTICE  => [
				'total'     => 0,
				'active'    => [],
				'dismissed' => [],
			],
		];
	}

	/**
	 * Get the notifications for the settings.
	 *
	 * @return array<string, array{total: int, active: array<string>, dismissed: array<string>}|int>
	 */
	public function get_notifications_array(): array {
		$notifications_array = $this->default_notifications_array();
		$notifications       = $this->get_sorted_notifications();

		if ( empty( $notifications ) ) {
			return $notifications_array;
		}
		$notifications = array_unique( $notifications );

		foreach ( $notifications as $notification ) {
			$type   = $notification->get_type();
			$status = $this->is_notification_dismissed( $notification ) ? 'dismissed' : 'active';

			$notifications_array[ $type ][ $status ][] = $notification->render();
			++$notifications_array[ $type ]['total'];

			if ( $status === 'active' ) {
				++$notifications_array['metrics']['total'];
				++$notifications_array['metrics'][ $type ];
			}
		}

		return $notifications_array;
	}

	/**
	 * Remove notification.
	 *
	 * @param Notification $notification Notification to remove.
	 * @param bool         $resolve      Resolve as fixed.
	 *
	 * @return void
	 */
	public function remove_notification( Notification $notification, bool $resolve = true ): void {
		if ( $this->queue_transaction( [ $this, __FUNCTION__ ], func_get_args() ) ) {
			return;
		}

		$index = false;

		// ID of the user to show the notification for, defaults to current user id.
		$user_id       = $notification->get_user_id();
		$notifications = $this->get_notifications_for_user( $user_id );

		foreach ( $notifications as $current_index => $present_notification ) {
			if ( $present_notification->get_id() === $notification->get_id() ) {
				$index = $current_index;
				break;
			}
		}

		if ( $index === false ) {
			return;
		}

		if ( $resolve ) {
			$this->clear_dismissal( $notification );
		}

		unset( $notifications[ $index ] );
		$this->notifications[ $user_id ] = array_values( $notifications );

		$this->notifications_need_storage = true;
	}

	/**
	 * Removes a notification by its ID.
	 *
	 * @param string $notification_id The notification id.
	 * @param bool   $resolve         Resolve as fixed.
	 *
	 * @return void
	 */
	public function remove_notification_by_id( string $notification_id, bool $resolve = true ): void {
		$notification = $this->get_notification_by_id( $notification_id );

		if ( $notification === null ) {
			return;
		}

		$this->remove_notification( $notification, $resolve );
		$this->notifications_need_storage = true;
	}

	/**
	 * Return the notifications sorted on type and priority.
	 *
	 * @return array|Notification[] Sorted Notifications
	 */
	public function get_sorted_notifications(): array {
		$notifications = $this->get_notifications_for_user( get_current_user_id() );

		if ( empty( $notifications ) ) {
			return [];
		}

		// Sort by severity, error first.
		usort( $notifications, [ $this, 'sort_notifications' ] );

		return $notifications;
	}

	/**
	 * Remove storage when the plugin is deactivated.
	 *
	 * @return void
	 */
	public function deactivate_hook(): void {
		$this->clear_notifications();
	}

	/**
	 * Get the user ID
	 *
	 * @param int|null $user_id The user ID to check.
	 *
	 * @return int The user ID to use.
	 */
	private static function get_user_id( ?int $user_id ): int {
		if ( $user_id ) {
			return $user_id;
		}

		return get_current_user_id();
	}

	/**
	 * Splits the notifications on user ID.
	 *
	 * In other terms, it returns an associative array,
	 * mapping user ID to a list of notifications for this user.
	 *
	 * @param array|Notification[] $notifications The notifications to split.
	 *
	 * @return array<int, Notification[]> The notifications, split on user ID.
	 */
	private function split_on_user_id( array $notifications ): array {
		$split_notifications = [];
		foreach ( $notifications as $notification ) {
			$split_notifications[ $notification->get_user_id() ][] = $notification;
		}

		return $split_notifications;
	}

	/**
	 * Save persistent notifications to storage.
	 *
	 * @return void
	 */
	public function update_storage(): void {
		$notifications = $this->notifications;

		/**
		 * One array of Notifications, merged from multiple arrays.
		 *
		 * @var Notification[] $merged_notifications
		 */
		$merged_notifications = [];
		if ( ! empty( $notifications ) ) {
			$merged_notifications = array_merge( ...$notifications );
		}

		$notifications = $this->split_on_user_id( $merged_notifications );

		// No notifications to store, clear storage if it was previously present.
		if ( empty( $notifications ) ) {
			$this->remove_storage();

			return;
		}

		// Only store notifications if changes are made.
		if ( $this->notifications_need_storage ) {
			array_walk( $notifications, [ $this, 'store_notifications_for_user' ] );
		}
	}

	/**
	 * Stores the notifications to its respective user's storage.
	 *
	 * @param array|Notification[] $notifications The notifications to store.
	 * @param int                  $user_id       The ID of the user for which to store the notifications.
	 *
	 * @return void
	 */
	private function store_notifications_for_user( array $notifications, int $user_id ): void {
		$notifications_as_arrays = array_map( [ $this, 'notification_to_array' ], $notifications );
		update_user_option( $user_id, self::STORAGE_KEY, $notifications_as_arrays );
	}

	/**
	 * Provide a way to verify present notifications.
	 *
	 * @return array|Notification[] Registered notifications.
	 */
	public function get_notifications(): array {
		if ( ! $this->notifications ) {
			return [];
		}

		return array_merge( ...$this->notifications );
	}

	/**
	 * Returns the notifications for the given user.
	 *
	 * @param int $user_id The id of the user to check.
	 *
	 * @return Notification[] The notifications for the user with the given ID.
	 */
	public function get_notifications_for_user( int $user_id ): array {
		if ( array_key_exists( $user_id, $this->notifications ) ) {
			return $this->notifications[ $user_id ];
		}

		return [];
	}

	/**
	 * Retrieve the notifications from storage and fill the relevant property.
	 *
	 * @param int $user_id The ID of the user to retrieve notifications for.
	 *
	 * @return void
	 */
	private function retrieve_notifications_from_storage( int $user_id ): void {
		if ( $this->notifications_retrieved ) {
			return;
		}

		$this->notifications_retrieved = true;

		$stored_notifications = get_user_option( self::STORAGE_KEY, $user_id );

		// Check if notifications are stored.
		if ( empty( $stored_notifications ) ) {
			return;
		}

		if ( is_array( $stored_notifications ) ) {
			$notifications = array_map( [ $this, 'array_to_notification' ], $stored_notifications );

			// Apply array_values to ensure we get a 0-indexed array.
			$notifications = array_values( array_filter( $notifications, [ $this, 'filter_notification_current_user' ] ) );

			$this->notifications[ $user_id ] = $notifications;
		}
	}

	/**
	 * Sort on type then priority.
	 *
	 * @param Notification $a Compare with B.
	 * @param Notification $b Compare with A.
	 *
	 * @return int 1, 0 or -1 for sorting offset.
	 */
	private function sort_notifications( Notification $a, Notification $b ): int {
		$a_type = $a->get_type();
		$b_type = $b->get_type();

		if ( $a_type === $b_type ) {
			return ( $b->get_priority() === $a->get_priority() ) ? 0 : ( ( $b->get_priority() > $a->get_priority() ) ? 1 : -1 );
		}

		if ( $a_type === 'error' ) {
			return -1;
		}

		if ( $b_type === 'error' ) {
			return 1;
		}

		return 0;
	}

	/**
	 * Clear local stored notifications.
	 *
	 * @return void
	 */
	private function clear_notifications(): void {
		$this->notifications           = [];
		$this->notifications_retrieved = false;
	}

	/**
	 * Convert Notification to array representation.
	 *
	 * @since 3.2
	 *
	 * @param Notification $notification Notification to convert.
	 *
	 * @return array<string, mixed>
	 */
	private function notification_to_array( Notification $notification ): array {
		return $notification->to_array();
	}

	/**
	 * Convert stored array to Notification.
	 *
	 * @param array<string, mixed> $notification_data Array to convert to Notification.
	 *
	 * @return Notification
	 */
	private function array_to_notification( array $notification_data ): Notification {
		return new Notification(
			$notification_data['message'],
			$notification_data['header'],
			$notification_data['options']
		);
	}

	/**
	 * Filter notifications that should not be displayed for the current user.
	 *
	 * @param Notification $notification Notification to test.
	 *
	 * @return bool
	 */
	private function filter_notification_current_user( Notification $notification ): bool {
		return $notification->display_for_current_user();
	}

	/**
	 * Queues a notification transaction for later execution if notifications are not yet set up.
	 *
	 * @param callable          $callback Callback that performs the transaction.
	 * @param array<int, mixed> $args     Arguments to pass to the callback.
	 *
	 * @return bool True if transaction was queued, false if it can be performed immediately.
	 */
	private function queue_transaction( callable $callback, array $args ): bool {
		if ( $this->notifications_retrieved ) {
			return false;
		}

		$this->add_transaction_to_queue( $callback, $args );

		return true;
	}

	/**
	 * Adds a notification transaction to the queue for later execution.
	 *
	 * @param callable          $callback Callback that performs the transaction.
	 * @param array<int, mixed> $args     Arguments to pass to the callback.
	 *
	 * @return void
	 */
	private function add_transaction_to_queue( callable $callback, array $args ): void {
		$this->queued_transactions[] = [ $callback, $args ];
	}

	/**
	 * Removes all notifications from storage.
	 *
	 * @return bool True when notifications got removed.
	 */
	protected function remove_storage(): bool {
		if ( ! $this->has_stored_notifications() ) {
			return false;
		}

		delete_user_option( get_current_user_id(), self::STORAGE_KEY );

		return true;
	}

	/**
	 * Checks if there are stored notifications.
	 *
	 * @return bool True when there are stored notifications.
	 */
	protected function has_stored_notifications(): bool {
		$stored_notifications = $this->get_stored_notifications();

		return ! empty( $stored_notifications );
	}

	/**
	 * Retrieves the stored notifications.
	 *
	 * @codeCoverageIgnore
	 *
	 * @return array<int, array<int, mixed>>|false Array with notifications or false when not set.
	 */
	protected function get_stored_notifications() {
		return get_user_option( self::STORAGE_KEY, get_current_user_id() );
	}
}
