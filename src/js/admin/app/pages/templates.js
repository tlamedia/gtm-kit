/* eslint-disable jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions, no-nested-ternary */
/*WordPress*/
import { __ } from '@wordpress/i18n';
import {
	useState,
	useEffect,
	useContext,
	useCallback,
	useMemo,
	memo,
} from '@wordpress/element';
import {
	CheckboxControl,
	TextControl,
	Button,
	RadioControl,
} from '@wordpress/components';

/*Inbuilt Components*/
import Card from '../shell/Card';
import ContextPane from '../shell/ContextPane';
import { SettingsDataContext } from '../../context/SettingsDataContext';
import { SiteDataContext } from '../../context/SiteDataContext';
import { LicenseContext } from '../../context/LicenseContext';
import { getAdminLink } from '../utils/get-admin-link';
import SettingsService from '../../services/SettingsService';

/**
 * Wide-viewport context pane for the Template Assistant: what the generator
 * produces and a reassurance that it never touches the live container.
 */
const TEMPLATES_CONTEXT = {
	about: {
		title: __( 'About the assistant', 'gtm-kit' ),
		text: __(
			'The assistant builds a ready-to-import GTM container from your setup, with tags, triggers and variables already wired to the data layer GTM Kit pushes. It is a starting point you import into Google Tag Manager, then review and publish yourself.',
			'gtm-kit'
		),
		link: {
			label: __( 'Read the import guide', 'gtm-kit' ),
			href: 'https://jump.gtmkit.com/link/5-7DD1E',
		},
		note: __(
			'Generating a template never touches your live container. Nothing goes live until you import the file and publish it in Google Tag Manager.',
			'gtm-kit'
		),
	},
};

/**
 * Pull a human-readable reason out of a failed generator response.
 *
 * The generator answers a rejected request with a JSON body (a top-level
 * message and/or per-field validation errors). Surface that instead of
 * throwing it away, falling back to the HTTP status when the body is empty or
 * not JSON.
 *
 * @param {Response} response The failed fetch response.
 * @return {Promise<string>} A short detail string, or '' when nothing usable.
 */
const readErrorDetail = async ( response ) => {
	let body = '';

	try {
		body = await response.text();
	} catch ( error ) {
		body = '';
	}

	if ( body ) {
		try {
			const data = JSON.parse( body );
			const parts = [];

			if ( data.message ) {
				parts.push( data.message );
			}

			if ( data.errors && typeof data.errors === 'object' ) {
				Object.values( data.errors ).forEach( ( messages ) => {
					if ( Array.isArray( messages ) ) {
						parts.push( ...messages );
					} else if ( messages ) {
						parts.push( messages );
					}
				} );
			}

			if ( parts.length ) {
				return parts.join( ' ' );
			}
		} catch ( error ) {
			// Not JSON; fall through to the status-based detail.
		}
	}

	if ( response.status ) {
		return `(HTTP ${ response.status }${
			response.statusText ? ' ' + response.statusText : ''
		})`;
	}

	return '';
};

const Templates = memo( ( { templateData } ) => {
	const { useSettings } = useContext( SettingsDataContext );
	const { useSiteData } = useContext( SiteDataContext );
	const { hasValidLicense } = useContext( LicenseContext );

	// Memoize computed values
	const isServerSide = useMemo(
		() =>
			useSettings.general.sgtm_domain &&
			useSettings.general.sgtm_domain !== 'www.googletagmanager.com',
		[ useSettings.general.sgtm_domain ]
	);

	// Wizard state management
	const [ wizardStep, setWizardStep ] = useState( 1 );
	const [ selectedServices, setSelectedServices ] = useState( {} );
	const [ serviceConfigs, setServiceConfigs ] = useState( {} );
	const [ gtmConfigType, setGtmConfigType ] = useState(
		isServerSide ? 'server-side' : 'standard'
	);
	const [ siteType, setSiteType ] = useState(
		useSiteData.ecommerce ? 'ecommerce' : 'lead'
	);
	const [ generationError, setGenerationError ] = useState( '' );

	// Initialize server container URL and ID when component mounts if server-side is detected
	useEffect( () => {
		if ( isServerSide ) {
			const updates = {};

			if (
				useSettings.general.sgtm_domain &&
				! serviceConfigs.serverContainer?.url
			) {
				updates.serverContainer = {
					url: useSettings.general.sgtm_domain,
					containerId: useSettings.general.gtm_id || '',
				};
			}

			if ( Object.keys( updates ).length > 0 ) {
				setServiceConfigs( ( prev ) => ( {
					...prev,
					...updates,
				} ) );
			}
		}
	}, [
		isServerSide,
		useSettings.general.sgtm_domain,
		useSettings.general.sgtm_container_identifier,
		useSettings.general.gtm_id,
		serviceConfigs.serverContainer?.url,
	] );

	// Handle wizard step navigation - allow going back to previous steps
	const handleStepClick = useCallback(
		( step ) => {
			if ( step < wizardStep ) {
				setWizardStep( step );
			}
		},
		[ wizardStep ]
	);

	// Handle service selection
	const handleServiceToggle = useCallback( ( serviceId ) => {
		setSelectedServices( ( prev ) => ( {
			...prev,
			[ serviceId ]: ! prev[ serviceId ],
		} ) );
	}, [] );

	// Handle configuration input
	const handleConfigChange = useCallback( ( serviceId, fieldKey, value ) => {
		setServiceConfigs( ( prev ) => ( {
			...prev,
			[ serviceId ]: {
				...prev[ serviceId ],
				[ fieldKey ]: value,
			},
		} ) );
	}, [] );

	// Initialize server container URL and ID when switching to server-side mode
	const handleGtmConfigTypeChange = useCallback(
		( value ) => {
			setGtmConfigType( value );

			// Pre-populate serverContainer URL and containerId if switching to server-side and not already set
			if (
				value === 'server-side' &&
				! serviceConfigs.serverContainer?.url &&
				useSettings.general.sgtm_domain
			) {
				setServiceConfigs( ( prev ) => ( {
					...prev,
					serverContainer: {
						url: useSettings.general.sgtm_domain,
						containerId:
							useSettings.general.sgtm_container_identifier || '',
					},
				} ) );
			}
		},
		[
			serviceConfigs.serverContainer?.url,
			useSettings.general.sgtm_domain,
			useSettings.general.sgtm_container_identifier,
		]
	);

	// Generate template configuration
	const generateTemplate = useCallback(
		async ( usageContext = 'WEB' ) => {
			const configData = {
				selectedServices: Object.keys( selectedServices ).filter(
					( key ) => selectedServices[ key ]
				),
				serviceConfigs,
				gtmType: gtmConfigType,
				serverContainerUrl: serviceConfigs.serverContainer?.url || '',
				serverContainerId:
					serviceConfigs.serverContainer?.containerId || '',
				ecommerce: siteType === 'ecommerce',
				siteType,
				usageContext,
			};

			setGenerationError( '' );

			try {
				const response = await fetch(
					SettingsService.getGeneratorUrl(),
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': SettingsService.getNonce(),
						},
						body: JSON.stringify( configData ),
					}
				);

				if ( response.ok ) {
					const template = await response.blob();
					const url = window.URL.createObjectURL( template );
					const a = document.createElement( 'a' );
					a.href = url;
					a.download = `gtm-template-${ usageContext.toLowerCase() }.json`;
					document.body.appendChild( a );
					a.click();
					window.URL.revokeObjectURL( url );
					document.body.removeChild( a );
				} else {
					// Read the backend's actual reason instead of discarding it,
					// so a rejected request surfaces what failed rather than a
					// generic message.
					const detail = await readErrorDetail( response );

					// eslint-disable-next-line no-console
					console.error(
						'GTM Kit template generation failed:',
						response.status,
						response.statusText,
						detail
					);

					const friendly = __(
						'Error generating template. Please try again.',
						'gtm-kit'
					);
					setGenerationError(
						detail ? `${ friendly } ${ detail }` : friendly
					);
				}
			} catch ( error ) {
				// eslint-disable-next-line no-console
				console.error(
					'GTM Kit template generation request failed:',
					error
				);

				setGenerationError(
					__(
						'Error generating template. Please check your connection and try again.',
						'gtm-kit'
					)
				);
			}
		},
		[ selectedServices, serviceConfigs, gtmConfigType, siteType ]
	);

	// Get selected services for configuration step
	const getSelectedServicesList = useCallback( () => {
		return templateData.filter(
			( service ) => selectedServices[ service.id ]
		);
	}, [ templateData, selectedServices ] );

	const hasSelectedServices = useMemo(
		() =>
			Object.values( selectedServices ).some( ( selected ) => selected ),
		[ selectedServices ]
	);

	const selectedServicesList = useMemo(
		() => getSelectedServicesList(),
		[ getSelectedServicesList ]
	);

	// Check if templateData is empty or unavailable
	if ( ! Array.isArray( templateData ) || templateData.length === 0 ) {
		return (
			<>
				<h2 className="gtmkit-text-2xl gtmkit-font-bold gtmkit-text-color-heading gtmkit-mb-8">
					{ __( 'Template Assistant', 'gtm-kit' ) }
				</h2>

				<Card
					title={ __(
						'Get your Google Tag Manager container template',
						'gtm-kit'
					) }
				>
					<div className="gtmkit-bg-yellow-50 gtmkit-border gtmkit-border-yellow-200 gtmkit-rounded-lg gtmkit-p-6 gtmkit-text-center">
						<p className="gtmkit-text-lg gtmkit-font-semibold gtmkit-mb-2">
							{ __(
								'Template service is currently unavailable',
								'gtm-kit'
							) }
						</p>
						<p className="gtmkit-text-gray-600">
							{ __(
								'The template service is not available at the moment. Please try again later.',
								'gtm-kit'
							) }
						</p>
					</div>
				</Card>
			</>
		);
	}

	return (
		<>
			<div className="gtmkit-mb-8">
				<h2 className="gtmkit-mb-1 gtmkit-text-2xl gtmkit-font-bold gtmkit-text-text-primary">
					{ __( 'Template Assistant', 'gtm-kit' ) }
				</h2>
				<p className="gtmkit-m-0 gtmkit-text-sm gtmkit-text-text-muted">
					{ __(
						'Generate a GTM container template from your setup',
						'gtm-kit'
					) }
				</p>
			</div>

			<div className="min-[1440px]:gtmkit-flex min-[1440px]:gtmkit-items-start min-[1440px]:gtmkit-gap-6">
				<div className="min-[1440px]:gtmkit-min-w-0 min-[1440px]:gtmkit-flex-1">
					<Card
						title={ __(
							'Get your Google Tag Manager container template',
							'gtm-kit'
						) }
					>
						<p className="gtmkit-mb-4 gtmkit-text-text-secondary">
							{ __(
								'GTM Kit is sending data to your container, but you still need to configure Tags, Triggers and Variables in GTM. The generator builds a template from your choices to import and deploy.',
								'gtm-kit'
							) }
						</p>

						{ /* Wizard Progress Indicator */ }
						<div className="gtmkit-mb-6 gtmkit-mt-8 gtmkit-flex gtmkit-flex-wrap gtmkit-gap-2">
							{ [
								__( '1. Configuration Type', 'gtm-kit' ),
								__( '2. Requirements', 'gtm-kit' ),
								__( '3. Configure', 'gtm-kit' ),
								__( '4. Generate', 'gtm-kit' ),
							].map( ( label, i ) => {
								const step = i + 1;
								const active = wizardStep === step;
								const done = wizardStep > step;
								return (
									<button
										key={ step }
										type="button"
										disabled={ ! done }
										onClick={ () =>
											handleStepClick( step )
										}
										className={ `gtmkit-rounded-full gtmkit-px-4 gtmkit-py-1.5 gtmkit-text-sm gtmkit-font-medium ${
											active
												? 'gtmkit-bg-brand-primary gtmkit-text-white'
												: 'gtmkit-bg-gray-100 gtmkit-text-text-secondary'
										} ${
											done
												? 'gtmkit-cursor-pointer hover:gtmkit-bg-gray-200'
												: 'gtmkit-cursor-default'
										}` }
									>
										{ label }
									</button>
								);
							} ) }
						</div>

						{ wizardStep === 1 && (
							<div>
								<h3 className="gtmkit-pt-3 gtmkit-mb-2 gtmkit-font-bold">
									{ __( 'Configuration Type', 'gtm-kit' ) }
								</h3>

								<div className="gtmkit-mb-6">
									<RadioControl
										label={ __(
											'Select your site type:',
											'gtm-kit'
										) }
										help={ __(
											'Choose whether your site is primarily for e-commerce or lead generation.',
											'gtm-kit'
										) }
										selected={ siteType }
										options={ [
											{
												label: (
													<>
														{ __(
															'E-commerce',
															'gtm-kit'
														) }
														{ useSiteData.ecommerce && (
															<span className="gtmkit-ml-2">
																(
																{ __(
																	'Recommended based on your setup',
																	'gtm-kit'
																) }
																)
															</span>
														) }
													</>
												),
												value: 'ecommerce',
											},
											{
												label: (
													<>
														{ __(
															'Lead Generation',
															'gtm-kit'
														) }
														{ ! useSiteData.ecommerce && (
															<span className="gtmkit-ml-2">
																(
																{ __(
																	'Recommended based on your setup',
																	'gtm-kit'
																) }
																)
															</span>
														) }
													</>
												),
												value: 'lead',
											},
										] }
										onChange={ ( value ) =>
											setSiteType( value )
										}
									/>
								</div>

								<div className="gtmkit-mb-6">
									<RadioControl
										label={ __(
											'Select your Google Tag Manager setup:',
											'gtm-kit'
										) }
										help={ __(
											'Choose the type of Google Tag Manager setup you are using.',
											'gtm-kit'
										) }
										selected={ gtmConfigType }
										options={ [
											{
												label: __(
													'Standard GTM',
													'gtm-kit'
												),
												value: 'standard',
											},
											{
												label: (
													<>
														{ __(
															'Server-side GTM',
															'gtm-kit'
														) }
														{ isServerSide && (
															<span className="gtmkit-ml-2">
																(
																{ __(
																	'It looks like you are using server-side GTM',
																	'gtm-kit'
																) }
																)
															</span>
														) }
													</>
												),
												value: 'server-side',
											},
										] }
										onChange={ handleGtmConfigTypeChange }
									/>
								</div>

								<div className="gtmkit-mt-6 gtmkit-flex gtmkit-space-x-4">
									<Button
										isPrimary
										onClick={ () => setWizardStep( 2 ) }
									>
										{ __(
											'Continue to Service Selection',
											'gtm-kit'
										) }
									</Button>
								</div>
							</div>
						) }

						{ /* Step 2: Service Selection */ }
						{ wizardStep === 2 && (
							<>
								<h3 className="gtmkit-pt-3 gtmkit-mb-2 gtmkit-font-bold">
									{ __(
										'Specify your tracking needs',
										'gtm-kit'
									) }
								</h3>
								<p className="gtmkit-mb-4">
									{ __(
										'Select the services that you want to send tracking data to.',
										'gtm-kit'
									) }
								</p>

								<div className="gtmkit-grid gtmkit-grid-cols-3 gtmkit-gap-4">
									{ templateData.map( ( service ) => (
										<div
											key={ service.id }
											className={ `gtmkit-rounded-md gtmkit-border gtmkit-p-4 ${
												selectedServices[ service.id ]
													? 'gtmkit-border-brand-primary'
													: 'gtmkit-border-border-default'
											}` }
										>
											<CheckboxControl
												label={ service.title }
												help={ service.collections
													.filter( ( collection ) => {
														if (
															collection.type ===
															'all'
														) {
															return true;
														}
														return (
															collection.type ===
															siteType
														);
													} )
													.map( ( collection ) => (
														<div
															key={
																collection.id
															}
														>
															{ collection.title }
														</div>
													) ) }
												disabled={
													( service.premium &&
														! hasValidLicense ) ||
													( service.sgtm &&
														! isServerSide )
												}
												checked={
													selectedServices[
														service.id
													] || false
												}
												onChange={ () =>
													handleServiceToggle(
														service.id
													)
												}
											/>

											<div className="gtmkit-flex gtmkit-gap-x-2 gtmkit-ml-6">
												{ service.premium &&
													! hasValidLicense && (
														<a
															className="gtmkit-w-fit gtmkit-px-3 gtmkit-py-0.5 gtmkit-rounded-full gtmkit-text-xs gtmkit-font-medium gtmkit-bg-tier-premium-bg gtmkit-text-tier-premium gtmkit-whitespace-nowrap"
															href={ getAdminLink(
																'upgrades',
																'upgrades'
															) }
														>
															{ __(
																'Premium',
																'gtm-kit'
															) }
														</a>
													) }
												{ service.sgtm &&
													! isServerSide && (
														<a
															className="gtmkit-w-fit gtmkit-px-3 gtmkit-py-0.5 gtmkit-rounded-full gtmkit-text-xs gtmkit-bg-gray-200 gtmkit-whitespace-nowrap"
															href={ getAdminLink(
																'general',
																'container?focus=sgtm'
															) }
														>
															{ __(
																'Requires sGTM',
																'gtm-kit'
															) }
														</a>
													) }
											</div>
										</div>
									) ) }
								</div>

								<div className="gtmkit-mt-6 gtmkit-flex gtmkit-space-x-4">
									<Button
										isSecondary
										onClick={ () => setWizardStep( 1 ) }
									>
										{ __( 'Back', 'gtm-kit' ) }
									</Button>

									<Button
										isPrimary
										disabled={ ! hasSelectedServices }
										onClick={ () => setWizardStep( 3 ) }
									>
										{ __(
											'Continue to Configuration',
											'gtm-kit'
										) }
									</Button>
								</div>
							</>
						) }

						{ /* Step 3: Configuration */ }
						{ wizardStep === 3 && (
							<>
								<h3 className="gtmkit-pt-3 gtmkit-mb-2 gtmkit-font-bold">
									{ __(
										'Input your unique values',
										'gtm-kit'
									) }
								</h3>
								<p className="gtmkit-mb-8">
									{ __(
										'These values are optional and you can edit them in your container at any time.',
										'gtm-kit'
									) }
								</p>
								<div className="gtmkit-space-y-6">
									{ gtmConfigType === 'server-side' && (
										<div className="gtmkit-border gtmkit-border-border-default gtmkit-rounded-md gtmkit-px-6 gtmkit-py-4">
											<h4 className="gtmkit-font-semibold gtmkit-mb-3">
												{ __(
													'Server-side GTM Configuration',
													'gtm-kit'
												) }
											</h4>
											<TextControl
												label={ __(
													'Server Container URL',
													'gtm-kit'
												) }
												placeholder={ __(
													'Enter your server container URL',
													'gtm-kit'
												) }
												value={
													serviceConfigs
														.serverContainer?.url ||
													useSettings.general
														.sgtm_domain ||
													''
												}
												onChange={ ( value ) =>
													handleConfigChange(
														'serverContainer',
														'url',
														value
													)
												}
											/>
											<TextControl
												label={ __(
													'Server Container ID',
													'gtm-kit'
												) }
												placeholder={ __(
													'GTM-XXXXX',
													'gtm-kit'
												) }
												help={ __(
													'Enter your server-side GTM container ID (e.g., GTM-XXXXXX)',
													'gtm-kit'
												) }
												value={
													serviceConfigs
														.serverContainer
														?.containerId ||
													useSettings.general
														.sgtm_container_identifier ||
													''
												}
												onChange={ ( value ) =>
													handleConfigChange(
														'serverContainer',
														'containerId',
														value
													)
												}
											/>
										</div>
									) }
									{ selectedServicesList.map( ( service ) => {
										// Get all templates with fields from the service's collections
										const templatesWithFields =
											service.collections
												.filter( ( collection ) => {
													if (
														collection.type ===
														'all'
													) {
														return true;
													}
													return (
														collection.type ===
														siteType
													);
												} )
												.flatMap( ( collection ) =>
													collection.templates.filter(
														( template ) =>
															template.fields &&
															Object.keys(
																template.fields
															).length > 0
													)
												);

										// Deduplicate fields by key
										const uniqueFieldsMap = new Map();
										templatesWithFields.forEach(
											( template ) => {
												const fields = template.fields;

												// Handle single field object
												if ( fields.key ) {
													if (
														! uniqueFieldsMap.has(
															fields.key
														)
													) {
														uniqueFieldsMap.set(
															fields.key,
															fields
														);
													}
												}

												// Handle array of fields
												if ( Array.isArray( fields ) ) {
													fields.forEach(
														( field ) => {
															if (
																! uniqueFieldsMap.has(
																	field.key
																)
															) {
																uniqueFieldsMap.set(
																	field.key,
																	field
																);
															}
														}
													);
												}
											}
										);

										const uniqueFields = Array.from(
											uniqueFieldsMap.values()
										);

										return (
											<div
												key={ service.id }
												className="gtmkit-border gtmkit-border-border-default gtmkit-rounded-md gtmkit-px-6 gtmkit-py-4"
											>
												<h4 className="gtmkit-font-semibold gtmkit-mb-3">
													{ service.title }
												</h4>

												{ uniqueFields.length === 0 ? (
													<p className="gtmkit-text-gray-600 gtmkit-italic">
														{ __(
															'No configuration required',
															'gtm-kit'
														) }
													</p>
												) : (
													<div className="gtmkit-space-y-4">
														{ uniqueFields.map(
															( field ) => (
																<TextControl
																	key={ `${ service.id }-${ field.key }` }
																	label={
																		field.label
																	}
																	placeholder={
																		field.placeholder
																	}
																	value={
																		serviceConfigs[
																			service
																				.id
																		]?.[
																			field
																				.key
																		] || ''
																	}
																	onChange={ (
																		value
																	) =>
																		handleConfigChange(
																			service.id,
																			field.key,
																			value
																		)
																	}
																/>
															)
														) }
													</div>
												) }
											</div>
										);
									} ) }
								</div>

								<div className="gtmkit-mt-6 gtmkit-flex gtmkit-space-x-4">
									<Button
										isSecondary
										onClick={ () => setWizardStep( 2 ) }
									>
										{ __( 'Back', 'gtm-kit' ) }
									</Button>
									<Button
										isPrimary
										onClick={ () => setWizardStep( 4 ) }
									>
										{ __(
											'Continue to Generate',
											'gtm-kit'
										) }
									</Button>
								</div>
							</>
						) }

						{ /* Step 4: Generate Template */ }
						{ wizardStep === 4 && (
							<>
								<h3 className="gtmkit-pt-3 gtmkit-mb-2 gtmkit-font-bold">
									{ __(
										'Download and import the template',
										'gtm-kit'
									) }
								</h3>
								<p className="gtmkit-mb-4">
									{ __(
										'Review your configuration and generate the GTM template.',
										'gtm-kit'
									) }
								</p>

								{ /* Configuration Summary */ }
								<div className="gtmkit-bg-gray-50 gtmkit-px-6 gtmkit-py-4 gtmkit-rounded-lg gtmkit-mb-4">
									<h4 className="gtmkit-font-semibold gtmkit-mb-2">
										{ __(
											'Google Tag Manager configuration:',
											'gtm-kit'
										) }
									</h4>
									<p className="gtmkit-mb-8">
										{ gtmConfigType === 'server-side'
											? __(
													'Client-Side + Server-Side GTM',
													'gtm-kit'
											  )
											: __(
													'Standard Client-Side GTM',
													'gtm-kit'
											  ) }
									</p>

									<h4 className="gtmkit-font-semibold gtmkit-mb-2">
										{ __(
											'Selected Services:',
											'gtm-kit'
										) }
									</h4>
									<ul className="gtmkit-text-sm gtmkit-list-disc gtmkit-list-inside gtmkit-space-y-1">
										{ selectedServicesList.map(
											( service ) => (
												<li key={ service.id }>
													{ service.title }
												</li>
											)
										) }
									</ul>
								</div>

								<div className="gtmkit-mt-6 gtmkit-flex gtmkit-space-x-4">
									<Button
										isSecondary
										onClick={ () => setWizardStep( 3 ) }
									>
										{ __( 'Back', 'gtm-kit' ) }
									</Button>
									{ gtmConfigType === 'server-side' ? (
										<>
											<Button
												isPrimary
												onClick={ () =>
													generateTemplate( 'WEB' )
												}
											>
												{ __(
													'Download Web Template',
													'gtm-kit'
												) }
											</Button>
											<Button
												isPrimary
												onClick={ () =>
													generateTemplate( 'SERVER' )
												}
											>
												{ __(
													'Download Server Template',
													'gtm-kit'
												) }
											</Button>
										</>
									) : (
										<Button
											isPrimary
											onClick={ () =>
												generateTemplate( 'WEB' )
											}
										>
											{ __(
												'Generate & Download Template',
												'gtm-kit'
											) }
										</Button>
									) }
								</div>

								{ generationError && (
									<div
										role="alert"
										className="gtmkit-mt-6 gtmkit-bg-red-50 gtmkit-border gtmkit-border-red-200 gtmkit-text-red-800 gtmkit-rounded-md gtmkit-px-6 gtmkit-py-4"
									>
										{ generationError }
									</div>
								) }

								<p className="gtmkit-mt-12 gtmkit-mb-4">
									{ __(
										'Please read the guide on how to use the import files and configure GTM.',
										'gtm-kit'
									) }
									<a
										className="gtmkit-ml-2 gtmkit-text-color-primary gtmkit-font-semibold hover:gtmkit-underline"
										href="https://jump.gtmkit.com/link/5-7DD1E"
										target="_blank"
										rel="noreferrer"
									>
										{ __( 'Read the guide', 'gtm-kit' ) }
									</a>
								</p>
							</>
						) }
					</Card>
				</div>
				<aside className="gtmkit-hidden min-[1440px]:gtmkit-block min-[1440px]:gtmkit-w-[400px] min-[1440px]:gtmkit-shrink-0 min-[1440px]:gtmkit-sticky min-[1440px]:gtmkit-top-[112px] min-[1440px]:gtmkit-self-start">
					<ContextPane context={ TEMPLATES_CONTEXT } />
				</aside>
			</div>
		</>
	);
} );

export default Templates;
