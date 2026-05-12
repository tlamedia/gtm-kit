import { __ } from '@wordpress/i18n';

/**
 * Welcome introduction shown on the user's first non-wizard GTM Kit
 * admin page load. No "Run setup wizard" CTA: the wizard is the
 * first-run welcome surface and is already complete by the time this
 * modal renders.
 *
 * @param {{ onDismiss: () => void }} props
 * @return {JSX.Element}
 */
const Welcome = ( { onDismiss } ) => (
	<div>
		<h1 className="gtmkit-text-3xl gtmkit-font-medium gtmkit-mb-4 gtmkit-text-color-heading">
			{ __( 'Welcome to GTM Kit', 'gtm-kit' ) }
		</h1>
		<p className="gtmkit-text-base gtmkit-mb-4 gtmkit-text-color-grey">
			{ __(
				'GTM Kit is now active. Your Google Tag Manager container and data layer are ready to use.',
				'gtm-kit'
			) }
		</p>
		<p className="gtmkit-text-base gtmkit-mb-4 gtmkit-text-color-grey">
			{ __(
				'The fastest way to make the most of GTM Kit is to skim the documentation, where each feature, hook, and filter is covered.',
				'gtm-kit'
			) }
		</p>
		<div className="gtmkit-mt-6 gtmkit-flex gtmkit-gap-2">
			<a
				className="button button-primary"
				href="https://gtmkit.com/documentation/"
				target="_blank"
				rel="noreferrer"
			>
				{ __( 'Read the docs', 'gtm-kit' ) }
			</a>
			<button type="button" className="button" onClick={ onDismiss }>
				{ __( 'Got it', 'gtm-kit' ) }
			</button>
		</div>
	</div>
);

export default Welcome;
