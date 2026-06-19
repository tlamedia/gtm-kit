import { memo } from '@wordpress/element';

const Divider = memo( ( { step, currentStep, totalSteps } ) => {
	if ( step === totalSteps ) {
		return null;
	}

	let classNames = 'gtmkit-h-0.5 gtmkit-w-full';
	classNames +=
		step < currentStep
			? ' gtmkit-bg-color-primary'
			: ' gtmkit-bg-color-border';

	return <div className={ classNames }></div>;
} );

export default Divider;
