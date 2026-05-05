<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Frontend;

/**
 * Event deferral gate.
 *
 * Wraps the `gtmkit_event_should_defer` filter so the dataLayer push
 * helpers in {@see Frontend} have a single, testable seam through which
 * to ask "should I defer this event?". The default decision is false
 * (events fire); a Premium-only event deferral queue can register on
 * the filter and return true when consent is missing.
 */
final class EventDeferralGate {

	/**
	 * The signal source registry used to read the active consent state.
	 *
	 * @var ConsentSignalSourceRegistry
	 */
	private ConsentSignalSourceRegistry $registry;

	/**
	 * Constructor.
	 *
	 * @param ConsentSignalSourceRegistry $registry The signal source registry.
	 */
	public function __construct( ConsentSignalSourceRegistry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Decide whether to defer an event.
	 *
	 * Reads the current consent state from the active signal source
	 * (or an empty array when no source is active) and runs the
	 * `gtmkit_event_should_defer` filter. Returning true skips the
	 * push at the call site.
	 *
	 * @param string               $event_name    e.g. 'view_item', 'add_to_cart', 'purchase'.
	 * @param array<string, mixed> $event_payload Event data.
	 */
	public function should_defer( string $event_name, array $event_payload ): bool {
		$consent_state = $this->registry->read_state() ?? [];

		/**
		 * Per-event deferral decision. Default: do not defer.
		 *
		 * @param bool                              $should_defer  Default false.
		 * @param string                            $event_name    e.g. 'view_item', 'add_to_cart', 'purchase'.
		 * @param array<string, mixed>              $event_payload Event data.
		 * @param array<string, 'granted'|'denied'> $consent_state Current state from the active signal source.
		 */
		return (bool) apply_filters(
			'gtmkit_event_should_defer',
			false,
			$event_name,
			$event_payload,
			$consent_state
		);
	}
}
