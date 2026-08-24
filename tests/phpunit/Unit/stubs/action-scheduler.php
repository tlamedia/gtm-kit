<?php
/**
 * Bare `ActionScheduler` stub for the BrainMonkey unit harness.
 *
 * `SnippetScan::schedule_daily_event()` picks its scheduler with
 * `class_exists( 'ActionScheduler' )`, which no test double can intercept.
 * Tests that need that branch require this file from inside an isolated
 * process, so the declaration never leaks into the rest of the suite and
 * flips other `class_exists`-gated code.
 *
 * @package TLA_Media\GTM_Kit
 */

if ( class_exists( 'ActionScheduler' ) ) {
	return;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound, Squiz.Commenting.ClassComment.Missing -- Mirrors Action Scheduler's own class name, which the branch under test keys on. Marker only; the scan never calls into it.
class ActionScheduler {
}
