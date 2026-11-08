=== GTM Kit - Google Tag Manager & GA4 integration ===
Contributors: tlamedia, torbenlundsgaard, gtmkit
Donate link: https://github.com/tlamedia/gtm-kit
Tags: google tag manager, gtm, woocommerce, analytics, ga4
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 2.24.0-rc2
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

= 2.24.0 =

Release date: 2026-11-10

Find out about what's new in [our release post](https://gtmkit.com/changelog/gtm-kit-2-24/).

#### New:
* AI assistants connected through the WordPress Abilities API can now change a short list of GTM Kit settings, such as the container ID, consent defaults and integrations. Every change is previewed first and only applied with the one-time code from that preview. This is off until you turn it on with the gtmkit_write_abilities_enabled filter.
* Product variations in the data layer now also carry their parent product's ID, in a field named item_group_id and in the same format as the item ID, so a variation can be tied back to its product in reports and in Merchant Center feeds.
* Open any GTM Kit settings section, the setup wizard or Google Tag Manager from the WordPress command palette (Ctrl+K or Cmd+K).

#### Bugfixes:
* GTM Kit's abilities now reach AI assistants on sites where another plugin loads an older copy of the MCP Adapter.
* The consent status that AI assistants and Site Health report is now the default a new visitor gets, not the consent of the administrator viewing it.
* Saving the settings no longer fails when a switch was stored as the text "true" or "false", which can happen when a setting is changed outside the settings screen. The save now stores it as a proper on or off value, and a Google tag gateway stored as "false" is saved as off.
* A search from the Product Search block is now counted once. Block themes counted it twice, and classic themes lost it when engagement search tracking was off.
* A product search with a single match, which WooCommerce sends straight to the product page, is now counted as a search when engagement search tracking is on.
* Searches from the Product Search block that WooCommerce now adds as a version of the WordPress Search block are counted when engagement search tracking is off.
* The data layer name is now checked when you save it. A name that is not a valid JavaScript name, such as one with a space, is refused instead of breaking the container on every page.
* Importing from GTM4WP now brings over a server-side domain that uses GTM4WP's default script path, and a re-import clears a domain an earlier import saved but could not use.
* The Stape loader notice now also appears when a saved loader no longer matches your settings, and the Google tag gateway's custom-domain message says the Stape loader is off too.

#### Other:
* When an AI assistant previews a change to the data layer name, the preview now warns that other scripts using the old name will need updating.
* Clearer consent advice in Site Health.

= 2.23.1 =

Release date: 2026-11-02

Find out about what's new in [our release post](https://gtmkit.com/changelog/gtm-kit-2-23/).

#### Bugfixes:
* With "Just the container" turned on, GTM Kit outputs the Google Tag Manager container again. It was left out of the page, so no tags fired.

= 2.23.0 =

Release date: 2026-10-27

Find out about what's new in [our release post](https://gtmkit.com/changelog/gtm-kit-2-23/).

#### New:
* The GTM Kit settings screen now checks that your browser can reach your site's settings API when it opens, and shows a notice if it cannot, if your site answers with an error, or if it is slow to answer. The notice explains the usual causes, such as a browser extension, ad blocker, VPN or firewall, and lets you copy the details for support. The check talks only to your own site and sends nothing anywhere else.

#### Other:
* The event deferral warning in the settings, shown when GTM Kit cannot see a consent state, now names the two fixes that work with a consent banner running inside Google Tag Manager: the GTM Kit consent bridge tag in the container, or a consent plugin that supports the WP Consent API. It no longer suggests turning on Consent Mode.

= 2.22.0 =

Release date: 2026-10-13

Find out about what's new in [our release post](https://gtmkit.com/changelog/gtm-kit-2-22/).

#### New:
* Consent set by a consent platform that runs inside Google Tag Manager can now reach GTM Kit's purchase consent check through GTM Kit's consent bridge template, which you can add to your container from the Template Assistant. The template reports the visitor's consent state to GTM Kit through Google Tag Manager's documented template interfaces.
* System data you share with GTM Kit support now includes the installed versions of GTM Kit Premium and the GTM Kit Woo Add-On, so support can see which build a site runs. The anonymised site data is unchanged.

#### Bugfixes:
* Product variations loaded over admin-ajax, which some themes use instead of the WooCommerce variation form, now carry the tax-resolved price GTM Kit adds to every variation, so the variation events those themes send report the same price as the rest of the data layer.

#### Other:
* Add-ons can now add data stored on a cart line or an order line to the items of the cart, checkout and purchase events through two new filters that receive the WooCommerce line next to the item.
* The cart tracking for WooCommerce blocks now keeps the last reported cart in one shared place (window.gtmkit.wc.cartSnapshot), so a cart change another script has already recorded there is not reported again by the block tracking.

= Earlier versions =
For the changelog of earlier versions, please refer to [the changelog on gtmkit.com](https://gtmkit.com/changelog/).

