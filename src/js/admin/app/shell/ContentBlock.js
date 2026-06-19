/*WordPress*/
import { useContext, memo } from '@wordpress/element';

/*Registry*/
import { getBlock } from '../../registry/blocks';
import { isVisible } from '../../registry/conditions';
import { SettingsDataContext } from '../../context/SettingsDataContext';

/**
 * Render a single non-field content block, resolving its renderer from the
 * block-type registry and honouring an optional `visibleWhen`. A hidden block
 * renders nothing, so its row padding never leaves an empty gap.
 *
 * @param {Object}  props          Component props.
 * @param {Object}  props.block    The block definition.
 * @param {boolean} [props.padded] Wrap in the field-row vertical padding (for
 *                                 inline section content, not the help aside).
 * @return {JSX.Element|null} The rendered block, or null.
 */
const ContentBlock = memo( ( { block, padded = false } ) => {
	const { settings } = useContext( SettingsDataContext );

	if ( ! isVisible( block, settings ) ) {
		return null;
	}

	const Renderer = getBlock( block.type );
	if ( ! Renderer ) {
		return null;
	}

	const rendered = <Renderer block={ block } />;

	// Component blocks are escape-hatch UI that styles itself and may render
	// nothing (e.g. a plugin-inactive notice when the plugin is active), so
	// they never take the generic row padding that would leave an empty gap.
	return padded && block.type !== 'component' ? (
		<div className="gtmkit-py-3.5">{ rendered }</div>
	) : (
		rendered
	);
} );

export default ContentBlock;
