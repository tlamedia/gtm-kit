/*WordPress*/
import { memo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/*Inbuilt Components*/
import SaveBtn from '../atoms/save-btn';
import FilterChips from './FilterChips';

/**
 * Sticky top bar: settings search and the integration filter on the left, the
 * save action on the right.
 *
 * @param {Object}   props          Component props.
 * @param {string}   props.query    Current search query.
 * @param {Function} props.onSearch Search query change handler.
 * @return {JSX.Element} The top bar.
 */
const TopBar = memo( ( { query, onSearch } ) => (
	<header className="gtmkit-sticky gtmkit-top-8 gtmkit-z-10 gtmkit-flex gtmkit-h-16 gtmkit-items-center gtmkit-justify-between gtmkit-border-b gtmkit-border-border-default gtmkit-bg-surface gtmkit-px-8">
		<div className="gtmkit-flex gtmkit-items-center gtmkit-gap-5">
			<input
				type="search"
				value={ query }
				onChange={ ( e ) => onSearch( e.target.value ) }
				placeholder={ __( 'Search all settings…', 'gtm-kit' ) }
				aria-label={ __( 'Search all settings', 'gtm-kit' ) }
				className="gtmkit-h-9 gtmkit-w-[300px] gtmkit-rounded-sm gtmkit-border gtmkit-border-border-default gtmkit-bg-page gtmkit-px-3 gtmkit-text-sm gtmkit-text-text-primary focus:gtmkit-border-brand-primary focus:gtmkit-outline-none"
			/>
			<FilterChips />
		</div>
		<SaveBtn
			title={ __( 'Save changes', 'gtm-kit' ) }
			className="gtmkit-rounded-sm !gtmkit-py-2 !gtmkit-px-4 gtmkit-text-sm disabled:!gtmkit-bg-color-button-disabled disabled:!gtmkit-text-color-grey"
		/>
	</header>
) );

export default TopBar;
