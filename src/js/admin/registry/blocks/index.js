/**
 * Block-type registry for non-field content.
 *
 * Settings pages are not only fields: they carry help asides, examples,
 * conditional callouts, and bundled promos. Each block declares a `type` that
 * maps to a renderer here, analogous to the control-type registry. Add-ons can
 * extend this map at runtime. Typography matches the design's Help card and
 * inline blocks.
 */
import { memo } from '@wordpress/element';
import { getComponent } from './components';

const HEADING_CLASS =
	'gtmkit-text-[13px] gtmkit-font-semibold gtmkit-text-text-primary gtmkit-m-0';

const PARAGRAPH_CLASS =
	'gtmkit-text-xs gtmkit-text-text-secondary gtmkit-leading-[1.5] gtmkit-m-0';

const LINK_CLASS =
	'gtmkit-text-brand-primary gtmkit-font-medium hover:gtmkit-underline';

/**
 * Render a trailing "Learn more"-style link after a paragraph.
 *
 * @param {Object} link `{ label, href }`.
 * @return {JSX.Element|null} The anchor, or null.
 */
const renderLink = ( link ) =>
	link ? (
		<>
			{ ' ' }
			<a
				href={ link.href }
				className={ LINK_CLASS }
				target="_blank"
				rel="noreferrer"
			>
				{ link.label }
			</a>
		</>
	) : null;

/**
 * Shared renderer for prose-like node lists (used by `prose` and `promo`).
 *
 * @param {Object} props       Component props.
 * @param {Array}  props.nodes Rich nodes: `{ heading?, paragraphs?, code? }`.
 * @return {JSX.Element} The rendered nodes.
 */
const NodeList = ( { nodes = [] } ) => (
	<div className="gtmkit-space-y-2.5">
		{ nodes.map( ( node, i ) => (
			<div key={ i } className="gtmkit-space-y-1.5">
				{ node.heading && (
					<p className={ HEADING_CLASS }>{ node.heading }</p>
				) }
				{ ( node.paragraphs || [] ).map( ( paragraph, j ) => (
					<p key={ j } className={ PARAGRAPH_CLASS }>
						{ paragraph.text }
						{ renderLink( paragraph.link ) }
					</p>
				) ) }
				{ node.code && (
					<p className="gtmkit-m-0">
						<code className="gtmkit-text-xs">{ node.code }</code>
					</p>
				) }
			</div>
		) ) }
	</div>
);

const Prose = memo( ( { block } ) => <NodeList nodes={ block.nodes } /> );

const Promo = memo( ( { block } ) => <NodeList nodes={ block.nodes } /> );

const Examples = memo( ( { block } ) => (
	<div className="gtmkit-space-y-2">
		{ block.heading && (
			<p className={ HEADING_CLASS }>{ block.heading }</p>
		) }
		{ ( block.items || [] ).map( ( item, i ) => (
			<div
				key={ i }
				className="gtmkit-flex gtmkit-items-center gtmkit-gap-2"
			>
				<code className="gtmkit-bg-chip-bg gtmkit-px-1.5 gtmkit-py-0.5 gtmkit-rounded-sm gtmkit-text-[11px] gtmkit-text-text-primary">
					{ item.code }
				</code>
				<span className="gtmkit-text-xs gtmkit-text-text-muted">
					{ item.text }
				</span>
			</div>
		) ) }
	</div>
) );

const DocLinks = memo( ( { block } ) => (
	<div className="gtmkit-space-y-1.5">
		{ ( block.links || [] ).map( ( link, i ) => (
			<a
				key={ i }
				href={ link.href }
				className={ `${ LINK_CLASS } gtmkit-block gtmkit-text-xs` }
				target="_blank"
				rel="noreferrer"
			>
				{ link.label }
			</a>
		) ) }
	</div>
) );

/**
 * Info callout: the blue banner from the design (brand-surface-subtle on
 * info-blue text, no heading).
 *
 * @param {Object} props       Component props.
 * @param {Object} props.block The block definition.
 * @return {JSX.Element} The banner.
 */
const InfoCallout = ( { block } ) => (
	<div className="gtmkit-rounded-md gtmkit-bg-brand-surface-subtle gtmkit-px-3.5 gtmkit-py-3 gtmkit-space-y-1.5">
		{ ( block.paragraphs || [] ).map( ( paragraph, i ) => (
			<p
				key={ i }
				className="gtmkit-text-xs gtmkit-leading-[1.45] gtmkit-text-info gtmkit-m-0"
			>
				{ paragraph.text }
				{ renderLink( paragraph.link ) }
			</p>
		) ) }
	</div>
);

/**
 * Warning / error callout. The design has no banner spec for these yet, so it
 * keeps a heading-led treatment rather than inventing colors.
 *
 * @param {Object} props       Component props.
 * @param {Object} props.block The block definition.
 * @return {JSX.Element} The callout.
 */
const NoticeCallout = ( { block } ) => (
	<div className="gtmkit-space-y-1.5">
		{ block.heading && (
			<p className="gtmkit-text-[13px] gtmkit-font-semibold gtmkit-text-red-700 gtmkit-m-0">
				{ block.heading }
			</p>
		) }
		{ ( block.paragraphs || [] ).map( ( paragraph, i ) => (
			<p
				key={ i }
				className="gtmkit-text-xs gtmkit-leading-[1.5] gtmkit-text-text-secondary gtmkit-m-0"
			>
				{ paragraph.text }
				{ renderLink( paragraph.link ) }
			</p>
		) ) }
	</div>
);

const Callout = memo( ( { block } ) =>
	block.variant === 'info' ? (
		<InfoCallout block={ block } />
	) : (
		<NoticeCallout block={ block } />
	)
);

const Component = memo( ( { block } ) => {
	const Resolved = getComponent( block.component );
	return Resolved ? <Resolved { ...( block.props || {} ) } /> : null;
} );

const BLOCKS = {
	prose: Prose,
	promo: Promo,
	callout: Callout,
	examples: Examples,
	'doc-links': DocLinks,
	component: Component,
};

/**
 * Resolve a block renderer by type.
 *
 * @param {string} type The block's `type`.
 * @return {Function|undefined} The renderer component.
 */
export const getBlock = ( type ) => BLOCKS[ type ];

export default BLOCKS;
