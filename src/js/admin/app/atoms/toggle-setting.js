/*WordPress*/
import { BaseControl, ToggleControl } from '@wordpress/components';
import { useId, memo } from '@wordpress/element';

/*Custom Hooks*/
import { useSettingField } from '../../hooks/useSettingField';
import { useNotification } from '../../hooks/useNotification';

/**
 * Toggle setting component
 *
 * Refactored to use custom hooks (Phase 4 Component Improvements)
 * No longer requires prop drilling of context values
 *
 * @since Phase 4 Component Improvements (2026-01-27)
 */
const ToggleSetting = memo(
	( {
		title,
		label,
		optionGroup = 'general',
		optionName,
		disabled = false,
		narrow = false,
		premium = false,
		notificationId = '',
	} ) => {
		const uniqueId = useId();
		const [ value, setValue ] = useSettingField( optionGroup, optionName );
		const { removeNotification } = useNotification();

		const classes =
			'gtmkit-settings-field-wrap ' +
			( narrow ? 'gtmkit-py-2' : 'gtmkit-py-4' );

		return (
			<>
				<div className={ classes }>
					<BaseControl
						label={
							premium ? (
								<>
									{ title }
									<span className="gtmkit-text-xs gtmkit-text-white gtmkit-font-normal gtmkit-rounded-full gtmkit-py-0.5 gtmkit-px-2 gtmkit-h-5 gtmkit-leading-5 gtmkit-bg-color-success gtmkit-ml-6">
										Premium
									</span>
								</>
							) : (
								title
							)
						}
						id={ uniqueId }
					>
						<ToggleControl
							label={ label }
							checked={ ! disabled && Boolean( value ) }
							onChange={ () => {
								setValue( ! Boolean( value ) );
								if ( notificationId ) {
									removeNotification( notificationId );
								}
							} }
							disabled={ disabled }
						/>
					</BaseControl>
				</div>
			</>
		);
	}
);

export default ToggleSetting;
