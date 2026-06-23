/*WordPress*/
import { __ } from '@wordpress/i18n';

/*Hooks / registry*/
import Pill from './Pill';
import RowLabel from './RowLabel';
import FieldTags from './FieldTags';
import UpsellSlot from './UpsellSlot';
import { BOX } from './controls';
import { useSettingField } from '../../../hooks/useSettingField';
import { useFeatureFlags } from '../../../hooks/useFeatureFlags';
import { parseKey } from '../../../registry/controls';
import { isTierLocked } from '../../../registry/gating';

/**
 * Default config shape. Mirrors the engine's defaults so the control renders a
 * complete config before the option has ever been saved (the settings API only
 * localizes options that already exist in the database).
 */
const DEFAULT_CONFIG = {
	enabled: false,
	events: {
		add_to_cart: true,
		begin_checkout: true,
		add_payment_info: true,
		add_shipping_info: true,
		purchase: true,
		view_cart: true,
	},
	timeout_ms: 3000,
	expiry_mode: 'flush',
	required_categories: [ 'analytics_storage', 'ad_storage' ],
};

const TIMEOUT_MIN_S = 1;
const TIMEOUT_MAX_S = 30;

/**
 * Events offered in the per-event list. The first group is the recommended
 * preset (deferred by default); the second group is present but unchecked so a
 * user can opt them in without the control re-implementing the event catalogue.
 */
const DEFERRABLE_EVENTS = [
	'add_to_cart',
	'begin_checkout',
	'add_payment_info',
	'add_shipping_info',
	'purchase',
	'view_cart',
	'view_item',
	'view_item_list',
	'page_view',
];

/**
 * Clamp a seconds value to the supported range, falling back to the default
 * when the field is cleared or non-numeric.
 *
 * @param {string|number} raw Raw seconds value from the input.
 * @return {number} Clamped whole seconds.
 */
const clampSeconds = ( raw ) => {
	const value = parseInt( raw, 10 );
	if ( Number.isNaN( value ) ) {
		return DEFAULT_CONFIG.timeout_ms / 1000;
	}
	if ( value < TIMEOUT_MIN_S ) {
		return TIMEOUT_MIN_S;
	}
	if ( value > TIMEOUT_MAX_S ) {
		return TIMEOUT_MAX_S;
	}
	return value;
};

/**
 * Event deferral: hold back the configured ecommerce events client-side until
 * the required consent categories are granted, then flush them in emission
 * order.
 *
 * The entire config persists as one nested option value that the shipped
 * engine reads at runtime, so every write is the full config object layered
 * over the engine's defaults; the control never writes a non-object value.
 *
 * @param {Object}  props          Component props.
 * @param {Object}  props.field    Field definition.
 * @param {boolean} props.disabled Whether the control is disabled.
 * @return {JSX.Element} The control.
 */
const ShellEventDeferral = ( { field, disabled } ) => {
	const { group, name } = parseKey( field.key );
	const [ stored, setValue ] = useSettingField( group, name );
	const [ gatingMode ] = useSettingField( 'general', 'consent_gating_mode' );
	const [ gcmEnabled ] = useSettingField( 'general', 'gcm_default_settings' );
	const { meetsRequiredTier } = useFeatureFlags();
	const lockedByTier = isTierLocked( field, meetsRequiredTier );

	const config = {
		enabled: stored?.enabled ?? DEFAULT_CONFIG.enabled,
		events: stored?.events ?? DEFAULT_CONFIG.events,
		timeout_ms: stored?.timeout_ms ?? DEFAULT_CONFIG.timeout_ms,
		expiry_mode: stored?.expiry_mode ?? DEFAULT_CONFIG.expiry_mode,
		required_categories:
			stored?.required_categories ?? DEFAULT_CONFIG.required_categories,
	};

	const update = ( patch ) => setValue( { ...config, ...patch } );

	const toggleEvent = ( eventName, isChecked ) => {
		// The stored map is the complete defer set: the engine replaces its
		// default with it wholesale, so an unchecked event is simply omitted.
		const events = { ...config.events };
		if ( isChecked ) {
			events[ eventName ] = true;
		} else {
			delete events[ eventName ];
		}
		update( { events } );
	};

	const expanded = ! disabled && config.enabled;
	const strongBlock = gatingMode === 'strong_block';
	const consentModeOff = ! gcmEnabled;

	return (
		<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-6">
			<div className="gtmkit-flex gtmkit-items-start gtmkit-gap-3">
				<div className="gtmkit-flex gtmkit-h-5 gtmkit-shrink-0 gtmkit-items-center">
					<Pill
						on={ expanded }
						disabled={ disabled }
						label={ field.label }
						onClick={ () =>
							update( { enabled: ! config.enabled } )
						}
					/>
				</div>
				<div className="gtmkit-flex gtmkit-min-w-0 gtmkit-flex-col gtmkit-gap-0.5">
					<div className="gtmkit-flex gtmkit-min-h-5 gtmkit-items-center gtmkit-gap-2">
						<span className="gtmkit-text-[13px] gtmkit-font-medium gtmkit-leading-5 gtmkit-text-text-primary">
							{ field.label }
						</span>
						<FieldTags field={ field } locked={ lockedByTier } />
					</div>
					{ field.description && (
						<p className="gtmkit-m-0 gtmkit-text-xs gtmkit-text-text-secondary">
							{ field.description }
						</p>
					) }
					{ lockedByTier && <UpsellSlot tier={ field.tier } /> }
				</div>
			</div>

			{ expanded && (
				<>
					{ consentModeOff && (
						<div className="gtmkit-rounded-md gtmkit-bg-brand-surface-subtle gtmkit-px-3.5 gtmkit-py-3">
							<p className="gtmkit-m-0 gtmkit-text-xs gtmkit-leading-[1.45] gtmkit-text-color-warning">
								{ __(
									'Event Deferral needs Consent Mode enabled to work. With Consent Mode off, GTM Kit emits no consent object, so deferred events have nothing to wait on and never release on a grant. Turn on Consent Mode (the Activate GCM settings toggle under Consent), or turn this feature off.',
									'gtm-kit'
								) }
							</p>
						</div>
					) }

					{ strongBlock && (
						<div className="gtmkit-rounded-md gtmkit-bg-brand-surface-subtle gtmkit-px-3.5 gtmkit-py-3">
							<p className="gtmkit-m-0 gtmkit-text-xs gtmkit-leading-[1.45] gtmkit-text-info">
								{ __(
									'Strong-block gating mode holds the whole container back until consent, so per-event deferral stays inert while that mode is active.',
									'gtm-kit'
								) }
							</p>
						</div>
					) }

					<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-4">
						<RowLabel
							label={ __( 'Events to defer', 'gtm-kit' ) }
							description={ __(
								'Checked events wait for consent. Unchecked events fire immediately.',
								'gtm-kit'
							) }
						/>
						<div className="gtmkit-grid gtmkit-grid-cols-2 gtmkit-gap-x-6 gtmkit-gap-y-3">
							{ DEFERRABLE_EVENTS.map( ( eventName ) => {
								const inputId = `gtmkit-deferral-event-${ eventName }`;
								return (
									<div
										key={ eventName }
										className="gtmkit-flex gtmkit-items-center gtmkit-gap-2"
									>
										<input
											id={ inputId }
											type="checkbox"
											checked={ Boolean(
												config.events[ eventName ]
											) }
											disabled={ disabled }
											onChange={ ( e ) =>
												toggleEvent(
													eventName,
													e.target.checked
												)
											}
										/>
										<label
											htmlFor={ inputId }
											className="gtmkit-cursor-pointer gtmkit-text-[13px] gtmkit-text-text-primary"
										>
											<code>{ eventName }</code>
										</label>
									</div>
								);
							} ) }
						</div>
					</div>

					<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-1.5">
						<RowLabel
							label={ __( 'Seconds to wait', 'gtm-kit' ) }
							description={ __(
								'How long the queue waits for consent before the timeout fallback runs. 1 to 30 seconds.',
								'gtm-kit'
							) }
						/>
						<input
							type="number"
							className={ `${ BOX } gtmkit-w-[120px]` }
							aria-label={ __( 'Seconds to wait', 'gtm-kit' ) }
							min={ TIMEOUT_MIN_S }
							max={ TIMEOUT_MAX_S }
							step={ 1 }
							value={ config.timeout_ms / 1000 }
							disabled={ disabled }
							onChange={ ( e ) =>
								update( {
									timeout_ms:
										clampSeconds( e.target.value ) * 1000,
								} )
							}
						/>
					</div>

					<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-2.5">
						<RowLabel label={ __( 'On timeout', 'gtm-kit' ) } />
						<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-2">
							{ [
								{
									label: __(
										'Flush the queued events anyway',
										'gtm-kit'
									),
									value: 'flush',
								},
								{
									label: __(
										'Drop the queued events (never fire without consent)',
										'gtm-kit'
									),
									value: 'drop',
								},
							].map( ( option ) => {
								const inputId = `${ group }-${ name }-expiry-${ option.value }`;
								return (
									<div
										key={ option.value }
										className="gtmkit-flex gtmkit-items-center gtmkit-gap-2"
									>
										<input
											id={ inputId }
											type="radio"
											name={ `${ group }-${ name }-expiry` }
											checked={
												config.expiry_mode ===
												option.value
											}
											disabled={ disabled }
											onChange={ () =>
												update( {
													expiry_mode: option.value,
												} )
											}
										/>
										<label
											htmlFor={ inputId }
											className="gtmkit-cursor-pointer gtmkit-text-[13px] gtmkit-text-text-primary"
										>
											{ option.label }
										</label>
									</div>
								);
							} ) }
						</div>
					</div>

					<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-1.5">
						<RowLabel
							label={ __(
								'Required consent categories',
								'gtm-kit'
							) }
							description={ __(
								'Deferred events are released once these consent categories are granted.',
								'gtm-kit'
							) }
						/>
						<p className="gtmkit-m-0 gtmkit-font-mono gtmkit-text-sm">
							{ config.required_categories.join( ', ' ) }
						</p>
					</div>
				</>
			) }
		</div>
	);
};

export default ShellEventDeferral;
