<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin\Introductions\Infrastructure;

use TLA_Media\GTM_Kit\Admin\Introductions\Application\Remote_Introduction;
use TLA_Media\GTM_Kit\Admin\Introductions\Domain\Introduction_Interface;
use TLA_Media\GTM_Kit\Common\Util;

/**
 * Fetches and validates remote introductions from the GTM Kit app endpoint. Reuses the 12h
 * transient pattern in `Util::get_data()` so a site does not call the endpoint on every admin
 * page load.
 *
 * The endpoint is responsible for audience targeting; this source trusts the response shape and
 * validates the block schema only. Anything that does not match the schema is dropped with a
 * debug log line so a misbehaving endpoint cannot break the admin page.
 */
final class Remote_Introductions_Source {

	/**
	 * REST endpoint path on the GTM Kit app, relative to the API host.
	 *
	 * @var string
	 */
	public const ENDPOINT = '/get-introductions';

	/**
	 * Transient key used to cache the response for 12 hours.
	 *
	 * @var string
	 */
	public const TRANSIENT = 'gtmkit_introductions';

	/**
	 * Block types accepted by the v1 schema. Anything outside this set is dropped during
	 * validation.
	 *
	 * @var string[]
	 */
	private const VALID_BLOCK_TYPES = [ 'heading', 'body', 'image', 'video', 'cta' ];

	/**
	 * Valid CTA variants.
	 *
	 * @var string[]
	 */
	private const VALID_CTA_VARIANTS = [ 'primary', 'secondary', 'dismiss' ];

	/**
	 * Utilities used to make the cached remote call.
	 *
	 * @var Util
	 */
	private Util $util;

	/**
	 * Constructor.
	 *
	 * @param Util $util The Util instance.
	 */
	public function __construct( Util $util ) {
		$this->util = $util;
	}

	/**
	 * Hook the source onto the `gtmkit_introductions` filter so remote intros merge with the
	 * bundled and sibling-plugin intros in the collector.
	 *
	 * @return void
	 */
	public function register(): void {
		\add_filter( 'gtmkit_introductions', [ $this, 'add_remote_intros' ] );
	}

	/**
	 * Filter callback: append validated remote intros to the list.
	 *
	 * @param mixed $intros The current list of registered intros.
	 *
	 * @return Introduction_Interface[]
	 */
	public function add_remote_intros( $intros ): array {
		$list = is_array( $intros ) ? array_values( $intros ) : [];
		foreach ( $this->get() as $remote ) {
			$list[] = $remote;
		}
		return $list;
	}

	/**
	 * Fetch the remote intros, validate, and return as Introduction_Interface instances.
	 *
	 * @return Introduction_Interface[]
	 */
	public function get(): array {
		// Send the running plugin version so the endpoint can apply per-intro min/max version gates.
		$data = $this->util->get_data(
			self::ENDPOINT,
			self::TRANSIENT,
			[ 'version' => GTMKIT_VERSION ]
		);

		if ( empty( $data ) ) {
			return [];
		}

		$items = [];
		foreach ( $data as $entry ) {
			$intro = $this->validate_intro( $entry );
			if ( $intro instanceof Introduction_Interface ) {
				$items[] = $intro;
			}
		}
		return $items;
	}

	/**
	 * Validate a single response item and return a Remote_Introduction if it conforms. Returns
	 * null otherwise so the caller can skip it.
	 *
	 * @param mixed $entry The decoded response entry.
	 *
	 * @return Remote_Introduction|null
	 */
	private function validate_intro( $entry ): ?Remote_Introduction {
		if ( ! is_array( $entry ) ) {
			$this->log_dropped( 'entry is not an object', $entry );
			return null;
		}

		$id = isset( $entry['id'] ) && is_string( $entry['id'] ) ? $entry['id'] : '';
		if ( $id === '' ) {
			$this->log_dropped( 'missing id', $entry );
			return null;
		}

		$priority = isset( $entry['priority'] ) && is_numeric( $entry['priority'] ) ? (int) $entry['priority'] : 500;

		$blocks_raw = isset( $entry['blocks'] ) && is_array( $entry['blocks'] ) ? $entry['blocks'] : [];
		$blocks     = [];
		foreach ( $blocks_raw as $block ) {
			$validated = $this->validate_block( $block );
			if ( $validated !== null ) {
				$blocks[] = $validated;
			}
		}

		if ( $blocks === [] ) {
			$this->log_dropped( 'no valid blocks for id "' . $id . '"', $entry );
			return null;
		}

		return new Remote_Introduction( $id, $priority, $blocks );
	}

	/**
	 * Validate a single block against the v1 schema.
	 *
	 * @param mixed $block The decoded block.
	 *
	 * @return array<string, mixed>|null The validated block or null.
	 */
	private function validate_block( $block ): ?array {
		if ( ! is_array( $block ) ) {
			return null;
		}
		$type = isset( $block['type'] ) && is_string( $block['type'] ) ? $block['type'] : '';
		if ( ! in_array( $type, self::VALID_BLOCK_TYPES, true ) ) {
			return null;
		}

		switch ( $type ) {
			case 'heading':
				$text = isset( $block['text'] ) && is_string( $block['text'] ) ? $block['text'] : '';
				if ( $text === '' ) {
					return null;
				}
				return [
					'type' => 'heading',
					'text' => $text,
				];

			case 'body':
				$paragraphs = isset( $block['paragraphs'] ) && is_array( $block['paragraphs'] ) ? $block['paragraphs'] : [];
				$paragraphs = array_values(
					array_filter(
						$paragraphs,
						static function ( $p ): bool {
							return is_string( $p ) && $p !== '';
						}
					)
				);
				if ( $paragraphs === [] ) {
					return null;
				}
				return [
					'type'       => 'body',
					'paragraphs' => $paragraphs,
				];

			case 'image':
				$url = isset( $block['url'] ) && is_string( $block['url'] ) ? \esc_url_raw( $block['url'] ) : '';
				$alt = isset( $block['alt'] ) && is_string( $block['alt'] ) ? $block['alt'] : '';
				if ( $url === '' || $alt === '' ) {
					return null;
				}
				return [
					'type'   => 'image',
					'url'    => $url,
					'alt'    => $alt,
					'width'  => isset( $block['width'] ) && is_numeric( $block['width'] ) ? (int) $block['width'] : null,
					'height' => isset( $block['height'] ) && is_numeric( $block['height'] ) ? (int) $block['height'] : null,
				];

			case 'video':
				$provider = isset( $block['provider'] ) && is_string( $block['provider'] ) ? $block['provider'] : '';
				$video_id = isset( $block['id'] ) && is_string( $block['id'] ) ? $block['id'] : '';
				if ( $provider !== 'youtube' || $video_id === '' ) {
					return null;
				}
				return [
					'type'     => 'video',
					'provider' => $provider,
					'id'       => $video_id,
				];

			case 'cta':
				$variant = isset( $block['variant'] ) && is_string( $block['variant'] ) ? $block['variant'] : '';
				if ( ! in_array( $variant, self::VALID_CTA_VARIANTS, true ) ) {
					return null;
				}
				$label = isset( $block['label'] ) && is_string( $block['label'] ) ? $block['label'] : '';
				if ( $label === '' ) {
					return null;
				}
				$cta = [
					'type'    => 'cta',
					'label'   => $label,
					'variant' => $variant,
				];
				if ( $variant !== 'dismiss' ) {
					$url = isset( $block['url'] ) && is_string( $block['url'] ) ? \esc_url_raw( $block['url'] ) : '';
					if ( $url === '' ) {
						return null;
					}
					$cta['url'] = $url;
				}
				return $cta;
		}
	}

	/**
	 * Log a dropped entry when WP_DEBUG is on. No telemetry leaves the site; this is a
	 * developer-only aid.
	 *
	 * @param string $reason Short human-readable reason.
	 * @param mixed  $entry The dropped entry, for context.
	 *
	 * @return void
	 */
	private function log_dropped( string $reason, $entry ): void {
		if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return;
		}
		$context = is_array( $entry ) ? \wp_json_encode( $entry ) : (string) $entry;
		\error_log( '[gtm-kit] Remote_Introductions_Source dropped entry (' . $reason . '): ' . $context ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Developer-only debug surface, gated by WP_DEBUG.
	}
}
