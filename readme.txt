=== GTM Kit - Google Tag Manager & GA4 integration ===
Contributors: tlamedia, torbenlundsgaard, gtmkit
Donate link: https://github.com/tlamedia/gtm-kit
Tags: google tag manager, gtm, woocommerce, analytics, ga4
Requires at least: 6.8
Tested up to: 7.0
Stable tag: 2.16.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Google Tag Manager and GA4 integration. Including WooCommerce data for Google Analytics 4 and support for server side GTM.

== Description ==

GTM Kit puts the Google Tag Manager container code on your website so that you don't need to touch any code. It also pushes data from WooCommerce, Easy Digital Downloads (EDD) and Contact Form 7 to the data layer for use with for Google Analytics 4, Facebook and other GTM tags.

The goal of GTM Kit is to provide a flexible tool for generating the data layer for Google Tag Manager. It is easy to use and doesn't require any coding, but it allows developers to customize the plugin as needed.

The settings are organised around what you are trying to do (Setup, Events & data layer, Commerce, Consent & privacy, and Tools), so related options live together and the setting you need is quick to find.

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
- order_processing **[Premium]**
- order_completed **[Premium]**
- order_refunded **[Premium]**
- subscription_started **[Premium]**

Unlock all features with [GTM Kit Premium](https://gtmkit.com/).

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
2. Setup: container code, server-side GTM, and page exclusions
3. Events & data layer: post data and GA4 events
4. Consent & privacy: Google Consent Mode and CMP script attributes
5. Commerce: WooCommerce and Easy Digital Downloads tracking

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

= 2.16.3 =

Release date: 2026-06-23

Find out about what's new in our [our release post](https://gtmkit.com/changelog/gtm-kit-2-16/).

#### New:
* The Event Deferral setting now warns when it is switched on while Consent Mode is off, because deferred events have no consent signal to wait on and never release in that state.

#### Bugfixes:
* The Commerce "Brand" selector now lists your product brand taxonomies again, instead of showing only "(not set)". The redesigned settings screen stopped loading the taxonomy and page lists, so the Brand selector (and other taxonomy- or page-based options) appeared empty regardless of how brands were configured.

= 2.16.0 =

Release date: 2026-06-23

Find out about what's new in our [our release post](https://gtmkit.com/changelog/gtm-kit-2-16/).

#### New:
* The settings screen now uses a redesigned, capability-based interface, organising everything into Setup, Events & data layer, Commerce, Consent & privacy, Tools and more.

#### Bugfixes:
* The Contact Form 7 "Load JavaScript" setting now shows the recommended choice as selected when the setting has never been saved.

#### Other:
* Clarified the Debug log setting description so it reflects that it also logs the server-side webhooks GTM Kit sends, not only the purchase event.

= 2.15.0 =

Release date: 2026-06-12

Find out about what's new in our [our release post](https://gtmkit.com/changelog/gtm-kit-2-15/).

#### Bugfixes:
* Security hardening: Links served to the settings interface from remote content (upgrade offers, templates, tutorials) and notifications are now validated before they are used for navigation.

#### Other:
* New `gtmkit_settings_registry` filter lets add-ons register their settings fields with the GTM Kit settings screen at runtime. The settings screen now exposes its field registry and related metadata, preparing for GTM Kit's new settings interface.

= 2.14.1 =

Release date: 2026-06-03

A maintenance fix for the 2.14 line; see the [2.14 release post](https://gtmkit.com/changelog/gtm-kit-2-14/) for what 2.14 introduced.

#### Bugfixes:
* WooCommerce block tracking now loads on block (FSE) themes where Cart, Checkout, Mini Cart, Product Collection, or Related Products are rendered from block templates and template parts. Previously the block tracking bundle could fail to load on these sites, so block ecommerce events never fired.

= 2.14.0 =

Release date: 2026-06-02

Find out about what's new in our [release post](https://gtmkit.com/changelog/gtm-kit-2-14/).

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

A maintenance fix for the 2.13 line; see the [2.13 release post](https://gtmkit.com/changelog/gtm-kit-2-13/) for what 2.13 introduced.

#### Bugfixes:
* The "Exclude pages from GTM" feature now also holds back the WooCommerce, Contact Form 7, and Easy Digital Downloads tracking scripts on excluded pages. Previously those add-on scripts could still load on an excluded page and fail, because the core GTM Kit runtime they rely on was withheld there.

= 2.13.0 =

Release date: 2026-05-26

Find out about what's new in our [release post](https://gtmkit.com/changelog/gtm-kit-2-13/).

#### New:
* New "Exclude pages from GTM" section on the Container settings page lets you list URL patterns where GTM Kit should stay off. Useful for third-party checkout iframes, partner-hosted subpages, or in-app webview routes that have their own tracking.
* New `window.gtmkit.events.push()` helper now sits in front of every GTM Kit event push, so an add-on can defer consent-sensitive events in the browser without server-side suppression.

#### Other:
* The existing `gtmkit_container_active` filter now receives the actual computed container-active value instead of a hardcoded `true`, so callbacks that return the value through unchanged automatically honor the new URL exclusion.
* PHP-rendered initial dataLayer content is now emitted through the same client helper, so deferral works the same on full-page-cached and uncached pages.

= Earlier versions =
For the changelog of earlier versions, please refer to [the changelog on gtmkit.com](https://gtmkit.com/changelog/).

