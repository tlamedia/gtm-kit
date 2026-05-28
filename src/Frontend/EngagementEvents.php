<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Frontend;

use TLA_Media\GTM_Kit\Options\Options;
use WP_Query;
use WP_User;

/**
 * GA4 engagement events.
 *
 * Emits the four GA4 standard engagement events: `login`, `sign_up`,
 * `search`, and `generate_lead`. The server-triggered events
 * (`login`, `sign_up`) hand off via a short-lived cookie so the
 * decision stays out of cached HTML; the search event is detected in
 * PHP but emitted from JS so the cache key (which already varies on
 * the query string) is the only per-request state involved. The
 * `generate_lead` event ships from the existing CF7 integration's
 * client-side hook.
 */
class EngagementEvents {

	/**
	 * Cookie name used to hand off server-triggered events to the
	 * frontend JS module. JS-readable, host-only, short-lived. See
	 * {@see self::write_cookie()} for the full attribute contract.
	 *
	 * @var string
	 */
	public const COOKIE_NAME = 'gtmkit_engagement_event';

	/**
	 * Cookie max-age in seconds. Long enough to survive redirects and
	 * the WooCommerce "Order received" detour, short enough to avoid
	 * stale events from earlier sessions.
	 *
	 * @var int
	 */
	public const COOKIE_MAX_AGE = 300;

	/**
	 * Optional cookie writer. When null, the module writes through
	 * PHP's `setcookie()`. Tests substitute a callable so no real
	 * cookies are written during the suite.
	 *
	 * @var callable|null
	 */
	private $cookie_writer;

	/**
	 * Construct.
	 *
	 * `Options` is required by {@see self::register()} to decide which
	 * hooks to attach but is not used directly by the instance methods,
	 * which is why the constructor only stores the cookie writer. The
	 * `Options` argument is kept on the constructor signature so the
	 * register-vs-construct API mirrors the rest of the Frontend
	 * namespace and reads consistently for future maintainers.
	 *
	 * @param Options       $options       An instance of Options (used by `register()`; retained for API symmetry).
	 * @param callable|null $cookie_writer Optional cookie writer used by tests. Signature: `fn( string $name, string $value, array $attributes ): void`.
	 */
	public function __construct( Options $options, ?callable $cookie_writer = null ) {
		unset( $options );
		$this->cookie_writer = $cookie_writer;
	}

	/**
	 * Register the module's hooks.
	 *
	 * Per-event toggles gate hook registration so disabled events do
	 * not even add a no-op handler; `did_action()` stays clean for
	 * customers debugging GTM containers.
	 *
	 * @param Options       $options       An instance of Options.
	 * @param callable|null $cookie_writer Optional cookie writer used by tests.
	 * @return self
	 */
	public static function register( Options $options, ?callable $cookie_writer = null ): self {
		$instance = new self( $options, $cookie_writer );

		if ( $options->get( 'general', 'engagement_event_login_enabled' ) ) {
			add_action( 'wp_login', [ $instance, 'on_login' ], 10, 2 );
		}

		if ( $options->get( 'general', 'engagement_event_signup_enabled' ) ) {
			add_action( 'user_register', [ $instance, 'on_user_register' ], 10, 1 );
			add_action( 'woocommerce_created_customer', [ $instance, 'on_woocommerce_created_customer' ], 99, 1 );
		}

		if ( $options->get( 'general', 'engagement_event_search_enabled' ) ) {
			add_filter( 'body_class', [ $instance, 'filter_body_class' ], 10, 1 );
			// Priority 1 so the marker lands before any integration that
			// adds content via `wp_body_open` and reads `document.body`.
			add_action( 'wp_body_open', [ $instance, 'echo_search_term_marker' ], 1 );
		}

		return $instance;
	}

	/**
	 * Handle `wp_login` and queue a `login` event.
	 *
	 * @param string  $user_login The login name of the user (unused, signature requirement).
	 * @param WP_User $user       The logged-in user.
	 */
	public function on_login( string $user_login, WP_User $user ): void {
		unset( $user_login );

		/**
		 * Method label for the `login` event. Defaults to `wordpress`
		 * for every login path (WP core, Woo My Account, REST). Sites
		 * with social-login plugins can override per provider.
		 *
		 * @param string  $method Default method label.
		 * @param WP_User $user   The logged-in user.
		 */
		// phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- GA4 method labels are conventionally lowercase.
		$method = (string) apply_filters( 'gtmkit_engagement_event_login_method', 'wordpress', $user );

		$this->maybe_write_event(
			'login',
			[
				'event'  => 'login',
				'method' => $method,
			]
		);
	}

	/**
	 * Handle `user_register` and queue a `sign_up` event.
	 *
	 * Suppresses emission when the registration originates from an
	 * admin context (bulk import, programmatic user creation in the
	 * dashboard). The late `woocommerce_created_customer` hook
	 * upgrades the method to `woocommerce` when the registration was
	 * part of a Woo checkout or My Account flow.
	 *
	 * @param int $user_id Newly created user ID.
	 */
	public function on_user_register( int $user_id ): void {
		if ( is_admin() ) {
			return;
		}

		/**
		 * Method label for the `sign_up` event from the WordPress
		 * core hook. The Woo-specific hook upgrades this to
		 * `woocommerce` after the fact.
		 *
		 * @param string $method  Default method label.
		 * @param int    $user_id Newly created user ID.
		 */
		// phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- GA4 method labels are conventionally lowercase.
		$method = (string) apply_filters( 'gtmkit_engagement_event_signup_method', 'wordpress', $user_id );

		$this->maybe_write_event(
			'sign_up',
			[
				'event'  => 'sign_up',
				'method' => $method,
			]
		);
	}

	/**
	 * Handle `woocommerce_created_customer` and upgrade the queued
	 * `sign_up` method to `woocommerce`. Runs at priority 99 so it
	 * lands after `user_register`.
	 *
	 * @param int $user_id Newly created customer user ID.
	 */
	public function on_woocommerce_created_customer( int $user_id ): void {
		if ( is_admin() ) {
			return;
		}

		/**
		 * Method label for the `sign_up` event when WooCommerce
		 * created the account. Lets sites distinguish checkout
		 * sign-ups from My Account sign-ups via the user meta.
		 *
		 * @param string $method  Default method label.
		 * @param int    $user_id Newly created customer user ID.
		 */
		$method = (string) apply_filters( 'gtmkit_engagement_event_signup_method', 'woocommerce', $user_id );

		$this->maybe_write_event(
			'sign_up',
			[
				'event'  => 'sign_up',
				'method' => $method,
			]
		);
	}

	/**
	 * Add the `gtmkit-search-results` body class on search results
	 * pages with a non-empty query. The class is the only reliable
	 * frontend signal because some themes manually emit the `<body>`
	 * tag and skip the `body_class()` template tag, but every theme
	 * that wants block compatibility includes the class list.
	 *
	 * Cache-safe: the class is a pure function of the query string,
	 * which the cache already keys on.
	 *
	 * @param array<int, string> $classes Existing body classes.
	 * @return array<int, string>
	 */
	public function filter_body_class( array $classes ): array {
		if ( ! $this->is_search_results_page() ) {
			return $classes;
		}

		if ( '' === $this->current_search_term() ) {
			return $classes;
		}

		$classes[] = 'gtmkit-search-results';

		return $classes;
	}

	/**
	 * Echo a `<script>` setting `document.body.dataset.gtmkitSearchTerm`
	 * on `wp_body_open`. The frontend module reads the dataset rather
	 * than re-parsing the URL so URL-rewriting plugins do not throw
	 * the matcher off.
	 *
	 * Setting via JS (rather than directly on the `<body>` tag) keeps
	 * the surface compatible with themes that strip unknown body
	 * attributes via filter, and avoids needing a custom
	 * `body_attributes` hook that WordPress does not provide.
	 *
	 * Term is JSON-encoded so quotes and special characters survive
	 * intact. Cache-safe: the term is part of the URL query string,
	 * which the cache already keys on.
	 */
	public function echo_search_term_marker(): void {
		if ( ! $this->is_search_results_page() ) {
			return;
		}

		$term = $this->current_search_term();
		if ( '' === $term ) {
			return;
		}

		// JSON_HEX_TAG / _AMP / _APOS / _QUOT escape the four characters
		// that could break out of an inline `<script>` tag, so the
		// encoded value is safe to print without further escaping.
		// Re-escaping with `esc_html()` would corrupt the JSON syntax.
		$encoded = wp_json_encode( $term, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		if ( ! is_string( $encoded ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $encoded is a JSON-encoded string with HTML-special characters escaped via JSON flags above; re-escaping would corrupt the JS literal.
		echo '<script id="gtmkit-engagement-search-term">document.body.dataset.gtmkitSearchTerm=' . $encoded . ';</script>';
	}

	/**
	 * Whether the current request is a search results page.
	 *
	 * Defaults to `is_search()` AND non-empty query. The
	 * `gtmkit_is_search_results_page` filter lets sites with custom
	 * search templates that bypass `is_search()` opt in.
	 *
	 * @return bool
	 */
	public function is_search_results_page(): bool {
		global $wp_query;

		$base = function_exists( 'is_search' ) && is_search();

		/**
		 * Whether the current request should be treated as a search
		 * results page for the purpose of emitting the `search`
		 * engagement event. Useful for headless or block-based
		 * templates that bypass `is_search()`.
		 *
		 * @param bool          $is_search Current decision.
		 * @param WP_Query|null $query     The current main query.
		 */
		return (bool) apply_filters(
			'gtmkit_is_search_results_page',
			$base,
			$wp_query instanceof WP_Query ? $wp_query : null
		);
	}

	/**
	 * Resolve and filter the current search term.
	 *
	 * @return string Trimmed, decoded search term; empty string when
	 *                the query is empty.
	 */
	public function current_search_term(): string {
		$raw = '';
		if ( function_exists( 'get_search_query' ) ) {
			$raw = (string) get_search_query( false );
		}

		// `get_search_query` returns the URL-decoded value already.
		$term = trim( $raw );

		/**
		 * The search term carried by the `search` engagement event.
		 * Runs before the term reaches the frontend so the same value
		 * the JS pushes is the value the filter sees.
		 *
		 * @param string $term Trimmed, URL-decoded search term.
		 */
		$term = (string) apply_filters( 'gtmkit_engagement_event_search_term', $term );

		return trim( $term );
	}

	/**
	 * Write the engagement-event cookie if the final-veto filter
	 * permits and headers have not yet been sent.
	 *
	 * @param string               $event_name Event name (used by filters and logging only).
	 * @param array<string, mixed> $payload    Cookie payload.
	 */
	private function maybe_write_event( string $event_name, array $payload ): void {
		/**
		 * Final veto applied just before the cookie is written. Lets
		 * a site drop any engagement event for any reason without
		 * touching the admin toggles.
		 *
		 * @param bool                  $should_emit Current decision.
		 * @param string                $event_name  Event name.
		 * @param array<string, mixed>  $payload     Event payload that will be written.
		 */
		$should_emit = (bool) apply_filters( 'gtmkit_engagement_event_should_emit', true, $event_name, $payload );
		if ( ! $should_emit ) {
			return;
		}

		// `setcookie()` silently fails after headers have been sent
		// (it warns instead). Bail early so the rest of the request
		// is not contaminated by a stray warning.
		if ( ! $this->headers_already_sent() ) {
			$this->write_cookie( $payload );
		}
	}

	/**
	 * Test seam around PHP's native `headers_sent()`.
	 *
	 * Patchwork (used by the BrainMonkey unit harness) cannot redefine
	 * PHP internals without an opt-in `patchwork.json`. Wrapping the
	 * call in a protected method lets the unit tests override it via a
	 * subclass without needing the JSON file or special test
	 * configuration.
	 *
	 * @return bool
	 */
	protected function headers_already_sent(): bool {
		return headers_sent();
	}

	/**
	 * Write the engagement-event cookie.
	 *
	 * @param array<string, mixed> $payload Payload encoded as JSON.
	 */
	private function write_cookie( array $payload ): void {
		$json = wp_json_encode( $payload );
		if ( ! is_string( $json ) ) {
			return;
		}

		$value = rawurlencode( $json );

		$name = self::cookie_name();

		$attributes = [
			'expires'  => time() + self::COOKIE_MAX_AGE,
			'path'     => self::cookie_path(),
			'domain'   => '',
			'secure'   => is_ssl(),
			'httponly' => false,
			'samesite' => 'Lax',
		];

		if ( null !== $this->cookie_writer ) {
			( $this->cookie_writer )( $name, $value, $attributes );
			return;
		}

		// PHP 7.3+ accepts the array form of `setcookie()`; this
		// project requires PHP 7.4+ so the array form is safe. The `@`
		// suppresses the warning PHP emits if a header sneaks out
		// between {@see self::headers_already_sent()} and this call
		// (extremely rare but cheaper to silence than to introduce a
		// second guard).
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- See block comment above.
		@setcookie( $name, $value, $attributes );
	}

	/**
	 * Filterable cookie name.
	 *
	 * @return string
	 */
	public static function cookie_name(): string {
		/**
		 * Cookie name used to hand off engagement events from the
		 * server to the frontend module. Override when a different
		 * cookie name is required for reverse-proxy or namespacing
		 * reasons.
		 *
		 * @param string $name Default cookie name.
		 */
		$name = (string) apply_filters( 'gtmkit_engagement_event_cookie_name', self::COOKIE_NAME );

		return '' !== $name ? $name : self::COOKIE_NAME;
	}

	/**
	 * Site-relative cookie path so subdirectory installs scope the
	 * cookie correctly.
	 *
	 * @return string
	 */
	public static function cookie_path(): string {
		$path = '/';
		if ( function_exists( 'wp_parse_url' ) ) {
			$parsed = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
			if ( is_string( $parsed ) && '' !== $parsed ) {
				$path = $parsed;
			}
		}

		return $path;
	}
}
