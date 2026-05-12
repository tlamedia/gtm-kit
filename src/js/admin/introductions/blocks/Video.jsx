import { __ } from '@wordpress/i18n';

/**
 * Video block. v1 only supports YouTube embeds; unknown providers are
 * not rendered.
 *
 * @param {{ provider: string, id: string }} props
 * @return {JSX.Element|null}
 */
const Video = ( { provider, id } ) => {
	if ( provider !== 'youtube' || typeof id !== 'string' || id === '' ) {
		return null;
	}
	const src = `https://www.youtube-nocookie.com/embed/${ encodeURIComponent( id ) }`;
	return (
		<div
			style={ {
				position: 'relative',
				paddingBottom: '56.25%',
				height: 0,
				overflow: 'hidden',
				margin: '1em 0',
			} }
		>
			<iframe
				src={ src }
				title={ __( 'Video', 'gtm-kit' ) }
				allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
				allowFullScreen
				style={ {
					position: 'absolute',
					top: 0,
					left: 0,
					width: '100%',
					height: '100%',
					border: 0,
				} }
			/>
		</div>
	);
};

export default Video;
