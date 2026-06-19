/*WordPress*/
import { __, sprintf } from '@wordpress/i18n';

/*Registry*/
import { integrationLabel } from '../../registry/integrations';

/**
 * The subtitle shown under a capability heading while a filter is active: how
 * many of the capability's settings match, and the reassurance that universal
 * capabilities stay available.
 *
 * @param {Object} props                 Component props.
 * @param {string} props.filter          The active integration slug.
 * @param {string} props.capabilityLabel The capability label.
 * @param {number} props.matchingCount   Matching-field count in the capability.
 * @return {JSX.Element} The notice.
 */
export const FilterNotice = ( { filter, capabilityLabel, matchingCount } ) => {
	const label = integrationLabel( filter );

	const text =
		matchingCount > 0
			? sprintf(
					/* translators: 1: integration name, 2: setting count, 3: capability name. */
					__(
						'Filtered to %1$s — %2$d settings in %3$s. Universal capabilities (Setup, Consent) still apply and stay available.',
						'gtm-kit'
					),
					label,
					matchingCount,
					capabilityLabel
			  )
			: sprintf(
					/* translators: 1: integration name, 2: capability name. */
					__(
						'Filtered to %1$s. No %1$s settings in %2$s — universal settings still apply and stay available.',
						'gtm-kit'
					),
					label,
					capabilityLabel
			  );

	return (
		<p className="gtmkit-m-0 gtmkit-text-sm gtmkit-text-text-muted">
			{ text }
		</p>
	);
};

/**
 * A dimmed placeholder standing in for an integration's sections that the
 * active filter hid, so the page makes clear the settings still exist.
 *
 * @param {Object} props             Component props.
 * @param {string} props.integration The hidden integration slug.
 * @param {string} props.filter      The active integration slug.
 * @return {JSX.Element} The placeholder.
 */
export const HiddenIntegrationNotice = ( { integration, filter } ) => (
	<div className="gtmkit-mb-6 gtmkit-rounded-md gtmkit-border gtmkit-border-border-default gtmkit-bg-surface gtmkit-px-6 gtmkit-py-4 gtmkit-text-sm gtmkit-text-text-muted">
		{ sprintf(
			/* translators: 1: hidden integration name, 2: active filter name. */
			__( '%1$s settings hidden by the %2$s filter.', 'gtm-kit' ),
			integrationLabel( integration ),
			integrationLabel( filter )
		) }
	</div>
);
