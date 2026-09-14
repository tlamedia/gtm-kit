/**
 * Image block. Constrains the image so it never overflows the modal
 * panel and forwards width/height to avoid layout jank while loading.
 *
 * @param {{ url: string, alt: string, width?: number|null, height?: number|null }} props
 * @return {JSX.Element|null} The image, or null when the block has no usable url or alt.
 */
const Image = ( { url, alt, width, height } ) => {
	if ( typeof url !== 'string' || url === '' ) {
		return null;
	}
	if ( typeof alt !== 'string' ) {
		return null;
	}
	const size = {};
	if ( typeof width === 'number' && width > 0 ) {
		size.width = width;
	}
	if ( typeof height === 'number' && height > 0 ) {
		size.height = height;
	}
	return (
		<img
			src={ url }
			alt={ alt }
			style={ {
				maxWidth: '100%',
				height: 'auto',
				display: 'block',
				margin: '1em 0',
			} }
			{ ...size }
		/>
	);
};

export default Image;
