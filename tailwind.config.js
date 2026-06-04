/**
 * GTM Kit
 *
 * @package
 */

// eslint-disable-next-line
/** @type {import('tailwindcss').Config} */ // phpcs:ignore

module.exports = {
	content: [
		'./src/**/*.js',
		'../gtm-kit-settings/src/**/*.js',
		'./src/**/*.php',
	],
	theme: {
		extend: {
			colors: {
				'color-primary': 'var(--gtmkit-color-primary)',
				'color-secondary': 'var(--gtmkit--color--secondary)',
				'color-button': 'var(--gtmkit--color--button)',
				'color-border': 'var(--gtmkit-border-color)',
				'color-heading': 'var(--gtmkit-text-color-heading)',
				'color-grey': 'var(--gtmkit-color-grey)',
				'color-button-disabled': 'var(--gtmkit-button-disabled)',
				'color-background-disabled': 'var(--gtmkit-button-disabled-bg)',
				'color-success': 'var(--gtmkit-color-success)',
				'color-warning': 'var(--gtmkit-color-warning)',
				'color-error': 'var(--gtmkit-color-error)',
				'brand-primary': 'var(--gtmkit-color-brand-primary)',
				'brand-surface-subtle':
					'var(--gtmkit-color-brand-surface-subtle)',
				'text-primary': 'var(--gtmkit-color-text-primary)',
				'text-secondary': 'var(--gtmkit-color-text-secondary)',
				'text-muted': 'var(--gtmkit-color-text-muted)',
				'border-default': 'var(--gtmkit-color-border-default)',
				surface: 'var(--gtmkit-color-bg-surface)',
				page: 'var(--gtmkit-color-bg-page)',
				'integration-woo': 'var(--gtmkit-color-integration-woo)',
				'integration-woo-bg': 'var(--gtmkit-color-integration-woo-bg)',
				'integration-edd': 'var(--gtmkit-color-integration-edd)',
				'integration-edd-bg': 'var(--gtmkit-color-integration-edd-bg)',
				'tier-premium': 'var(--gtmkit-color-tier-premium)',
				'tier-premium-bg': 'var(--gtmkit-color-tier-premium-bg)',
				info: 'var(--gtmkit-color-info)',
				'chip-bg': 'var(--gtmkit-color-chip-bg)',
			},
			borderRadius: {
				sm: 'var(--gtmkit-radius-sm, 4px)',
				md: 'var(--gtmkit-radius-md, 8px)',
			},
		},
	},
	plugins: [],
	prefix: 'gtmkit-',
};
