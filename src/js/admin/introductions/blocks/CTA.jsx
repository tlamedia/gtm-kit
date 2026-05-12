/**
 * Call-to-action block. Three variants:
 *
 *  - `primary`   — `<a>` styled as the WordPress primary button.
 *  - `secondary` — `<a>` styled as the WordPress secondary button.
 *  - `dismiss`   — `<button>` that fires `onDismiss`, routing the user
 *                  through the same dismissal handler as the close
 *                  button.
 *
 * @param {{
 *   label: string,
 *   url?: string,
 *   variant: 'primary' | 'secondary' | 'dismiss',
 *   onDismiss: () => void
 * }} props
 * @return {JSX.Element|null}
 */
const CTA = ( { label, url, variant, onDismiss } ) => {
	if ( typeof label !== 'string' || label === '' ) {
		return null;
	}

	if ( variant === 'dismiss' ) {
		return (
			<button type="button" className="button" onClick={ onDismiss }>
				{ label }
			</button>
		);
	}

	if ( typeof url !== 'string' || url === '' ) {
		return null;
	}

	const className =
		variant === 'primary' ? 'button button-primary' : 'button';

	return (
		<a className={ className } href={ url } target="_blank" rel="noreferrer">
			{ label }
		</a>
	);
};

export default CTA;
