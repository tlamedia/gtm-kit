=== GTM Kit - Google Tag Manager & GA4 integration ===
Contributors: tlamedia, torbenlundsgaard, gtmkit
Donate link: https://github.com/tlamedia/gtm-kit
Tags: google tag manager, gtm, woocommerce, analytics, ga4
Requires at least: 6.8
Tested up to: 7.0
Stable tag: 2.15.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Google Tag Manager and GA4 integration. Including WooCommerce data for Google Analytics 4 and support for server side GTM.

== Description ==

GTM Kit puts the Google Tag Manager container code on your website so that you don't need to touch any code. It also pushes data from WooCommerce, Easy Digital Downloads (EDD) and Contact Form 7 to the data layer for use with for Google Analytics 4, Facebook and other GTM tags.

The goal of GTM Kit is to provide a flexible tool for generating the data layer for Google Tag Manager. It is easy to use and doesn't require any coding, but it allows developers to customize the plugin as needed.

## eCommerce events tracked with Google Analytics 4
The following GA4 events are automatically included in the dataLayer:

### WooCommerce
- view_item_list
- select_item
- view_item
- add_to_wishlist **[Premium]**
- add_to_cart
- view_cart
- remove_from_cart
- begin_checkout
- add_shipping_info
- add_payment_info
- purchase
- refund **[Premium]**
- order_paid **[Premium]**

Unlock all features with [GTM Kit Woo Add-On](https://woocommerce.com/products/gtm-kit-woo-add-on/).

### Easy Digital Downloads
- view_item
- add_to_cart
- begin_checkout
- purchase


## Flexible container implementation

Depending on how you use Google Tag Manager you can delay the loading of the container script until the browser is idle. This may be relevant to you be if are focusing on pagespeed.

You may enter a custom domain name if you are using a custom server side GTM (sGTM) container for tracking. It's also possible to specify a custom loader. GTM Kit has full support for Stape server GTM hosting.

You can also exclude specific pages from GTM entirely. Add URL patterns on the Container settings page and GTM Kit holds back the container, the noscript fallback, and its data layer scripts on matching pages. Useful for third-party checkout iframes, partner-hosted subpages, and in-app webview routes that run their own tracking. Glob patterns are supported by default, with optional regex for advanced matching.

## Post data

You may specify which post data elements you wish to include in the dataLayer for use in Google Tag Manager.
- Post type: include the type of the current post or archive page.
- Page type: include a defined page type. I.e. post, page, product, category, cart, checkout etc.
- Categories: include the categories of the current post or archive page.
- Tags: include the tags of the current post or archive page.
- Post title: include the post title of the current post.
- Post ID: include the Post ID of the current post.
- Post date: include the post date.
- Post author name: include the post author name.
- Post author ID: include the post author ID.


== Screenshots ==

1. GTM Kit Dashboard
2. Container settings: container code, server side GTM, and page exclusions
3. Post data settings
4. Google Consent Mode
5. WooCommerce Integration

== Installation ==

1. Install GTM Kit either via the WordPress.org plugin repository or by uploading the files to your server.
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Enter your GTM Container ID and click 'Save changes'.

== Frequently Asked Questions ==

= Where do I get a GTM Container ID? =

Create an account and container in <a href="https://tagmanager.google.com/">Google Tag Manager</a> and get it there.

= Is Google Analytics 4 (GA4) supported? =

Yes! We strive to support the full feature set of Google Analytics 4 and will implement any future features of Analytics 4.

= Is this plugin cache friendly? =

Yes! Pagespeed is one of our main focus points, and we strive to make the plugin compatible with full page caching.

= How can I report security bugs? =

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team help validate, triage and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/vdp/gtm-kit)

== Changelog ==

= Unreleased =

#### Bugfixes:
* The Contact Form 7 "Load JavaScript" setting now shows the recommended choice as selected when the setting has never been saved.
* On the Post data settings, the Post title option showed the Post ID help text and the Post date option was mislabelled "Post data". Both now read correctly.

#### Other:
* New `gtmkit_option_value` filter runs on every option read, including unknown keys, so add-ons can resolve a value from an alternative source (for example a network-level override) without changing the core read path.
* New `gtmkit_shell_v2` filter opts a site into GTM Kit's new settings interface and doubles as a kill-switch; while it is active the settings admin menu collapses to a single entry. The classic settings interface remains the default.

= 2.15.0 =

Release date: 2026-06-12

Find out about what's new in our [our release post](https://gtmkit.com/gtm-kit-2-15/).

#### Bugfixes:
* Security hardening: Links served to the settings interface from remote content (upgrade offers, templates, tutorials) and notifications are now validated before they are used for navigation.

#### Other:
* New `gtmkit_settings_registry` filter lets add-ons register their settings fields with the GTM Kit settings screen at runtime. The settings screen now exposes its field registry and related metadata, preparing for GTM Kit's new settings interface.

= 2.14.1 =

Release date: 2026-06-03

A maintenance fix for the 2.14 line; see the [2.14 release post](https://gtmkit.com/gtm-kit-2-14/) for what 2.14 introduced.

#### Bugfixes:
* WooCommerce block tracking now loads on block (FSE) themes where Cart, Checkout, Mini Cart, Product Collection, or Related Products are rendered from block templates and template parts. Previously the block tracking bundle could fail to load on these sites, so block ecommerce events never fired.

= 2.14.0 =

Release date: 2026-06-02

Find out about what's new in our [release post](https://gtmkit.com/gtm-kit-2-14/).

#### New:
* New "Engagement events" settings section emits GA4 standard `login`, `sign_up`, `search`, and `generate_lead` events out of the box. Each event has its own toggle and defaults to on, so customers see the events the moment they upgrade.
* Rebuilt WooCommerce block tracking on stable data-store APIs. Cart, Checkout, Mini Cart, All Products, Product Collection, Single Product, Related Products, the Cart block cross-sells, and product filter blocks now all emit ecommerce events end to end, including add_to_cart and view_cart from the Mini Cart, list and select tracking for the All Products grid and cart cross-sells, and view_item_list re-fires when a filter or pagination control updates a Product Collection.

#### Bugfixes:
* The Contact Form 7 integration now loads reliably on form pages when "Load JavaScript" is set to the recommended "Only on pages where the Contact Form 7 script is registered" mode, even when a performance plugin (e.g. WP Rocket) defers Contact Form 7's own scripts until shortcode render. Previously the integration could be skipped on legitimate form pages and `gtmkit.CF7MailSent` would not fire.

#### Other:
* New developer filters let extensions tag the method, normalise the search term, assign a lead value, rename the handoff cookie, veto any event, or opt custom search templates into the `search` event.
* New `gtmkit_blocks_supported` filter lets developers add custom block names to the list that loads GTM Kit's block tracking.
* Raised the minimum WooCommerce version to 10.3 for the new block tracking integration. Sites on earlier WooCommerce continue to receive classic-template tracking unchanged.
* Added Vitest and Playwright test harnesses covering the block tracking path, plus PHPUnit coverage for the block detection and Store API extension.
* Prepare the settings and setup-wizard bootstrap for React 19, which WordPress will ship in a future release. No behaviour change under the current React 18.

= 2.13.1 =

Release date: 2026-05-26

A maintenance fix for the 2.13 line; see the [2.13 release post](https://gtmkit.com/gtm-kit-2-13/) for what 2.13 introduced.

#### Bugfixes:
* The "Exclude pages from GTM" feature now also holds back the WooCommerce, Contact Form 7, and Easy Digital Downloads tracking scripts on excluded pages. Previously those add-on scripts could still load on an excluded page and fail, because the core GTM Kit runtime they rely on was withheld there.

= 2.13.0 =

Release date: 2026-05-26

Find out about what's new in our [release post](https://gtmkit.com/gtm-kit-2-13/).

#### New:
* New "Exclude pages from GTM" section on the Container settings page lets you list URL patterns where GTM Kit should stay off. Useful for third-party checkout iframes, partner-hosted subpages, or in-app webview routes that have their own tracking.
* New `window.gtmkit.events.push()` helper now sits in front of every GTM Kit event push, so an add-on can defer consent-sensitive events in the browser without server-side suppression.

#### Other:
* The existing `gtmkit_container_active` filter now receives the actual computed container-active value instead of a hardcoded `true`, so callbacks that return the value through unchanged automatically honor the new URL exclusion.
* PHP-rendered initial dataLayer content is now emitted through the same client helper, so deferral works the same on full-page-cached and uncached pages.

= 2.12.0 =

Release date: 2026-05-19

Find out about what's new in our [release post](https://gtmkit.com/gtm-kit-2-12/).

#### New:
* New welcome modal greets fresh installs on their first GTM Kit admin page and links to the documentation. Existing installs are not interrupted.
* GTM Kit can now surface launch and upgrade announcements from gtmkit.com without a plugin release.

#### Bugfixes:
* Prevent a fatal error on WooCommerce shop and archive pages when another plugin (e.g. WP Grid Builder) re-runs the product loop without a current product in context. GTM Kit now skips its hidden product-data tag instead of crashing the page.
* No more "headers already sent" PHP warning when running WP-CLI commands on sites that use the Cookie Keeper option.

#### Other:
* New `gtmkit_introductions` filter and `Introduction_Interface` contract let add-ons register their own announcement modals through a documented public API.

= Earlier versions =
For the changelog of earlier versions, please refer to [the changelog on gtmkit.com](https://gtmkit.com/changelog/).

