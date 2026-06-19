/*WordPress*/
import { useContext, memo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Spinner } from '@wordpress/components';

/*Inbuilt Context*/
import { SettingsDataContext } from '../../context/SettingsDataContext';

const SaveBtn = memo(
	( {
		title = __( 'Save', 'gtm-kit' ),
		className = 'gtmkit-mx-auto gtmkit-rounded-md !gtmkit-py-4 !gtmkit-px-6 gtmkit-text-base disabled:!gtmkit-bg-color-button-disabled disabled:!gtmkit-text-color-grey',
	} ) => {
		const {
			updateSettings,
			isPending: useIsPending,
			canSave: useCanSave,
		} = useContext( SettingsDataContext );

		return (
			<Button
				className={ className }
				onClick={ () => updateSettings() }
				variant={ 'primary' }
				disabled={ useIsPending || ! useCanSave }
			>
				{ useCanSave ? title : __( 'Saved', 'gtm-kit' ) }
				{ useIsPending ? <Spinner /> : '' }
			</Button>
		);
	}
);

export default SaveBtn;
