/*WordPress*/
import { memo } from '@wordpress/element';

/**
 * Settings card matching the design: a titled header with a divider above a
 * padded body. Shell-specific so the legacy SectionBox stays untouched.
 *
 * @param {Object}  props            Component props.
 * @param {string}  props.title      Card title.
 * @param {boolean} [props.disabled] Whether the card is dimmed.
 * @param {Object}  props.children   Body content.
 * @return {JSX.Element} The card.
 */
const Card = memo( ( { title, disabled = false, children } ) => (
	<div
		className={ `gtmkit-mb-7 gtmkit-overflow-hidden gtmkit-rounded-[10px] gtmkit-border gtmkit-border-border-default gtmkit-bg-white ${
			disabled ? 'gtmkit-opacity-60' : ''
		}` }
	>
		<div className="gtmkit-border-b gtmkit-border-border-default gtmkit-px-6 gtmkit-pb-4 gtmkit-pt-5">
			<h3 className="gtmkit-m-0 gtmkit-text-[15px] gtmkit-font-semibold gtmkit-text-text-primary">
				{ title }
			</h3>
		</div>
		<div className="gtmkit-px-6 gtmkit-pb-6 gtmkit-pt-5">{ children }</div>
	</div>
) );

export default Card;
