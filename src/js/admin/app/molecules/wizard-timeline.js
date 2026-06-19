/*Inbuilt Components*/
import { memo } from '@wordpress/element';
import { getSteps } from '../utils/get-steps';
import Step from '../atoms/step';
import { useLocation } from 'react-router-dom';

const WizardTimeline = memo( () => {
	const getCurrentStep = ( steps, path ) => {
		const keys = Object.keys( path );

		return steps.filter( function ( obj ) {
			for ( let i = 0; i < keys.length; i++ ) {
				if (
					! obj.hasOwnProperty( keys[ i ] ) ||
					obj[ keys[ i ] ] !== path[ keys[ i ] ]
				) {
					return false;
				}
			}
			return true;
		} );
	};

	const totalSteps = getSteps.length - 1 + getSteps[ 0 ].step;

	let currentStep = getCurrentStep( getSteps, {
		path: useLocation().pathname,
	} );

	if ( currentStep.length ) {
		currentStep = currentStep[ 0 ].step;
	} else {
		currentStep = 0;
	}

	if ( currentStep === 0 ) {
		return <div className="gtmkit-my-16"></div>;
	}

	return (
		<div
			className="gtmkit-mt-6 gtmkit-inset-0 gtmkit-mx-auto gtmkit-my-6 gtmkit-flex gtmkit-items-center gtmkit-max-w-xl"
			aria-hidden="true"
		>
			{ getSteps.map( function ( item ) {
				return (
					<Step
						key={ item.step }
						step={ item.step }
						currentStep={ currentStep }
						totalSteps={ totalSteps }
					/>
				);
			} ) }
		</div>
	);
} );

export default WizardTimeline;
