/**
 * Image block. Constrains the image so it never overflows the modal
 * panel and forwards width/height to avoid layout jank while loading.
 *
 * @param {{ url: string, alt: string, width?: number|null, height?: number|null }} props
 * @return {JSX.Element|null}
 */
const Image = ( { url, alt, width, height } ) => {
	if ( typeof url !== 'string' || url === '' ) {
		return null;
	}
	if ( typeof alt !== 'string' ) {
		return null;
	}
	const attrs = {
		src: url,
		alt,
		style: { maxWidth: '100%', height: 'auto', display: 'block', margin: '1em 0' },
	};
	if ( typeof width === 'number' && width > 0 ) {
		attrs.width = width;
	}
	if ( typeof height === 'number' && height > 0 ) {
		attrs.height = height;
	}
	return <img { ...attrs } />;
};

export default Image;
