=== GTM Kit - Google Tag Manager & GA4 integration ===
Contributors: tlamedia, torbenlundsgaard, gtmkit
Donate link: https://github.com/tlamedia/gtm-kit
Tags: google tag manager, gtm, woocommerce, analytics, ga4
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 2.18.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Google Tag Manager and GA4 integration. Including WooCommerce data for Google Analytics 4 and support for server side GTM.

== Description ==

GTM Kit puts the Google Tag Manager container code on your website so that you don't need to touch any code. It also pushes data from WooCommerce, Easy Digital Downloads (EDD) and Contact Form 7 to the data layer for use with for Google Analytics 4, Facebook and other GTM tags.

The goal of GTM Kit is to provide a flexible tool for generating the data layer for Google Tag Manager. It is easy to use and doesn't require any coding, but it allows developers to customize the plugin as needed.

The settings are organised around what you are trying to do (Setup, Events & data layer, Commerce, Consent & privacy, and Tools), so related options live together and the setting you need is quick to find.

## Know when your tracking breaks

Tracking fails quietly. A caching plugin strips the container out of the page, a second plugin loads it a second time, a staging copy reports into your live property, and nothing on the settings screen says so.

GTM Kit checks one of your own pages once a day, the way a visitor receives it, and tells you when nothing on your site is loading your container, or when your pages load tracking twice. Where it recognises the plugin or tool adding the second copy, it names it.

Two checks in WordPress's own Site Health screen report whether your container is set up and reaching your pages, and whether consent is configured. A GTM Kit section on the Info tab lists your whole configuration on one screen and copies it into a support request with one click.

On sites WordPress reports as staging, development or local, GTM Kit leaves the container out, so test traffic never reaches your live analytics. The data layer is still built there, and a setting loads the container anyway when you are measuring a test site on purpose.

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

## Moving from another Google Tag Manager plugin

GTM Kit imports settings from Google Tag Manager for WordPress, Google Tag Manager for WooCommerce, Metronet Tag Manager and other GTM plugins, at any time, from the Tools page. Your container ID, data layer variables, Consent Mode defaults, excluded user roles and container environment come across in one step.

Before anything is written you see exactly which of your settings will be replaced, and only settings the other plugin actually configured are touched.

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
6. Tools: import settings from another Google Tag Manager plugin
7. Site Health: GTM Kit's container and consent checks

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

= 2.18.0 =

Release date: 2026-08-24

Find out about what's new in our [our release post](https://gtmkit.com/changelog/gtm-kit-2-18/).

#### New:
* GTM Kit now checks one of your pages once a day and tells you when nothing on your site is loading your container, or when your pages load tracking twice.
* GTM Kit now reports on itself in WordPress's Site Health, with checks for your container, your consent setup, and what the daily page check found.
* GTM Kit no longer loads your container on sites WordPress reports as staging, development or local, so test traffic stays out of your analytics.
* You can now import settings from another Google Tag Manager plugin at any time from the Tools page, not only during setup.

#### Bugfixes:
* The fallback for visitors who have JavaScript turned off is now added to your pages. It was missing on every placement setting, and you can switch it off under "Container code noscript implementation".
* On a block theme, adding a product to the cart from the product page no longer reloads the page.
* Importing settings during the setup wizard works again, reads the right customer data setting, and no longer produces an unusable container ID on sites with more than one container.

#### Other:
* The footer fallback now sits at the standard WordPress footer position. If you added a body_footer hook to your theme to make that option work, you no longer need it.
* GTM Kit now requires WordPress 6.9 or later, and is tested with WordPress 7.1.

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

= Earlier versions =
For the changelog of earlier versions, please refer to [the changelog on gtmkit.com](https://gtmkit.com/changelog/).

