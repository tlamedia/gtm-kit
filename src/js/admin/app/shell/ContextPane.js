/*WordPress*/
import { __ } from '@wordpress/i18n';

const CARD =
	'gtmkit-rounded-md gtmkit-border gtmkit-border-border-default gtmkit-bg-white gtmkit-p-5';

/**
 * Wide-viewport context pane: a per-capability "About this section" card and an
 * illustrative "Live dataLayer preview". Rendered only at very wide viewports,
 * beside the capped content column.
 *
 * @param {Object} props         Component props.
 * @param {Object} props.context The capability's `context` ({ about, dataLayer }).
 * @return {JSX.Element} The pane.
 */
const ContextPane = ( { context } ) => (
	<div className="gtmkit-space-y-4">
		{ context.about && (
			<section className={ CARD }>
				<h3 className="gtmkit-m-0 gtmkit-mb-2 gtmkit-text-[13px] gtmkit-font-semibold gtmkit-text-text-primary">
					{ context.about.title ||
						__( 'About this section', 'gtm-kit' ) }
				</h3>
				{ ( Array.isArray( context.about.text )
					? context.about.text
					: [ context.about.text ]
				).map( ( paragraph, i ) => (
					<p
						key={ i }
						className={ `gtmkit-m-0 gtmkit-text-xs gtmkit-leading-[1.5] gtmkit-text-text-secondary${
							i > 0 ? ' gtmkit-mt-2' : ''
						}` }
					>
						{ paragraph }
					</p>
				) ) }
				{ context.about.link && (
					<a
						href={ context.about.link.href }
						target="_blank"
						rel="noreferrer"
						className="gtmkit-mt-3 gtmkit-inline-block gtmkit-text-xs gtmkit-font-medium gtmkit-text-brand-primary hover:gtmkit-underline"
					>
						{ context.about.link.label } →
					</a>
				) }
				{ context.about.note && (
					<>
						<div className="gtmkit-my-3 gtmkit-h-px gtmkit-bg-border-default" />
						<p className="gtmkit-m-0 gtmkit-text-[11px] gtmkit-leading-[1.5] gtmkit-text-text-muted">
							{ context.about.note }
						</p>
					</>
				) }
			</section>
		) }

		{ Array.isArray( context.dataLayer ) &&
			context.dataLayer.length > 0 && (
				<section className={ CARD }>
					<h3 className="gtmkit-m-0 gtmkit-mb-2 gtmkit-text-[13px] gtmkit-font-semibold gtmkit-text-text-primary">
						{ __( 'Live dataLayer preview', 'gtm-kit' ) }
					</h3>
					<pre className="gtmkit-m-0 gtmkit-overflow-x-auto gtmkit-rounded-sm gtmkit-bg-[#1d2327] gtmkit-p-3 gtmkit-text-[11px] gtmkit-leading-[1.5] gtmkit-text-[#7ee787]">
						<code>{ context.dataLayer.join( '\n' ) }</code>
					</pre>
				</section>
			) }
	</div>
);

export default ContextPane;
