/**
 * Presentational pill toggle. Brand-colored when on, neutral when off.
 *
 * @param {Object}   props          Component props.
 * @param {boolean}  props.on       Whether the toggle is on.
 * @param {boolean}  props.disabled Whether the toggle is disabled.
 * @param {Function} props.onClick  Click handler.
 * @param {string}   props.label    Accessible label.
 * @return {JSX.Element} The pill.
 */
const Pill = ( { on, disabled, onClick, label } ) => (
	<button
		type="button"
		role="switch"
		aria-checked={ on }
		aria-label={ label }
		disabled={ disabled }
		onClick={ onClick }
		className={ `gtmkit-relative gtmkit-h-5 gtmkit-w-9 gtmkit-shrink-0 gtmkit-rounded-full gtmkit-border-0 gtmkit-p-0 gtmkit-transition-colors ${
			on ? 'gtmkit-bg-brand-primary' : 'gtmkit-bg-border-default'
		} ${ disabled ? 'gtmkit-opacity-50 gtmkit-cursor-not-allowed' : '' }` }
	>
		<span
			className={ `gtmkit-absolute gtmkit-left-0.5 gtmkit-top-0.5 gtmkit-h-4 gtmkit-w-4 gtmkit-rounded-full gtmkit-bg-white gtmkit-transition-transform ${
				on ? 'gtmkit-translate-x-4' : 'gtmkit-translate-x-0'
			}` }
		/>
	</button>
);

export default Pill;
