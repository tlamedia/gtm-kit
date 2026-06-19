import { memo } from '@wordpress/element';

const TimelineIcon = memo( ( { step, currentStep, totalSteps } ) => {
	if ( step < currentStep || step === totalSteps ) {
		return (
			<svg
				xmlns="http://www.w3.org/2000/svg"
				viewBox="0 0 20 20"
				fill="currentColor"
				aria-hidden="true"
				className="gtmkit-w-5 gtmkit-h-5 gtmkit-text-white"
			>
				<path
					fillRule="evenodd"
					d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
					clipRule="evenodd"
				></path>
			</svg>
		);
	}

	if ( step === currentStep ) {
		return (
			<span className="gtmkit-h-2.5 gtmkit-w-2.5 gtmkit-rounded-full gtmkit-bg-color-primary"></span>
		);
	}

	return null;
} );

export default TimelineIcon;
