=== GTM Kit - Google Tag Manager & GA4 integration ===
Contributors: tlamedia, torbenlundsgaard, gtmkit
Donate link: https://github.com/tlamedia/gtm-kit
Tags: google tag manager, gtm, woocommerce, analytics, ga4
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 2.23.0
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

= 2.21.0 =

Release date: 2026-10-06

Find out about what's new in [our release post](https://gtmkit.com/changelog/gtm-kit-2-21/).

#### New:
* AI assistants connected to your site through the WordPress Abilities API can now read how GTM Kit is set up, which consent defaults apply, and whether tracking is healthy. Only administrators can use them, nothing can be changed through them, and they send nothing off your site.
* If you use the loader Stape issues but GTM Kit has none stored for your site, the GTM Kit dashboard now tells you that your pages use the standard loader and how to get Stape's loader back with Refresh.

#### Bugfixes:
* If the Google tag gateway is switched on while a custom server-side tagging domain is set, the GTM Kit dashboard now says the two cannot be used together and how to choose between them, instead of reporting that the gateway is not working. The settings screen explains why one of the two is unavailable only while it is.
* Site Health no longer reports that consent is configured when the Consent Mode defaults deny analytics and no consent platform was found to grant it, a setup where every visitor is measured without cookies. It now recommends adding a consent platform or changing the defaults, and the Consent settings page shows the same warning.
* Site Health no longer says consent is not configured when the Consent Mode defaults are off because a consent integration, such as one using the WP Consent API, sets and updates the visitor's consent.
* Importing settings from GTM4WP no longer sets a server-side tagging domain when GTM4WP loads the container from a custom path, which produced a loader address that did not serve your container. The import now says the domain was skipped.

#### Other:
* On stores with a classic theme, shop, product and category pages without WooCommerce blocks no longer load GTM Kit's block tracking script, so those pages load less JavaScript. Pages that show a WooCommerce block anywhere, including a Mini Cart in a widget area, still load it, and block themes are unchanged.
* If you send customer details with the purchase event and use Consent Mode, GTM Kit now adds the email, phone, name and address, hashed or not, only once the visitor has granted both advertising storage and advertising user data consent. If consent is denied, or a denying default has not been lifted within three seconds, the purchase is still sent, without those details. GTM Kit reads consent from its own data layer, from `dataLayer` if that is a different one, and from the consent state Google Tag Manager holds, so consent a consent platform sets through its Google Tag Manager template counts too. A denial in any of them withholds the details. If GTM Kit finds no consent state at all within three seconds, it sends the details, as it did before, and a developer can make GTM Kit withhold them in that case with a filter.

= Earlier versions =
For the changelog of earlier versions, please refer to [the changelog on gtmkit.com](https://gtmkit.com/changelog/).

