import { __ } from '@wordpress/i18n';
import SharedData from '../molecules/shared-data';
import { useContext } from '@wordpress/element';

/*Inbuilt Context*/
import { SiteDataContext } from '../../context/SiteDataContext';

const ShareAnonymousData = () => {
	const { useSiteData } = useContext( SiteDataContext );

	const getSharedData = Object.values( useSiteData.shared_data );

	return (
		<>
			<p className="gtmkit-mb-2 gtmkit-text-color-grey">
				{ __(
					'GTM Kit is used together with a wide variety of server configurations and plugins. It is very helpful for us to know what some of these configurations are so we can test the most common configurations.',
					'gtm-kit'
				) }
			</p>
			<p className="gtmkit-mb-2 gtmkit-text-color-grey">
				{ __(
					'You can help by sharing anonymous data with us. Below is a detailed view of all data GTM Kit will collect if granted permission:',
					'gtm-kit'
				) }
			</p>

			<table className="gtmkit-border-2 gtmkit-table-fixed gtmkit-w-full gtmkit-text-sm gtmkit-my-6 gtmkit-py-6">
				<tbody className="gtmkit-py-6">
					{ getSharedData.map( function ( item, index ) {
						return (
							<SharedData
								key={ index }
								label={ item.label }
								value={ item.value }
								tag={ item.tag }
							/>
						);
					} ) }
				</tbody>
			</table>
		</>
	);
};

export default ShareAnonymousData;
