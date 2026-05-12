<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin\Introductions\Application;

/**
 * Helpers for version-gated introductions.
 *
 * The plugin records `gtmkit_initial_version` once on first activation and never updates it. That
 * gives a stable "the user has been on the plugin since this version" marker that lets a
 * version-bump intro target every upgrader exactly once per intro id, while a fresh install at the
 * new version stays silent.
 */
trait Version_Trait {

	/**
	 * Whether the user has crossed a version threshold: the plugin was first installed below the
	 * threshold and the running version is at or above it.
	 *
	 * @param string $threshold A semver string to compare against, for example '3.0.0'.
	 *
	 * @return bool
	 */
	protected function version_crossed( string $threshold ): bool {
		$initial = $this->get_initial_version();

		if ( $initial === '' ) {
			return false;
		}

		return version_compare( $initial, $threshold, '<' )
			&& version_compare( GTMKIT_VERSION, $threshold, '>=' );
	}

	/**
	 * Whether the plugin was first installed at or above the given version. Useful for
	 * "fresh install only" gating where an upgrader-targeted intro takes the upgrade case.
	 *
	 * @param string $threshold A semver string to compare against.
	 *
	 * @return bool
	 */
	protected function version_installed_at_or_above( string $threshold ): bool {
		$initial = $this->get_initial_version();

		if ( $initial === '' ) {
			return false;
		}

		return version_compare( $initial, $threshold, '>=' );
	}

	/**
	 * Read the stored initial-install version. Returns an empty string when the option is missing
	 * so callers can decide how to treat the unknown-history case (the default is to refuse, not
	 * to assume a fresh install).
	 *
	 * @return string
	 */
	private function get_initial_version(): string {
		return (string) \get_option( 'gtmkit_initial_version', '' );
	}
}
