/*WordPress*/
import { __ } from '@wordpress/i18n';
import { useRef, useState } from '@wordpress/element';

/*Internal*/
import SettingsService from '../../services/SettingsService';
import { sendReviewAskAction } from '../../api/settings';

const BTN_PRIMARY =
	'gtmkit-inline-flex gtmkit-items-center gtmkit-gap-1 gtmkit-rounded-sm gtmkit-bg-brand-primary gtmkit-px-4 gtmkit-py-[9px] gtmkit-text-[13px] gtmkit-font-medium gtmkit-text-white hover:gtmkit-opacity-90';
const BTN_SECONDARY =
	'gtmkit-inline-flex gtmkit-items-center gtmkit-gap-1 gtmkit-rounded-sm gtmkit-border gtmkit-border-border-default gtmkit-bg-white gtmkit-px-3 gtmkit-py-1.5 gtmkit-text-[13px] gtmkit-font-medium gtmkit-text-text-primary hover:gtmkit-bg-brand-surface-subtle';

/**
 * A one-time request to review GTM Kit on WordPress.org.
 *
 * The server decides when the site has earned it. Whatever the owner
 * chooses, the request hides at once and the choice is stored on the site;
 * a failed save only means it may be shown again later.
 *
 * @return {Element|null} The request, or nothing.
 */
const ReviewAsk = () => {
	const ask = SettingsService.getReviewAsk();
	const [ hidden, setHidden ] = useState( false );
	const region = useRef( null );

	if ( ! ask || hidden ) {
		return null;
	}

	const answer = ( action ) => {
		// The box is about to disappear with focus inside it, so hand focus
		// to the page content rather than letting it fall to the document.
		const main = region.current?.closest( 'main' );
		if ( main ) {
			main.setAttribute( 'tabindex', '-1' );
			main.focus();
		}
		setHidden( true );
		sendReviewAskAction( action ).catch( () => {} );
	};

	return (
		<div
			ref={ region }
			role="region"
			aria-label={ __( 'Review GTM Kit', 'gtm-kit' ) }
			className="gtmkit-mb-6 gtmkit-rounded-md gtmkit-border gtmkit-border-border-default gtmkit-bg-white gtmkit-px-5 gtmkit-py-4 gtmkit-text-sm gtmkit-text-text-primary"
		>
			<p className="gtmkit-m-0 gtmkit-leading-[1.5]">
				{ __(
					'GTM Kit has been loading your Google Tag Manager container for two weeks. If it has saved you time, a short review on WordPress.org helps other site owners find it.',
					'gtm-kit'
				) }
			</p>
			<div className="gtmkit-mt-3 gtmkit-flex gtmkit-flex-wrap gtmkit-items-center gtmkit-gap-2">
				<a
					href={ ask.url }
					target="_blank"
					rel="noopener noreferrer"
					className={ BTN_PRIMARY }
					onClick={ () => answer( 'reviewed' ) }
				>
					{ __( 'Write a review', 'gtm-kit' ) }
					<span className="screen-reader-text">
						{ __( '(opens in a new tab)', 'gtm-kit' ) }
					</span>
				</a>
				<button
					type="button"
					className={ BTN_SECONDARY }
					onClick={ () => answer( 'snooze' ) }
				>
					{ __( 'Not now', 'gtm-kit' ) }
				</button>
				<button
					type="button"
					className={ BTN_SECONDARY }
					onClick={ () => answer( 'dismiss' ) }
				>
					{ __( "Don't ask again", 'gtm-kit' ) }
				</button>
			</div>
		</div>
	);
};

export default ReviewAsk;
