import { forwardRef, memo } from '@wordpress/element';

/**
 * @param {Object} props           Component props.
 * @param {string} props.as        The element to render as.
 * @param {string} props.title     The title.
 * @param {Object} props.children  The children.
 * @param {string} props.className The className.
 * @return {JSX.Element} The card header.
 */
const Header = memo(
	( {
		as: Component = 'h3',
		title = '',
		children,
		className = '',
		...props
	} ) => (
		<Component
			{ ...props }
			className={
				'gtmkit-font-bold gtmkit-text-lg gtmkit-px-8 gtmkit-py-4 gtmkit-border-b gtmkit-border-color-grey gtmkit-flex gtmkit-items-center ' +
				className
			}
		>
			{ title }
			{ children }
		</Component>
	)
);

/**
 * @param {Object} props           Component props.
 * @param {string} props.as        The element to render as.
 * @param {Object} props.children  The children.
 * @param {string} props.className The className.
 * @return {JSX.Element} The card content.
 */
const Content = memo(
	( { as: Component = 'div', children, className = '', ...props } ) => (
		<Component
			{ ...props }
			className={ 'gtmkit-px-8 gtmkit-py-6 ' + className }
		>
			{ children }
		</Component>
	)
);

/**
 * @param {Object}  props           Component props.
 * @param {Object}  props.children  The children.
 * @param {string}  props.className The className.
 * @param {boolean} props.disabled  Is the box disabled.
 * @return {JSX.Element} The card component.
 */
const SectionBox = forwardRef(
	( { children, className = '', disabled = false, ...props }, ref ) => (
		<div
			{ ...props }
			className={
				'gtmkit-mb-12 gtmkit-border gtmkit-bg-white gtmkit-max-w-screen-lg gtmkit-border-color-grey gtmkit-rounded ' +
				className +
				( disabled ? 'gtmkit-opacity-60' : '' )
			}
			ref={ ref }
		>
			{ children }
		</div>
	)
);

SectionBox.Header = Header;
SectionBox.Content = Content;

export default SectionBox;
