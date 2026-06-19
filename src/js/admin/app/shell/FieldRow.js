/*WordPress*/
import { useContext, memo } from '@wordpress/element';

/*Registry*/
import { getControl } from '../../registry/controls';
import { getShellControl } from './fields/controls';
import { getShellCompositeControl } from './fields/composite';
import FieldTags from './fields/FieldTags';
import UpsellSlot from './fields/UpsellSlot';
import StaleAddonNotice from './fields/StaleAddonNotice';
import { isVisible, isEnabled } from '../../registry/conditions';
import { isTierLocked, isStaleStub } from '../../registry/gating';
import { SettingsDataContext } from '../../context/SettingsDataContext';
import { useFeatureFlags } from '../../hooks/useFeatureFlags';
import SettingsService from '../../services/SettingsService';
import { safeHref } from '../../utils/safeUrl';

/**
 * Whether an integration is active: its plugin is installed and active and the
 * integration option is enabled. Computed from the settings store and plugin
 * status directly, so no per-field hook is needed.
 *
 * @param {Object} settings The settings store.
 * @param {string} plugin   Plugin slug.
 * @param {string} option   Integration option key (under `integrations`).
 * @return {boolean} True when the integration is active.
 */
export const isIntegrationActive = ( settings, plugin, option ) => {
	if ( ! SettingsService.isPluginActive( plugin ) ) {
		return false;
	}
	const value = settings?.integrations?.[ option ];
	return value === '1' || value === true || value === 1;
};

/**
 * Render a single field from the registry.
 *
 * Resolves the control renderer from the control-type map and computes the
 * disabled state from two independent axes: license tier (does the active tier
 * meet the field's `tier`) and the declarative `enabledWhen` condition. A field
 * hidden by `visibleWhen` renders nothing.
 *
 * @param {Object} props       Component props.
 * @param {Object} props.field The field definition.
 * @return {JSX.Element|null} The rendered control, or null.
 */
const FieldRow = memo( ( { field } ) => {
	const { settings } = useContext( SettingsDataContext );
	const { meetsRequiredTier } = useFeatureFlags();

	if ( ! isVisible( field, settings ) ) {
		return null;
	}

	const lockedByTier = isTierLocked( field, meetsRequiredTier );
	// A stub the user is licensed for but no add-on superseded: the providing
	// add-on is too old to register, so hold the control locked rather than let
	// a simplified stub stand in for the real field.
	const staleStub = isStaleStub( field, meetsRequiredTier );
	const disabledByCondition = ! isEnabled( field, settings );
	const disabledByPlugin = field.requiresPlugin
		? ! SettingsService.isPluginActive( field.requiresPlugin )
		: false;
	const disabledByIntegration = field.requiresIntegration
		? ! isIntegrationActive(
				settings,
				field.requiresIntegration.plugin,
				field.requiresIntegration.option
		  )
		: false;
	const disabled =
		lockedByTier ||
		staleStub ||
		disabledByCondition ||
		disabledByPlugin ||
		disabledByIntegration;

	// An optional help link a registered field can declare to point at a
	// related admin screen. Validated like any other externally-supplied URL.
	const helpHref = field.helpLink ? safeHref( field.helpLink.url ) : '';

	// Redesigned controls render stacked: the label, inline tags and optional
	// description sit together, with the control directly below (inputs/selects)
	// or to the right (toggles). No dividers; sections space rows with a gap.
	const ShellControl = getShellControl( field.control );
	if ( ShellControl ) {
		// The stacked layout reads cleaner without trailing label colons.
		const label = field.label.replace( /:\s*$/, '' );
		const labelBlock = (
			<div className="gtmkit-flex gtmkit-min-w-0 gtmkit-flex-col gtmkit-gap-0.5">
				<div className="gtmkit-flex gtmkit-min-h-5 gtmkit-items-center gtmkit-gap-2">
					<span className="gtmkit-text-[13px] gtmkit-font-medium gtmkit-leading-5 gtmkit-text-text-primary">
						{ label }
					</span>
					<FieldTags
						field={ field }
						locked={ lockedByTier || staleStub }
					/>
				</div>
				{ field.description && (
					<p className="gtmkit-m-0 gtmkit-text-xs gtmkit-text-text-secondary">
						{ field.description }
					</p>
				) }
				{ helpHref && field.helpLink.label && (
					<p className="gtmkit-m-0 gtmkit-text-xs">
						<a
							className="gtmkit-font-medium gtmkit-text-brand-primary hover:gtmkit-underline"
							href={ helpHref }
							target="_blank"
							rel="noreferrer"
						>
							{ field.helpLink.label }
						</a>
					</p>
				) }
				{ lockedByTier && <UpsellSlot /> }
				{ staleStub && <StaleAddonNotice tier={ field.tier } /> }
			</div>
		);

		if ( field.control === 'toggle' ) {
			return (
				<div className="gtmkit-flex gtmkit-items-start gtmkit-gap-3">
					<div className="gtmkit-flex gtmkit-h-5 gtmkit-shrink-0 gtmkit-items-center">
						<ShellControl field={ field } disabled={ disabled } />
					</div>
					{ labelBlock }
				</div>
			);
		}

		return (
			<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-1.5">
				{ labelBlock }
				<ShellControl field={ field } disabled={ disabled } />
			</div>
		);
	}

	// Composite controls lay out their own multiple rows.
	const CompositeControl = getShellCompositeControl( field.control );
	if ( CompositeControl ) {
		return <CompositeControl field={ field } disabled={ disabled } />;
	}

	// Controls not yet redesigned fall back to the shared atom rendering.
	const Control = getControl( field.control );
	if ( ! Control ) {
		return null;
	}

	return <Control field={ field } disabled={ disabled } />;
} );

export default FieldRow;
