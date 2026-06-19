/**
 * Skeleton Loading Components
 *
 * Provides content-aware loading states for better perceived performance.
 * Skeletons match the layout of actual content to prevent layout shifts.
 *
 * @since Phase 3 Optimization (2026-01-27)
 */

import { memo } from '@wordpress/element';
import classNames from 'classnames';

/**
 * Base Skeleton component
 *
 * Renders an animated skeleton placeholder.
 *
 * @param {Object}  props           Component props
 * @param {string}  props.className Additional CSS classes
 * @param {string}  props.width     Width (CSS value)
 * @param {string}  props.height    Height (CSS value)
 * @param {boolean} props.circle    Render as circle
 * @return {JSX.Element} Skeleton element
 */
export const Skeleton = memo(
	( { className = '', width, height, circle = false } ) => {
		const baseClasses =
			'gtmkit-animate-pulse gtmkit-bg-color-border gtmkit-rounded';
		const circleClass = circle ? 'gtmkit-rounded-full' : '';

		const style = {
			width: width || '100%',
			height: height || '1rem',
		};

		return (
			<div
				className={ classNames( baseClasses, circleClass, className ) }
				style={ style }
				aria-hidden="true"
			/>
		);
	}
);

/**
 * Text Skeleton
 *
 * Mimics text content with multiple lines.
 *
 * @param {Object} props       Component props
 * @param {number} props.lines Number of lines to show
 * @return {JSX.Element} Text skeleton
 */
export const SkeletonText = memo( ( { lines = 3 } ) => {
	return (
		<div className="gtmkit-space-y-2">
			{ Array.from( { length: lines }, ( _, i ) => (
				<Skeleton
					key={ i }
					height="1rem"
					width={ i === lines - 1 ? '70%' : '100%' }
				/>
			) ) }
		</div>
	);
} );

/**
 * Section Box Skeleton
 *
 * Mimics a SectionBox with header and content.
 *
 * @return {JSX.Element} Section skeleton
 */
export const SkeletonSection = memo( () => {
	return (
		<div className="gtmkit-mb-12 gtmkit-border gtmkit-bg-white gtmkit-max-w-screen-lg gtmkit-border-color-grey gtmkit-rounded">
			<div className="gtmkit-px-8 gtmkit-py-4 gtmkit-border-b gtmkit-border-color-grey">
				<Skeleton height="1.5rem" width="200px" />
			</div>
			<div className="gtmkit-px-8 gtmkit-py-6 gtmkit-space-y-4">
				<SkeletonText lines={ 2 } />
				<Skeleton height="2.5rem" width="300px" />
			</div>
		</div>
	);
} );

/**
 * Settings Field Skeleton
 *
 * Mimics a settings field with label and input.
 *
 * @return {JSX.Element} Field skeleton
 */
export const SkeletonField = memo( () => {
	return (
		<div className="gtmkit-settings-field-wrap gtmkit-py-4">
			<Skeleton height="1.25rem" width="150px" className="gtmkit-mb-2" />
			<Skeleton height="2.5rem" width="100%" />
		</div>
	);
} );

/**
 * Dashboard Box Skeleton
 *
 * Mimics a dashboard box with header, content, and button.
 *
 * @return {JSX.Element} Dashboard box skeleton
 */
export const SkeletonDashboardBox = memo( () => {
	return (
		<div className="gtmkit-flex gtmkit-flex-col gtmkit-min-h-[128px] gtmkit-bg-white gtmkit-border gtmkit-border-color-border gtmkit-mb-6 gtmkit-py-4 gtmkit-px-5 gtmkit-rounded">
			<div className="gtmkit-flex gtmkit-justify-between gtmkit-items-center gtmkit-mb-5">
				<Skeleton height="2rem" width="200px" />
				<Skeleton height="1.5rem" width="60px" circle={ false } />
			</div>
			<div className="gtmkit-flex-auto gtmkit-mb-4">
				<SkeletonText lines={ 2 } />
			</div>
			<Skeleton height="3rem" width="225px" />
		</div>
	);
} );

/**
 * Page Loading Skeleton
 *
 * Full page skeleton with multiple sections.
 * Includes page title skeleton to prevent layout shift.
 *
 * @param {Object}  props           Component props
 * @param {number}  props.sections  Number of sections to show
 * @param {boolean} props.showTitle Whether to show title skeleton (default: true)
 * @return {JSX.Element} Page skeleton
 */
export const SkeletonPage = memo( ( { sections = 3, showTitle = true } ) => {
	return (
		<div>
			{ showTitle && (
				<div
					className="gtmkit-mb-8"
					style={ { height: '2rem' } }
					aria-hidden="true"
				/>
			) }
			{ Array.from( { length: sections }, ( _, i ) => (
				<SkeletonSection key={ i } />
			) ) }
		</div>
	);
} );

/**
 * Notification List Skeleton
 *
 * Mimics notification items.
 *
 * @param {Object} props       Component props
 * @param {number} props.count Number of items to show
 * @return {JSX.Element} Notification skeleton
 */
export const SkeletonNotifications = memo( ( { count = 3 } ) => {
	return (
		<div className="gtmkit-space-y-4">
			{ Array.from( { length: count }, ( _, i ) => (
				<div
					key={ i }
					className="gtmkit-flex gtmkit-items-center gtmkit-justify-between gtmkit-border-2 gtmkit-px-4 gtmkit-py-3"
				>
					<div className="gtmkit-flex-1">
						<Skeleton height="1.25rem" width="150px" />
						<Skeleton
							height="1rem"
							width="80%"
							className="gtmkit-mt-2"
						/>
					</div>
					<Skeleton height="2rem" width="80px" />
				</div>
			) ) }
		</div>
	);
} );

export default Skeleton;
