/**
 * Body block. Renders each paragraph as a separate `<p>` so the
 * surrounding modal flows naturally.
 *
 * @param {{ paragraphs: string[] }} props
 * @return {JSX.Element|null}
 */
const Body = ( { paragraphs } ) => {
	if ( ! Array.isArray( paragraphs ) || paragraphs.length === 0 ) {
		return null;
	}
	return (
		<>
			{ paragraphs
				.filter( ( p ) => typeof p === 'string' && p !== '' )
				.map( ( paragraph, index ) => (
					<p
						key={ index }
						className="gtmkit-text-base gtmkit-mb-4 gtmkit-text-color-grey"
					>
						{ paragraph }
					</p>
				) ) }
		</>
	);
};

export default Body;
