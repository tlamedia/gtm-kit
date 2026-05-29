# GTM Kit

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/gtm-kit?label=wordpress.org)](https://wordpress.org/plugins/gtm-kit/)
[![License: GPL v3](https://img.shields.io/badge/license-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0.html)

Google Tag Manager and GA4 integration for WordPress, focused on flexibility and page speed.

GTM Kit places the Google Tag Manager container on your site, no code required, and pushes structured data to the `dataLayer` for use with GA4, Facebook, and any other GTM tag. It ships e-commerce tracking for WooCommerce and Easy Digital Downloads, plus form tracking for Contact Form 7, and gives developers hooks to customise everything.

- **Website:** https://gtmkit.com/
- **WordPress.org:** https://wordpress.org/plugins/gtm-kit/
- **User documentation:** https://gtmkit.com/documentation/

## Highlights

- **Zero-code container injection** with optional delayed loading until the browser is idle, for page-speed-sensitive sites.
- **Server-side GTM (sGTM)** support, including custom domains, custom loaders, and full Stape compatibility.
- **GA4 e-commerce tracking** for WooCommerce and Easy Digital Downloads (view_item, add_to_cart, begin_checkout, purchase, and more).
- **Per-page exclusion** of the container and data layer via glob or regex URL patterns.
- **Configurable post data** in the data layer: post type, page type, categories, tags, and more.
- **Developer-friendly:** actions and filters to customise the data layer and container output.

## Add-on

GTM Kit is the free core. **GTM Kit Woo** extends it with advanced WooCommerce tracking and is available on the [WooCommerce marketplace](https://woocommerce.com/products/gtm-kit-woo-add-on/). It requires this plugin.

## Installation

For end users, install from the [WordPress plugin directory](https://wordpress.org/plugins/gtm-kit/) or search for "GTM Kit" under **Plugins → Add New** in wp-admin.

To run from source:

```bash
composer install
npm ci
```

## Development

This repo holds the `gtm-kit` core plugin. Classes in `src/` use PSR-4 autoloading via Composer; the entry point is `gtm-kit.php`.

### Quality checks

Run these before committing PHP changes:

```bash
composer phpstan   # Static analysis (level 6, bleeding edge)
composer phpcs     # Coding standards (WordPress-Extra + Docs)
composer phpcbf    # Auto-fix coding-standard issues
```

### Tests

Three harnesses ship here: PHPUnit unit (no WordPress boot), PHPUnit integration (boots WordPress against a real DB), and Vitest for the JavaScript modules. See [tests/README.md](tests/README.md) for prerequisites, one-command setup, and how to run each suite.

## Contributing

Found a bug or have a feature idea? [Open an issue](https://github.com/tlamedia/gtm-kit/issues?state=open). Pull requests are welcome.

## License

GPLv3 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-3.0.html).
