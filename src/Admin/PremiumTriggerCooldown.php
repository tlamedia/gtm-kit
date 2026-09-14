<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

/**
 * Remembers when a contextual upgrade notice was dismissed, and for how long
 * that dismissal keeps it away.
 *
 * The notifications system tracks dismissals of its own, but only for
 * notifications present in the set it currently holds: one that is never
 * created is never bucketed as dismissed either. These notices are created
 * conditionally, so their own dismissal is exactly the case that bucket cannot
 * answer. The timestamp therefore lives in a small option of its own, and a
 * notice still inside its cooldown is simply never created.
 *
 * At most one entry per notice id is ever stored, so the option stays a handful
 * of integers and needs no pruning. An entry whose cooldown has run out reads
 * as expired, which is the same answer as having no entry at all.
 */
final class PremiumTriggerCooldown {

	/**
	 * The option holding one dismissal timestamp per notice id.
	 *
	 * @var string
	 */
	public const OPTION = 'gtmkit_upgrade_notice_dismissals';

	/**
	 * How long a dismissal keeps its notice away, in days.
	 *
	 * Long enough that dismissing is worth doing, short enough that a site
	 * whose situation has not changed a season later is asked once more.
	 *
	 * @var int
	 */
	public const COOLDOWN_DAYS = 90;

	/**
	 * Record that a notice was dismissed now.
	 *
	 * @param string $notification_id The id of the dismissed notification.
	 *
	 * @return void
	 */
	public static function record( string $notification_id ): void {
		$dismissals = self::get_dismissals();

		$dismissals[ $notification_id ] = \time();

		\update_option( self::OPTION, $dismissals, false );
	}

	/**
	 * Whether a notice was dismissed recently enough to stay away.
	 *
	 * @param string $notification_id The id of the notification to check.
	 *
	 * @return bool True while the notice is still inside its cooldown.
	 */
	public static function is_within_cooldown( string $notification_id ): bool {
		$dismissals = self::get_dismissals();

		if ( ! isset( $dismissals[ $notification_id ] ) ) {
			return false;
		}

		return ( \time() - $dismissals[ $notification_id ] ) < ( self::COOLDOWN_DAYS * DAY_IN_SECONDS );
	}

	/**
	 * Read the stored dismissals.
	 *
	 * Anything the option holds that is not a notice id mapped to a timestamp
	 * is dropped, so a hand-edited or partially written option degrades to "not
	 * dismissed" rather than to a fatal.
	 *
	 * @return array<string, int> Dismissal timestamps keyed by notice id.
	 */
	private static function get_dismissals(): array {
		$stored = \get_option( self::OPTION, [] );

		if ( ! \is_array( $stored ) ) {
			return [];
		}

		$dismissals = [];

		foreach ( $stored as $notification_id => $timestamp ) {
			if ( \is_string( $notification_id ) && \is_numeric( $timestamp ) ) {
				$dismissals[ $notification_id ] = (int) $timestamp;
			}
		}

		return $dismissals;
	}
}
