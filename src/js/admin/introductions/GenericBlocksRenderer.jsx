import Body from './blocks/Body.jsx';
import CTA from './blocks/CTA.jsx';
import Heading from './blocks/Heading.jsx';
import Image from './blocks/Image.jsx';
import Video from './blocks/Video.jsx';

/**
 * Render a sequence of validated content blocks inside the modal
 * panel. Unknown block types are dropped silently as a defence in
 * depth on top of the PHP-side validator.
 *
 * CTAs are grouped at the end into a single button row regardless of
 * their position in the blocks list, so the modal's actions always
 * appear together below the content.
 *
 * @param {{
 *   blocks: Array<Record<string, unknown>>,
 *   onDismiss: () => void
 * }} props
 * @return {JSX.Element}
 */
const GenericBlocksRenderer = ( { blocks, onDismiss } ) => {
	if ( ! Array.isArray( blocks ) ) {
		return null;
	}

	const contentBlocks = [];
	const ctaBlocks     = [];

	blocks.forEach( ( block, index ) => {
		if ( ! block || typeof block !== 'object' ) {
			return;
		}
		const node = renderBlock( block, index, onDismiss );
		if ( node === null ) {
			return;
		}
		if ( block.type === 'cta' ) {
			ctaBlocks.push( node );
		} else {
			contentBlocks.push( node );
		}
	} );

	return (
		<div>
			{ contentBlocks }
			{ ctaBlocks.length > 0 && (
				<div className="gtmkit-mt-6 gtmkit-flex gtmkit-gap-2 gtmkit-flex-wrap">
					{ ctaBlocks }
				</div>
			) }
		</div>
	);
};

function renderBlock( block, index, onDismiss ) {
	switch ( block.type ) {
		case 'heading':
			return <Heading key={ index } text={ block.text } />;
		case 'body':
			return <Body key={ index } paragraphs={ block.paragraphs } />;
		case 'image':
			return (
				<Image
					key={ index }
					url={ block.url }
					alt={ block.alt }
					width={ block.width }
					height={ block.height }
				/>
			);
		case 'video':
			return <Video key={ index } provider={ block.provider } id={ block.id } />;
		case 'cta':
			return (
				<CTA
					key={ index }
					label={ block.label }
					url={ block.url }
					variant={ block.variant }
					onDismiss={ onDismiss }
				/>
			);
		default:
			return null;
	}
}

export default GenericBlocksRenderer;
