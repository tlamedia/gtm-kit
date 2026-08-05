=== GTM Kit - Google Tag Manager & GA4 integration ===
Contributors: tlamedia, torbenlundsgaard, gtmkit
Donate link: https://github.com/tlamedia/gtm-kit
Tags: google tag manager, gtm, woocommerce, analytics, ga4
Requires at least: 6.8
Tested up to: 7.0
Stable tag: 2.17.0
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

= 2.17.0 =

Release date: 2026-08-05

Find out about what's new in our [our release post](https://gtmkit.com/changelog/gtm-kit-2-17/).

#### New:
* Sharing system data with the support team now starts a live sync session: while your ticket is open (at most 7 days), saving GTM Kit settings automatically sends the support team a fresh copy of the same data. The Support page shows an indicator while sync is active, and a Stop sharing button ends it immediately.
* A new Premium page in the settings screen explains what GTM Kit Premium adds on top of the free plugin, covering server-side tracking, purchase accuracy, consent-safe measurement, forms and subscriptions, and debugging tools, with a link to the documentation behind each point. Cards are ordered to match your site, so a WooCommerce store sees the commerce topics first.
* The setup wizard now includes a short step introducing GTM Kit Premium, worded for the site it is running on: order tracking for WooCommerce stores, consent handling for sites in the EU and EEA, and a general overview otherwise. The step is informational and one click continues past it.

#### Bugfixes:
* Adding a product from a block product grid on a page that also shows the cart or Mini Cart no longer reports add_to_cart twice. The event also keeps the name of the product list it came from, which was missing from the second, duplicate event.
* On block themes, product lists no longer report every view and every add to cart twice. WooCommerce runs the classic product-loop hooks inside its block templates so older plugins keep working, and GTM Kit was responding both there and through its own block tracking, which doubled view_item_list and add_to_cart on shop, category and tag pages. List names are unchanged, so existing reports stay comparable.
* Removing a product from the cart now sends the remove_from_cart event again. The product details attached to the cart's remove link were encoded twice, so the browser could not read them and the event was silently skipped on the classic cart page.

#### Other:
* GTM Kit is now tested with WooCommerce 11.0. Shop, cart, checkout and purchase tracking were verified against the new release on both classic and block themes.
* Customers who already have GTM Kit Woo or GTM Kit Premium no longer see upgrade prompts anywhere. The Premium page and the wizard step are hidden entirely, and settings that need a paid add-on no longer show an upgrade link. Those settings still appear with their Premium label, so you can see what the product includes without being sold something you already own.
* New `gtmkit_support_sync_config` filter lets developers tune the support sync timings (coalesce delay, session cap, and status-check interval).
* Added a non-blocking continuous-integration check that runs the settings-app test suite against React 19, so the admin interface is verified ahead of WordPress bundling React 19 in a future core release.
* Building the settings screen now regenerates the compiled Tailwind stylesheet automatically, so new interface styling can no longer be silently missing from a build.
* The plugin's WooCommerce integration is now covered by an automated test suite that runs against a real WooCommerce install, and the suite runs against the oldest supported WordPress and WooCommerce versions as well as the newest. Faults in shop, cart and checkout tracking are caught before release instead of in the browser, and the compatibility stated in the plugin header is verified on every change rather than assumed.

= 2.16.4 =

Release date: 2026-06-29

Find out about what's new in our [our release post](https://gtmkit.com/changelog/gtm-kit-2-16/).

#### Bugfixes:
* When the Template Assistant cannot generate a container, the page now shows the reason reported by the server inline (and logs the full detail to the browser console), instead of a generic "Error generating template" message that hid what actually went wrong.

#### Other:
* The settings screen now ships an sGTM Preview test-send control that GTM Kit Premium registers into the Setup → Environment section, so Premium users can send a server-side webhook event to their server container's Preview/Debug panel.
* The Event Deferral setting no longer warns about Consent Mode when a consent platform supplies consent through the WP Consent API. The notice now appears only when neither Consent Mode nor the WP Consent API can release deferred events, and its wording names both consent sources instead of implying Consent Mode is required.

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

= Earlier versions =
For the changelog of earlier versions, please refer to [the changelog on gtmkit.com](https://gtmkit.com/changelog/).

