<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin\Introductions\Infrastructure;

/**
 * Per-user seen-state for introductions, persisted in user meta.
 *
 * Each entry is keyed by intro id and stores whether the user has seen the intro plus a unix
 * timestamp of when it was first marked seen.
 */
final class Introductions_Seen_Repository {

	/**
	 * User-meta key used to store the seen map for a single user.
	 *
	 * @var string
	 */
	public const META_KEY = '_gtmkit_introductions';

	/**
	 * Whether the user has seen the given introduction.
	 *
	 * @param int    $user_id The user id.
	 * @param string $intro_id The introduction id.
	 *
	 * @return bool
	 */
	public function is_seen( int $user_id, string $intro_id ): bool {
		$all = $this->get_all( $user_id );
		return isset( $all[ $intro_id ]['is_seen'] ) && (bool) $all[ $intro_id ]['is_seen'];
	}

	/**
	 * Mark an introduction as seen (or explicitly unseen) for the user. Returns false on save
	 * failure, true otherwise.
	 *
	 * @param int    $user_id The user id.
	 * @param string $intro_id The introduction id.
	 * @param bool   $seen Whether to set seen (true) or clear seen (false).
	 *
	 * @return bool
	 */
	public function mark_seen( int $user_id, string $intro_id, bool $seen = true ): bool {
		if ( $user_id <= 0 || $intro_id === '' ) {
			return false;
		}

		$all              = $this->get_all( $user_id );
		$all[ $intro_id ] = [
			'is_seen' => $seen,
			'seen_on' => time(),
		];

		$result = \update_user_meta( $user_id, self::META_KEY, $all );
		return $result !== false;
	}

	/**
	 * Return the entire seen map for the user.
	 *
	 * @param int $user_id The user id.
	 *
	 * @return array<string, array{is_seen: bool, seen_on: int}>
	 */
	public function get_all( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return [];
		}

		$meta = \get_user_meta( $user_id, self::META_KEY, true );
		if ( ! is_array( $meta ) ) {
			return [];
		}

		$normalised = [];
		foreach ( $meta as $id => $row ) {
			if ( ! is_string( $id ) || ! is_array( $row ) ) {
				continue;
			}
			$normalised[ $id ] = [
				'is_seen' => isset( $row['is_seen'] ) && (bool) $row['is_seen'],
				'seen_on' => isset( $row['seen_on'] ) ? (int) $row['seen_on'] : 0,
			];
		}
		return $normalised;
	}
}
