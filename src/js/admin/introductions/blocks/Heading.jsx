/**
 * Heading block. Rendered inside the modal panel as a second-level
 * heading so the modal's labelling div can act as the dialog title.
 *
 * @param {{ text: string }} props
 * @return {JSX.Element|null}
 */
const Heading = ( { text } ) => {
	if ( typeof text !== 'string' || text === '' ) {
		return null;
	}
	return (
		<h2 className="gtmkit-text-2xl gtmkit-font-medium gtmkit-mb-4 gtmkit-text-color-heading">
			{ text }
		</h2>
	);
};

export default Heading;
