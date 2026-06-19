import { memo } from '@wordpress/element';
import Divider from '../atoms/divider';
import TimelineIcon from './timeline-icon';

const Step = memo( ( { step, currentStep, totalSteps } ) => {
	if ( step === 0 ) {
		return null;
	}

	let circleClasses =
		'gtmkit-transition-opacity gtmkit-duration-500 gtmkit-absolute gtmkit-inset-0 gtmkit-border-2 gtmkit-flex gtmkit-items-center gtmkit-justify-center gtmkit-rounded-full gtmkit-opacity-100';
	circleClasses +=
		step < currentStep || currentStep === totalSteps
			? ' gtmkit-bg-color-primary'
			: ' gtmkit-bg-white';
	circleClasses +=
		step > currentStep
			? ' gtmkit-border-color-border'
			: ' gtmkit-border-color-primary';

	return (
		<>
			<span className="gtmkit-relative gtmkit-shrink-0 gtmkit-z-10 gtmkit-w-8 gtmkit-h-8 gtmkit-rounded-full">
				<span className={ circleClasses }>
					<TimelineIcon
						step={ step }
						currentStep={ currentStep }
						totalSteps={ totalSteps }
					/>
				</span>
			</span>
			<Divider
				step={ step }
				currentStep={ currentStep }
				totalSteps={ totalSteps }
			/>
		</>
	);
} );

export default Step;
