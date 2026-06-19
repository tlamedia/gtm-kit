/*WordPress*/
import { __, sprintf } from '@wordpress/i18n';
import { useNavigate } from 'react-router-dom';

/*Registry*/
import { getAllFields } from '../../registry/assemble';
import { getCapability, getSections } from '../../registry/capabilities';
import { isIntegrationPluginActive } from '../../registry/filtering';
import FieldTags from './fields/FieldTags';

/**
 * Build a `${capability}/${section}` → section-label lookup once per render.
 *
 * @return {Object} Section labels keyed by `${capability}/${section}`.
 */
const sectionLabels = () => {
	const labels = {};
	getAllFields().forEach( ( field ) => {
		const key = `${ field.capability }/${ field.section }`;
		if ( ! labels[ key ] ) {
			const section = getSections( field.capability ).find(
				( s ) => s.id === field.section
			);
			labels[ key ] = section?.label || '';
		}
	} );
	return labels;
};

/**
 * Whether a field matches a lowercased query across its label, key,
 * description and integration tag.
 *
 * @param {Object} field The field.
 * @param {string} q     Lowercased query.
 * @return {boolean} True on match.
 */
const matches = ( field, q ) =>
	[ field.label, field.key, field.description, field.integration ]
		.filter( Boolean )
		.some( ( value ) => String( value ).toLowerCase().includes( q ) );

/**
 * Global settings search results, grouped by capability. Selecting a result
 * navigates to its capability page and scrolls to the section.
 *
 * @param {Object}   props            Component props.
 * @param {string}   props.query      The search query.
 * @param {Function} props.onNavigate Called when a result is chosen.
 * @return {JSX.Element} The results view.
 */
const SearchResults = ( { query, onNavigate } ) => {
	const navigate = useNavigate();
	const q = query.trim().toLowerCase();
	const labels = sectionLabels();

	// Exclude fields whose integration plugin is inactive, mirroring the
	// capability pages: a setting that is not shown anywhere should not surface
	// in search either.
	const found = getAllFields().filter(
		( field ) =>
			isIntegrationPluginActive( field.integration ) &&
			matches( field, q )
	);

	const byCapability = found.reduce( ( acc, field ) => {
		( acc[ field.capability ] = acc[ field.capability ] || [] ).push(
			field
		);
		return acc;
	}, {} );

	const go = ( field ) => {
		onNavigate();
		navigate( `/${ field.capability }?focus=${ field.section }` );
	};

	return (
		<div>
			<h2 className="gtmkit-mb-6 gtmkit-text-2xl gtmkit-font-bold gtmkit-text-text-primary">
				{ sprintf(
					/* translators: %s: search query. */
					__( 'Results for “%s”', 'gtm-kit' ),
					query.trim()
				) }
			</h2>

			{ found.length === 0 && (
				<p className="gtmkit-text-sm gtmkit-text-text-muted">
					{ __( 'No settings match your search.', 'gtm-kit' ) }
				</p>
			) }

			{ Object.keys( byCapability ).map( ( capabilityId ) => (
				<div
					key={ capabilityId }
					className="gtmkit-mb-6 gtmkit-overflow-hidden gtmkit-rounded-md gtmkit-border gtmkit-border-border-default gtmkit-bg-white"
				>
					<div className="gtmkit-border-b gtmkit-border-border-default gtmkit-px-5 gtmkit-py-4">
						<h3 className="gtmkit-m-0 gtmkit-text-[15px] gtmkit-font-semibold gtmkit-text-text-primary">
							{ getCapability( capabilityId )?.label }
						</h3>
					</div>
					<div className="gtmkit-px-5">
						{ byCapability[ capabilityId ].map( ( field ) => (
							<button
								key={ field.key }
								type="button"
								onClick={ () => go( field ) }
								className="gtmkit-flex gtmkit-w-full gtmkit-items-center gtmkit-justify-between gtmkit-gap-4 gtmkit-border-t gtmkit-border-border-default gtmkit-py-3 gtmkit-text-left first:gtmkit-border-t-0 hover:gtmkit-bg-page"
							>
								<span className="gtmkit-flex gtmkit-items-center gtmkit-gap-2">
									<span className="gtmkit-text-sm gtmkit-text-text-primary">
										{ field.label }
									</span>
									<FieldTags field={ field } />
								</span>
								<span className="gtmkit-text-xs gtmkit-text-text-muted">
									{
										labels[
											`${ field.capability }/${ field.section }`
										]
									}
								</span>
							</button>
						) ) }
					</div>
				</div>
			) ) }
		</div>
	);
};

export default SearchResults;
