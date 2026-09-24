=== GTM Kit - Google Tag Manager & GA4 integration ===
Contributors: tlamedia, torbenlundsgaard, gtmkit
Donate link: https://github.com/tlamedia/gtm-kit
Tags: google tag manager, gtm, woocommerce, analytics, ga4
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 2.20.2
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

WordPress's own Site Health screen reports on GTM Kit directly: whether your container is set up and reaching your pages, whether consent is configured, whether GTM Kit's settings can be saved at all, and, if you serve the Google tag from your own domain, whether that is still working. A GTM Kit section on the Info tab lists your whole configuration on one screen and copies it into a support request with one click.

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


## Serve the Google tag from your own domain

Ad blockers and browser tracking restrictions treat a script loaded from Google differently from one loaded by your own site. GTM Kit can serve the Google tag from your own domain instead, so the tag loads where a request to Google's domain would have been blocked. The measurement the tag sends still goes to Google; moving that to your domain as well needs Google's tag gateway through a CDN or host that supports it.

Before you can switch it on, GTM Kit checks that your server can reach Google and that the address it would serve the tag from is actually reachable, and it repeats both checks once a day afterwards. If either stops working, GTM Kit goes back to loading the container the standard way and tells you, so your tracking never stops without warning.

It is off by default, and it is an alternative to pointing GTM Kit at your own server-side container domain rather than something you run alongside one.

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
7. Site Health: GTM Kit's checks for the container, consent, settings and tag serving

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

== External services ==

= Stape =

If your server-side Google Tag Manager container is hosted on Stape, GTM Kit can use the loader Stape issues for it. This is off by default. When you switch on "Get the loader from Stape" under Server-side Tagging, GTM Kit asks Stape's API (api.app.stape.io, or api.app.eu.stape.io for containers in Stape's EU region) for the loader.

The request is sent only when you save a change to the settings the loader depends on, or press "Refresh loader". It is never sent on a schedule or when a visitor views a page. It contains your sGTM container identifier, your GTM container ID, your sGTM container domain, your data layer name and, when Cookie Keeper is on, the name of the Cookie Keeper cookie. No visitor data is sent.

Stape terms of service: https://stape.io/terms-conditions
Stape privacy policy: https://stape.io/privacy-notice

== Changelog ==

= 2.20.2 =

Release date: 2026-09-24

Find out about what's new in our [our release post](https://gtmkit.com/changelog/gtm-kit-2-20/).

#### Bugfixes:
* If you use the loader Stape issues, GTM Kit no longer uses a loader stored by an earlier version, because it cannot tell whether that loader was checked against your data layer name. Your pages use the standard loader until you click Refresh under Server-side Tagging, which gets a checked loader from Stape.

= 2.20.1 =

Release date: 2026-09-22

Find out about what's new in our [our release post](https://gtmkit.com/changelog/gtm-kit-2-20/).

#### Bugfixes:
* If refreshing or pasting your Stape loader fails, the settings screen now names the loader your pages keep using, instead of claiming they fell back to the standard loader. A request that fails before reaching Stape, for example because your login has expired, now says so instead of reporting that Stape could not be reached.
* If saving your settings fails because the connection to your site dropped, the settings screen now says there is a network problem instead of reporting a server error.
* If activating your licence fails because the request itself failed, for example because the connection dropped, the licence screen now says so instead of telling you to check your licence key. If deactivating fails, the button no longer keeps spinning: the screen shows what went wrong and lets you try again.
* If a Stape loader is made for a different data layer name than GTM Kit uses, whether you paste it or GTM Kit gets it from Stape, the settings screen now refuses it and says why, instead of using a loader that listens to the wrong data layer.

= 2.20.0 =

Release date: 2026-09-22

Find out about what's new in our [our release post](https://gtmkit.com/changelog/gtm-kit-2-20/).

#### New:
* If your server-side container is hosted on Stape, GTM Kit can now use the loader Stape issues for it, which ad blockers find harder to recognise. Switch it on under Server-side Tagging. GTM Kit asks Stape for the loader only when you save or refresh it, and you can paste the code from Stape instead.

#### Bugfixes:
* With Cookie Keeper enabled, Safari visitors now load your Stape custom loader when its identifier is longer than eight characters, as every identifier Stape issues today is. Safari previously asked Stape for a loader address it rejects, so those visitors were not tracked.
* On sites with a persistent object cache such as Redis, GTM Kit no longer re-runs its update routine on every admin page load when the cache still holds the previous version number. The update routine also no longer logs a notice from Action Scheduler on its first run.

#### Other:
* The description of serving the Google tag from your own domain now says what the setting does and does not do: it changes where the tag loads from, while the measurement it sends still goes to Google unless Google's tag gateway is provided by your CDN or host.

= 2.19.0 =

Release date: 2026-09-14

Find out about what's new in our [our release post](https://gtmkit.com/changelog/gtm-kit-2-19/).

#### New:
* When Google for WooCommerce adds a Google tag beside your container, GTM Kit now names it as the source instead of saying it could not tell.
* Site Health now checks that GTM Kit's settings can be saved, and shows the error it got back when they cannot.
* When sending your system data from the Support screen cannot get through, the screen now says so instead of claiming your ticket was not found. With GTM Kit Premium you can then copy the data or download it to email instead.
* You can now serve the Google tag from your own domain, so ad blockers and browser tracking restrictions interfere less with loading it. If it stops working, GTM Kit falls back to the standard loader and tells you. It is off by default, and an alternative to your own sGTM container domain. (Corrected after publication: this entry originally said restrictions would interfere less with your measurement. The setting changes where the tag loads from. The measurement the tag sends still goes to Google unless your CDN or host provides Google's tag gateway, which this setting cannot switch on.)
* GTM Kit now points out setups that leave data unmeasured, such as a WooCommerce store with its ecommerce events switched off. At most one such notice appears at a time, and dismissing it keeps it away for 90 days.

#### Bugfixes:
* A Google tag loading beside your container is no longer reported as duplicate tracking in the dashboard. It is now a notice, since the tag is only counted twice when it also fires inside your container. Two containers, or the same container loaded twice, are still reported as problems.
* When settings will not save, the settings screen now tells you why instead of quietly showing your old values again. It names any rejected setting, and on a site with a persistent object cache it names the cache as the likely cause.
* Password managers no longer fill in GTM Kit's settings fields. A filled-in value could previously be saved without you typing it, and came back after you removed it.
* Event names such as purchase and add_to_cart can no longer be renamed by translations in the settings screens, where they disagreed with the events GTM Kit actually sends.
* The order confirmation page now sends purchase data only to visitors WooCommerce or Easy Digital Downloads would show the order to. Anyone opening a shared or guessed link previously received the order contents, its value and the shopper's details. The buyer's own visit still reports the purchase.

#### Other:
* New `gtmkit_active_cmp` and `gtmkit_cmp_display_name` filters let a site declare a consent platform GTM Kit cannot detect, such as one loaded by the theme or a code snippet, so Site Health stops reporting consent as unconfigured.
* The customer details sent with a purchase now come from the order itself, so they describe the buyer rather than whoever opened the confirmation page.
* `WooCommerce::include_customer_data()` now takes the order as its second argument, before the order value, and reads every customer field from it. Code that calls this method directly must pass the order.

= 2.18.1 =

Release date: 2026-08-24

Find out about what's new in our [our release post](https://gtmkit.com/changelog/gtm-kit-2-18/).

#### Bugfixes:
* The daily check of your pages now keeps running on schedule on WooCommerce sites. It could previously stop after one run until an administrator next opened wp-admin.
* On a site using a Google Tag Manager environment, the fallback for visitors without JavaScript pointed at your live container instead of the environment. It now matches the rest of your setup.

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

= Earlier versions =
For the changelog of earlier versions, please refer to [the changelog on gtmkit.com](https://gtmkit.com/changelog/).

