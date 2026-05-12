import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import GenericBlocksRenderer from './GenericBlocksRenderer.jsx';
import { resolveComponent } from './componentRegistry';
import { dismissIntroduction } from './dismiss';
import { pickHighestPriority } from './pickIntro';

const overlayStyle = {
	position: 'fixed',
	inset: 0,
	background: 'rgba(0, 0, 0, 0.5)',
	display: 'flex',
	alignItems: 'center',
	justifyContent: 'center',
	zIndex: 100000,
};

const panelStyle = {
	background: '#fff',
	maxWidth: '640px',
	width: 'calc(100% - 2em)',
	maxHeight: 'calc(100% - 4em)',
	overflow: 'auto',
	borderRadius: '6px',
	boxShadow: '0 4px 24px rgba(0, 0, 0, 0.2)',
	padding: '2em',
	position: 'relative',
};

const closeButtonStyle = {
	position: 'absolute',
	top: '0.5em',
	right: '0.5em',
	background: 'transparent',
	border: 'none',
	fontSize: '1.5em',
	cursor: 'pointer',
	padding: '0.25em 0.5em',
	lineHeight: 1,
};

/**
 * Renders the highest-priority eligible introduction from a localised
 * list. ESC, the close button, overlay clicks, and the rendered
 * component's `onDismiss` prop all route through the same dismissal
 * handler, which posts to the seen route and unmounts the modal.
 *
 * @param {{
 *   intros: Array<{ id: string, priority: number, render_mode: string }>,
 *   restRoot: string,
 *   nonce: string
 * }} props
 * @return {JSX.Element|null}
 */
const IntroductionsModal = ( { intros, restRoot, nonce } ) => {
	const [ current, setCurrent ] = useState( () => pickHighestPriority( intros ) );

	const handleDismiss = useCallback( () => {
		if ( ! current ) {
			return;
		}
		dismissIntroduction( current.id, { restRoot, nonce } );
		setCurrent( null );
	}, [ current, restRoot, nonce ] );

	useEffect( () => {
		if ( ! current ) {
			return undefined;
		}
		const onKey = ( event ) => {
			if ( event.key === 'Escape' ) {
				handleDismiss();
			}
		};
		document.addEventListener( 'keydown', onKey );
		return () => {
			document.removeEventListener( 'keydown', onKey );
		};
	}, [ current, handleDismiss ] );

	if ( ! current ) {
		return null;
	}

	const onOverlayClick = ( event ) => {
		if ( event.target === event.currentTarget ) {
			handleDismiss();
		}
	};

	let body;
	if ( current.render_mode === 'blocks' ) {
		body = (
			<GenericBlocksRenderer
				blocks={ Array.isArray( current.blocks ) ? current.blocks : [] }
				onDismiss={ handleDismiss }
			/>
		);
	} else {
		const Component = resolveComponent( current.id );
		body = <Component onDismiss={ handleDismiss } />;
	}

	return (
		<div
			style={ overlayStyle }
			role="presentation"
			onClick={ onOverlayClick }
		>
			<div
				style={ panelStyle }
				role="dialog"
				aria-modal="true"
				aria-labelledby="gtmkit-introductions-title"
			>
				<button
					type="button"
					style={ closeButtonStyle }
					onClick={ handleDismiss }
					aria-label={ __( 'Close', 'gtm-kit' ) }
				>
					×
				</button>
				<div id="gtmkit-introductions-title">{ body }</div>
			</div>
		</div>
	);
};

export default IntroductionsModal;
