/**
 * Skeleton Loading Components Type Definitions
 *
 * @since Phase 3 Optimization (2026-01-27)
 */

import React from 'react';

/**
 * Base Skeleton component props
 */
export interface SkeletonProps {
	/** Additional CSS classes */
	className?: string;
	/** Width (CSS value) */
	width?: string;
	/** Height (CSS value) */
	height?: string;
	/** Render as circle */
	circle?: boolean;
}

/**
 * Base Skeleton component
 */
export const Skeleton: React.FC< SkeletonProps >;

/**
 * Text Skeleton props
 */
export interface SkeletonTextProps {
	/** Number of lines to show */
	lines?: number;
}

/**
 * Text Skeleton component
 */
export const SkeletonText: React.FC< SkeletonTextProps >;

/**
 * Section Box Skeleton component
 */
export const SkeletonSection: React.FC;

/**
 * Settings Field Skeleton component
 */
export const SkeletonField: React.FC;

/**
 * Dashboard Box Skeleton component
 */
export const SkeletonDashboardBox: React.FC;

/**
 * Page Loading Skeleton props
 */
export interface SkeletonPageProps {
	/** Number of sections to show */
	sections?: number;
	/** Whether to show title skeleton (default: true) */
	showTitle?: boolean;
}

/**
 * Page Loading Skeleton component
 *
 * Includes page title skeleton to prevent layout shift on route changes.
 */
export const SkeletonPage: React.FC< SkeletonPageProps >;

/**
 * Notification List Skeleton props
 */
export interface SkeletonNotificationsProps {
	/** Number of items to show */
	count?: number;
}

/**
 * Notification List Skeleton component
 */
export const SkeletonNotifications: React.FC< SkeletonNotificationsProps >;

export default Skeleton;
