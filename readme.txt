=== GTM Kit - Google Tag Manager & GA4 integration ===
Contributors: tlamedia, torbenlundsgaard, gtmkit
Donate link: https://github.com/tlamedia/gtm-kit
Tags: google tag manager, gtm, woocommerce, analytics, ga4
Requires at least: 6.8
Tested up to: 7.0
Stable tag: 2.11.0
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
2. Google Tag Manager container code and server side GTM
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

#### New:
* New welcome modal greets fresh installs on their first GTM Kit admin page and links to the documentation. Existing installs are not interrupted.
* GTM Kit can now surface launch and upgrade announcements from gtmkit.com without a plugin release.

#### Bugfixes:
* No more "headers already sent" PHP warning when running WP-CLI commands on sites that use the Cookie Keeper option.

#### Other:
* New `gtmkit_introductions` filter and `Introduction_Interface` contract let add-ons register their own announcement modals through a documented public API.

= 2.11.0 =

Release date: 2026-05-11

Find out about what's new in our [our release post](https://gtmkit.com/gtm-kit-2-11/).

#### New:
* "Exclude tax" toggle now controls every standard e-commerce event the data layer emits: `view_cart`, `begin_checkout`, `purchase`, variation prices on variable product pages (re-fired `view_item` + `add_to_cart`), and the per-item coupon `discount` field.

#### Bugfixes:
* Cart, checkout, variation, and coupon-discount events now follow the "Exclude tax" toggle consistently across `value`, `price`, and `discount` fields. The GTM Kit Woo and GTM Kit Premium add-ons extend the fix to refund and order-paid events in their paired releases.
* Silence the "translation loading triggered too early" notice that WordPress 6.7+ logs against the `gtm-kit` text domain by registering translations at the very start of `init` before any other code can request a translated string.
* Close an edge case where a script-dependency notice could still appear under WordPress 6.9.1+ when a consent or CMP plugin toggled the GTM Kit container active mid-request, by asking the WordPress script registry directly which scripts were actually registered instead of re-evaluating the container gate.

#### Other:
* Heads up: GA4 numbers may move after this update. Stores with prices entered ex-tax and tax-inclusive cart display will see `value` change from ex-tax to inc-tax in cart and checkout events.
* New `gtmkit_resolve_tax_mode` and `gtmkit_resolve_item_discount` filters let developers override the toggle programmatically (per-event or per-context) and override the per-item coupon discount calculation.
* Minimum required WordPress version is now 6.8 (was 6.7). Sites still on WordPress 6.7 won't get this update via the dashboard until they upgrade WordPress.

= 2.10.1 =

Release date: 2026-05-07

Tag-only follow-up to 2.10.0 — completes the consent admin-badge surface alongside the React renderer that already shipped in 2.10.0. See the [2.10 release post](https://gtmkit.com/gtm-kit-2-10/) for the broader context.

#### New:
* New `gtmkit_consent_admin_badges` filter lets add-ons (e.g. Premium's WP Consent API integration) push status banners onto the Consent settings page so users see immediately when a higher-priority consent source has taken over.

= 2.10.0 =

Release date: 2026-05-06

Find out about what's new in our [our release post](https://gtmkit.com/gtm-kit-2-10/).

#### New:
* New "CMP script attributes" section on the Consent settings page lets you toggle Cookiebot, Iubenda, and CookieYes script-blocking attributes with one click and add a custom attribute for any other CMP — no PHP filters required.
* Fresh installs auto-detect a known CMP plugin (Cookiebot, Iubenda, CookieYes) and pre-select the matching toggle so the right attribute is on from day one.
* New "Script gating" mode on the Consent settings page lets you choose between always loading GTM, letting it load under Consent Mode v2 control, or holding it back entirely until consent is granted. Default stays as "Always load" so existing installs see no change.
* Strong-block mode masks the Google Tag Manager container until visitors consent. Works alongside any CMP and falls back gracefully when no consent signal arrives.
* Power users can override which consent categories must be granted before strong-block mode unmasks GTM via the new `gtmkit_strong_block_required_categories` filter.
* `window.gtmkit.consent.state` exposes the current consent state so partner scripts and integrators can inspect it without subscribing to events.
* New developer hooks let CMP integrations and consent add-ons plug into GTM Kit's consent flow without forking the plugin — sites running Cookiebot, CookieYes, WP Consent API or in-house consent solutions can now feed their state straight into GTM Kit.
* Server-side broadcast `gtmkit_consent_updated` so other plugins can react to consent state changes without polling.
* Per-event `gtmkit_event_should_defer` filter so future deferral features can hold individual events back when consent is missing.

#### Bugfixes:
* Eliminate "dependencies that are not registered: gtmkit-container" warnings logged by WordPress 6.9.1+ on sites that have GTM Kit's container active.

#### Other:
* The Cookiebot script attribute (`data-cookieconsent="ignore"`) is now configurable via Settings → Consent → CMP script attributes. Existing installs keep the attribute on by default to preserve current behavior; turn it off explicitly if you do not use Cookiebot.

= 2.9.0 =

Release date: 2026-04-29

Find out about what's new in our [our release post](https://gtmkit.com/gtm-kit-2-9/).

#### Enhancements:
* Scope Google Consent Mode defaults to specific countries or regions (e.g. DK, DE, US-CA) instead of applying them everywhere. Useful for sites with visitors both inside and outside the EU.
* Consent updates from other plugins or partner scripts can now talk to GTM Kit through a simple JavaScript API, making CMP integrations easier.

#### Bugfixes:
* Webhooks for Server-side Tracking on the WooCommerce integrations page no longer stay locked after entering an sGTM Container Domain on premium installs.

#### Other:
* "Wait For Update" is now a proper number field with a sensible 500 ms default on new installs. Your existing value is kept.
* Clearer warning on the Consent Mode page — if Cookiebot, Complianz, CookieYes, or Cookie Information already handles your consent, leave this setting off.
* Introduced an internal automated test suite (PHPUnit + Vitest) and continuous integration across PHP 7.4–8.4 × WordPress 6.9. No functional change — every future release is now verified by unit and integration tests before shipping, raising the bar on quality and reliability.

= 2.8.4 =

Release date: 2026-04-23

#### Other:
* Declared compatibility with WooCommerce Product Object Caching (`product_instance_caching`) introduced in WooCommerce 10.5. No functional change; resolves the "incompatible plugins" notice in WooCommerce → Settings → Advanced → Features.
* Tested up to WooCommerce 10.7.
* Tested up to WordPress 7.0.

= 2.8.3 =

Release date: 2026-03-18

#### Bugfixes:
* Fix: Add error handling to WooCommerce blocks action handlers to prevent tracking errors from breaking checkout functionality or interfering with third-party plugins.

#### Other:
* Tested up to WooCommerce 10.6.

= 2.8.2 =

Release date: 2026-02-17

#### Bugfixes:
* Fix undefined array key warning for order-received query var in edge cases like certain payment gateway redirects or bot traffic.

= 2.8.1 =

Release date: 2026-01-30

#### Bugfixes:
* Fixes correct detection of the premium plugin.

= 2.8.0 =

Release date: 2026-01-29

#### Enhancements:
* Improved internal handling of plugin settings to make GTM Kit more reliable and easier to maintain, while ensuring full backward compatibility with existing configurations.

#### Other:
* Tested up to WooCommerce 10.5.
* Require WooCommerce 9.5.


= Earlier versions =
For the changelog of earlier versions, please refer to [the changelog on gtmkit.com](https://gtmkit.com/changelog/).

