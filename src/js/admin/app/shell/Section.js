/*WordPress*/
import { useContext, memo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/*Inbuilt Components*/
import Card from './Card';
import FieldRow from './FieldRow';
import ContentBlock from './ContentBlock';

/*Registry*/
import { getSectionItems } from '../../registry/assemble';
import { LAYOUTS } from '../../registry/capabilities';
import { evaluateCondition } from '../../registry/conditions';
import {
	fieldMatchesFilter,
	isIntegrationPluginActive,
} from '../../registry/filtering';
import { SettingsDataContext } from '../../context/SettingsDataContext';

const BADGE_CLASS =
	'gtmkit-text-[11px] gtmkit-font-medium gtmkit-px-2 gtmkit-py-0.5 gtmkit-rounded-sm';

/**
 * Render an item that is either a field (resolved via the control map) or an
 * inline content block (resolved via the block map).
 *
 * @param {Object} props      Component props.
 * @param {Object} props.item The composed section item.
 * @return {JSX.Element} The rendered item.
 */
const SectionItem = ( { item } ) =>
	item.isBlock ? (
		<ContentBlock block={ item } />
	) : (
		<FieldRow field={ item } />
	);

/**
 * The badge shown in an aside, if any. Most help asides carry no badge;
 * promotional asides are tagged "Promotion" and warning callouts "Warning".
 *
 * @param {Array} aside The aside blocks.
 * @return {Object|null} `{ label, className }`, or null.
 */
const asideBadge = ( aside ) => {
	if ( aside.some( ( block ) => block.type === 'promo' ) ) {
		return {
			label: __( 'Promotion', 'gtm-kit' ),
			className:
				'gtmkit-bg-integration-woo-bg gtmkit-text-integration-woo',
		};
	}

	if (
		aside.some(
			( block ) => block.type === 'callout' && block.variant === 'warning'
		)
	) {
		return {
			label: __( 'Warning', 'gtm-kit' ),
			className: 'gtmkit-bg-[#fcf3d6] gtmkit-text-[#8a6d00]',
		};
	}

	return null;
};

/**
 * The right-column Help card for two-column sections: the aside content blocks,
 * with a category badge only on promotional or warning asides.
 *
 * @param {Object} props       Component props.
 * @param {Array}  props.aside The aside blocks.
 * @return {JSX.Element} The Help card.
 */
const HelpCard = ( { aside } ) => {
	const badge = asideBadge( aside );

	return (
		<aside className="gtmkit-relative gtmkit-w-[340px] gtmkit-shrink-0 gtmkit-self-start gtmkit-rounded-lg gtmkit-border gtmkit-border-border-default gtmkit-bg-white gtmkit-p-4">
			{ badge && (
				<span
					className={ `gtmkit-absolute gtmkit-right-4 gtmkit-top-4 ${ BADGE_CLASS } ${ badge.className }` }
				>
					{ badge.label }
				</span>
			) }
			<div
				className={ `gtmkit-space-y-2.5${
					badge ? ' gtmkit-mt-[5px]' : ''
				}` }
			>
				{ aside.map( ( block, i ) => (
					<ContentBlock key={ `aside-${ i }` } block={ block } />
				) ) }
			</div>
		</aside>
	);
};

/**
 * Render a section as a card. Single-layout sections stack their items; two
 * column sections place items in the left column and the aside blocks in a
 * bordered help panel on the right, mirroring the bespoke pages.
 *
 * @param {Object} props         Component props.
 * @param {Object} props.section The section definition.
 * @return {JSX.Element} The rendered card.
 */
const Section = memo( ( { section, filter = null } ) => {
	const { settings } = useContext( SettingsDataContext );
	const { items, aside } = getSectionItems( section.capability, section.id );

	const disabled = section.disabledWhen
		? evaluateCondition( section.disabledWhen, settings )
		: false;

	// Drop fields whose integration plugin is inactive, then (under the filter)
	// fields tagged with a different integration; blocks and universal fields
	// always stay.
	const visibleItems = items.filter(
		( item ) =>
			item.isBlock ||
			( isIntegrationPluginActive( item.integration ) &&
				fieldMatchesFilter( item, filter ) )
	);

	const main = visibleItems.map( ( item, i ) => (
		<SectionItem key={ item.key || `block-${ i }` } item={ item } />
	) );

	if (
		section.layout === LAYOUTS.TWO_COLUMN &&
		Array.isArray( aside ) &&
		aside.length > 0
	) {
		return (
			<Card title={ section.label } disabled={ disabled }>
				<div className="gtmkit-flex gtmkit-gap-6">
					<div className="gtmkit-flex gtmkit-min-w-0 gtmkit-flex-1 gtmkit-flex-col gtmkit-gap-6">
						{ main }
					</div>
					<HelpCard aside={ aside } />
				</div>
			</Card>
		);
	}

	return (
		<Card title={ section.label } disabled={ disabled }>
			<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-6">
				{ main }
			</div>
		</Card>
	);
} );

export default Section;
