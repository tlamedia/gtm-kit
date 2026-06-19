/**
 * A label/description column for a row inside a composite control.
 *
 * @param {Object} props               Component props.
 * @param {string} props.label         Row label.
 * @param {*}      [props.description] Optional description node.
 * @return {JSX.Element} The label column.
 */
const RowLabel = ( { label, description } ) => (
	<div className="gtmkit-flex gtmkit-min-w-0 gtmkit-flex-col gtmkit-gap-0.5">
		<span className="gtmkit-flex gtmkit-min-h-5 gtmkit-items-center gtmkit-text-[13px] gtmkit-font-medium gtmkit-leading-5 gtmkit-text-text-primary">
			{ label }
		</span>
		{ description && (
			<span className="gtmkit-text-xs gtmkit-text-text-secondary">
				{ description }
			</span>
		) }
	</div>
);

export default RowLabel;
