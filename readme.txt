=== GTM Kit ===
Contributors: tlamedia, torbenlundsgaard
Donate link: https://github.com/tlamedia/gtm-kit
Tags: google tag manager, gtm, tag manager, woocommerce, analytics, ga4, gtag, pagespeed
Requires at least: 5.5
Tested up to: 6.1
Requires PHP: 7.2
Stable tag: 1.3.3
License: GPL v3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Flexible tool for generating the data layer for Google Tag Manager. Including WooCommerce data for Google Analytics 4 and support for server side GTM.

== Description ==

The goal of GTM Kit is to provide a flexible tool for generating the data layer for Google Tag Manager. Including WooCommerce data for Google Analytics 4.

## Flexible container implementation

Depending on how you use Google Tag Manager you can delay the loading of the container script until the browser is idle. You can furthermore extend te delay with a timer. This may be relevant to you be if are focusing on pagespeed.

You may enter a custom domain name if you are using a custom server side GTM (sGTM) container for tracking. It's also possible to specify a custom loader.

## Post data

You may specify which post data elements you wish to include in the dataLayer for use in Google Tag Manager.
- Post type: include the type of the current post or archive page.
- Categories: include the categories of the current post or archive page.
- Tags: include the tags of the current post or archive page.
- Post title: include the post title of the current post.
- Post ID: include the Post ID of the current post.
- Post date: include the post date.
- Post author name: include the post author name.
- Post author ID: include the post author ID.

## eCommerce events tracked with Google Analytics 4

When the WooCommerce integration is activated the following GA4 events are automatically included in the dataLayer:

- view_item_list
- select_item
- view_item
- add_to_cart
- view_cart
- remove_from_cart
- begin_checkout
- add_shipping_info
- add_payment_info
- purchase

== Screenshots ==

1. Google Tag Manager container
2. Google Tag Manager container code
3. GTM Server Side
4. WooCommerce Integration

== Installation ==

1. Install GTM Kit either via the WordPress.org plugin repository or by uploading the files to your server.
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Enter your GTM Container ID and click 'Save changes'.

== Frequently Asked Questions ==

= Where do I get a GTM Container ID? =

Create an account and container in <a href="https://tagmanager.google.com/">Google Tag Manager</a> and get it there.

= Is Google Analytics 4 (GA4) supported? =

Yes! We strive to support the full feature set of Google Analytics 4 and will implement any future features of Analytics 4.

= Is Google Universal Analytics supported? =

Google Analytics 3 (Universal Analytics) properties will stop collecting data starting July 1, 2023. GTM Kit does not support Enhanced ecommerce with Google Analytics 3 (Universal Analytics).
It’s recommended that you create a Google Analytics 4 property instead.

= Is this plugin cache friendly? =

Yes! Pagespeed is one of our main focus points and we strive to make the plugin compatible with full page caching.

== Changelog ==

= 1.X =

Enhancements:

* Added options to add user data to the datalayer (logged in, user ID, user role).

= 1.3.3 =

Bugfixes:

* Fix custom datalayer name in CF7.

Other:

* WordPress tested up to 6.1
* WooCommerce tested up to: 7.0.

= 1.3.1 =

Bugfixes:

* A custom container domain would not be used in the noscript container.

= 1.3 =

Enhancements:

* Added compatibility with WooCommerce High-Performance Order Storage.
* Added DNS prefetch for the Google Tag Manager host.
* Added Contact Form 7 Integration.
* New admin dashboard.
* New admin navigation.

Other:

* Cleaned up and formatted code.

= 1.2.1 =

Bugfixes:

* Fix a bug where pageType is not included unless post type is included

= 1.2 =

Enhancements:

* Option to include the defined page type. I.e. post, page, product, category, cart, checkout etc.

= 1.1 =

Enhancements:

* Option to include the permalink structure of the product base, category base, tag base and attribute base in the datalayer.
* Option to include the path of cart, checkout, order received adn my account page in the datalayer.

Bugfixes:

* Fixes a bug where an aggressive wp_kses filter prevents add to cart tracking on product catagory pages.

Other:

* Disable WooCommerce settings if WooCommerce is not active
* Update logo.

= 1.0 =
* First public release

== Upgrade Notice ==

= 1.0 =
The plugin has been used in production for a year and is considered stable. This is the first public release.
