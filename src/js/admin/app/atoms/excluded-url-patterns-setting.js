/*WordPress*/
import { Button, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { memo, useMemo } from '@wordpress/element';

/*Custom Hooks*/
import { useSettingField } from '../../hooks/useSettingField';

const PATTERN_MAX_LENGTH = 500;
const PATTERN_LIST_MAX = 100;
const ROW_GRID =
	'gtmkit-grid gtmkit-grid-cols-[1fr_140px_auto] gtmkit-gap-3 gtmkit-items-start';

/**
 * Probe a regex pattern client-side using the same delimiter and flags
 * the PHP matcher uses. Returns null on success and an error string when
 * the pattern fails to compile so the row can render an inline error.
 *
 * @param {string} pattern Raw regex pattern entered by the admin.
 * @return {string|null} Error message or null.
 */
const probeRegexPattern = ( pattern ) => {
	try {
		// eslint-disable-next-line no-new
		new RegExp( pattern, 'i' );
		return null;
	} catch ( err ) {
		return err?.message || __( 'Invalid regular expression.', 'gtm-kit' );
	}
};

/**
 * Mirror the server-side glob normalisation: when the admin pastes a
 * full URL, strip the scheme and host so the stored pattern matches
 * what the runtime matcher sees (the request path only).
 *
 * @param {string} pattern Raw pattern as entered by the admin.
 * @return {string} Path-only pattern.
 */
const extractPathFromUrlPattern = ( pattern ) => {
	if ( ! pattern ) {
		return '';
	}
	if ( ! /^https?:\/\//i.test( pattern ) && ! pattern.startsWith( '//' ) ) {
		return pattern;
	}
	try {
		const parsed = new URL(
			pattern.startsWith( '//' ) ? `https:${ pattern }` : pattern
		);
		return parsed.pathname || '/';
	} catch ( _ ) {
		return pattern;
	}
};

/**
 * Repeatable list of URL-exclusion patterns. Each row is `{ pattern, mode }`
 * where `mode` is `glob` (default) or `regex`. The value is stored verbatim
 * as the `excluded_url_patterns` general option; PHP normalises and caps
 * the list on save.
 */
const ExcludedUrlPatternsSetting = memo(
	( {
		title,
		help,
		className = 'gtmkit-settings-field-wrap gtmkit-py-4',
		optionGroup = 'general',
		optionName = 'excluded_url_patterns',
	} ) => {
		const [ value, setValue ] = useSettingField( optionGroup, optionName );

		const rows = useMemo(
			() => ( Array.isArray( value ) ? value : [] ),
			[ value ]
		);

		const updateRow = ( index, patch ) => {
			const next = rows.map( ( row, i ) =>
				i === index ? { ...row, ...patch } : row
			);
			setValue( next );
		};

		const removeRow = ( index ) => {
			setValue( rows.filter( ( _, i ) => i !== index ) );
		};

		const addRow = () => {
			if ( rows.length >= PATTERN_LIST_MAX ) {
				return;
			}
			setValue( [ ...rows, { pattern: '', mode: 'glob' } ] );
		};

		const atLimit = rows.length >= PATTERN_LIST_MAX;
		const headerCellClass =
			'gtmkit-text-xs gtmkit-font-bold gtmkit-uppercase gtmkit-text-color-heading';

		return (
			<div className={ className }>
				{ title && (
					<h4 className="gtmkit-font-bold gtmkit-mb-2">{ title }</h4>
				) }
				{ help && (
					<p className="gtmkit-mb-4 gtmkit-text-sm">{ help }</p>
				) }

				{ rows.length === 0 && (
					<p className="gtmkit-italic gtmkit-text-sm gtmkit-mb-4">
						{ __(
							'No patterns configured. GTM Kit loads on every frontend page.',
							'gtm-kit'
						) }
					</p>
				) }

				{ rows.length > 0 && (
					<div
						className={ `${ ROW_GRID } gtmkit-mb-2` }
						aria-hidden="true"
					>
						<div className={ headerCellClass }>
							{ __( 'Pattern', 'gtm-kit' ) }
						</div>
						<div className={ headerCellClass }>
							{ __( 'Mode', 'gtm-kit' ) }
						</div>
						<div />
					</div>
				) }

				<ul className="gtmkit-list-none gtmkit-p-0 gtmkit-m-0 gtmkit-space-y-3">
					{ rows.map( ( row, index ) => {
						const mode = row?.mode === 'regex' ? 'regex' : 'glob';
						const pattern =
							typeof row?.pattern === 'string' ? row.pattern : '';
						const regexError =
							mode === 'regex' && pattern !== ''
								? probeRegexPattern( pattern )
								: null;

						return (
							<li key={ index } className={ ROW_GRID }>
								<div>
									<TextControl
										label={ __( 'Pattern', 'gtm-kit' ) }
										hideLabelFromVision
										value={ pattern }
										maxLength={ PATTERN_MAX_LENGTH }
										placeholder={
											mode === 'regex'
												? '^/api/v\\d+/'
												: '/checkout-embed/*'
										}
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										onChange={ ( next ) =>
											updateRow( index, {
												pattern: next,
											} )
										}
										onBlur={ ( event ) => {
											if ( mode !== 'glob' ) {
												return;
											}
											const raw = event.target.value;
											const cleaned =
												extractPathFromUrlPattern(
													raw
												);
											if ( cleaned !== raw ) {
												updateRow( index, {
													pattern: cleaned,
												} );
											}
										} }
									/>
									{ regexError && (
										<p className="gtmkit-text-sm gtmkit-text-red-600 gtmkit-mt-1 gtmkit-mb-0">
											{ __(
												'Invalid regular expression:',
												'gtm-kit'
											) }{ ' ' }
											{ regexError }
										</p>
									) }
								</div>
								<SelectControl
									label={ __( 'Mode', 'gtm-kit' ) }
									hideLabelFromVision
									value={ mode }
									options={ [
										{
											label: __( 'Glob', 'gtm-kit' ),
											value: 'glob',
										},
										{
											label: __( 'Regex', 'gtm-kit' ),
											value: 'regex',
										},
									] }
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									onChange={ ( next ) =>
										updateRow( index, {
											mode:
												next === 'regex'
													? 'regex'
													: 'glob',
										} )
									}
								/>
								<Button
									variant="secondary"
									isDestructive
									size="default"
									__next40pxDefaultSize
									onClick={ () => removeRow( index ) }
									aria-label={ __(
										'Remove pattern',
										'gtm-kit'
									) }
								>
									{ __( 'Remove', 'gtm-kit' ) }
								</Button>
							</li>
						);
					} ) }
				</ul>

				<div className="gtmkit-mt-4">
					<Button
						variant="secondary"
						onClick={ addRow }
						disabled={ atLimit }
					>
						{ __( 'Add pattern', 'gtm-kit' ) }
					</Button>
					{ atLimit && (
						<span className="gtmkit-ml-3 gtmkit-text-sm gtmkit-text-gray-600">
							{ __(
								'Maximum of 100 patterns reached.',
								'gtm-kit'
							) }
						</span>
					) }
				</div>
			</div>
		);
	}
);

export default ExcludedUrlPatternsSetting;
