import { memo } from '@wordpress/element';

const SharedData = memo( ( { label, value, tag } ) => {
	return (
		<tr>
			<td className="gtmkit-font-bold gtmkit-px-4 gtmkit-py-2">
				<strong>{ label }</strong>
			</td>
			<td className="gtmkit-px-4 gtmkit-py-2">
				{ tag === 'code' ? (
					<code className="gtmkit-text-sm">{ value }</code>
				) : (
					<em>{ value }</em>
				) }
			</td>
		</tr>
	);
} );

export default SharedData;
