/**
 * Performance monitoring utilities type definitions
 *
 * @since Phase 3 Optimization (2026-01-27)
 */

/**
 * Performance monitoring wrapper component
 *
 * Wraps components with React Profiler to measure render performance.
 * Logs warnings in development when renders exceed 16ms (1 frame at 60fps).
 */
export const PerformanceMonitor: React.FC<{
	/** Unique identifier for this profiler */
	id: string;
	/** Child components to monitor */
	children: React.ReactNode;
}>;

/**
 * Measure function execution time
 *
 * Wraps a function to log its execution time in development mode.
 * Warns if execution takes longer than 10ms.
 *
 * @param label - Label for the measurement
 * @param fn - Function to measure
 * @returns Wrapped function that logs execution time
 *
 * @example
 * const heavyComputation = measurePerformance('filterNotifications', () => {
 *     return notifications.filter(n => n.type === 'problem');
 * });
 */
export function measurePerformance<T extends (...args: any[]) => any>(
	label: string,
	fn: T
): T;

/**
 * Component render counter for debugging
 *
 * Logs how many times a component has rendered (development only).
 *
 * @param componentName - Name of the component
 *
 * @example
 * const MyComponent = () => {
 *     useRenderCount('MyComponent');
 *     // ... component code
 * };
 */
export function useRenderCount(componentName: string): void;

export default PerformanceMonitor;

/**
 * Global window extensions for performance debugging
 */
declare global {
	interface Window {
		/** Enable verbose performance logging */
		enablePerformanceLogging: () => void;
		/** Disable verbose performance logging */
		disablePerformanceLogging: () => void;
		/** Flag indicating if debug performance logging is enabled */
		GTMKIT_DEBUG_PERFORMANCE?: boolean;
	}
}
