/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

/** @type {import('tailwindcss').Config} */ // phpcs:ignore

module.exports = {
	content: [
		"./src/**/*.js",
		"../gtm-kit-settings/src/**/*.js",
		'./src/**/*.php'
	],
	theme: {
		extend: {
			colors: {
				'color-primary': 'var(--gtmkit-color-primary)',
				'color-secondary': 'var(--gtmkit--color--secondary)',
				'color-button': 'var(--gtmkit--color--button)',
				'color-border': 'var(--gtmkit-border-color)',
				'color-heading': 'var(--gtmkit-text-color-heading)',
				'color-grey': 'var(--gtmkit-text-color-grey)',
				'color-button-disabled': 'var(--gtmkit-button-disabled-bg)',
			},
		},
	},
	plugins: [],
	prefix: 'gtmkit-',
}
