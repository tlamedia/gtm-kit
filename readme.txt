=== GTM Kit - Google Tag Manager integration ===
Contributors: tlamedia, torbenlundsgaard
Donate link: https://github.com/tlamedia/gtm-kit
Tags: google tag manager, gtm, woocommerce, analytics, ga4, gtag, easy digital downloads
Tested up to: 6.4
Stable tag: 1.17.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Flexible tool for generating the data layer for Google Tag Manager. Including WooCommerce data for Google Analytics 4 and support for server side GTM.

== Description ==

GTM Kit puts the Google Tag Manager container code on your website so that you don't need to touch any code. It also pushes data from WooCommerce, Easy Digital Downloads (EDD) and Contact Form 7 to the data layer for use with for Google Analytics 4, Facebook and other GTM tags.

The goal of GTM Kit is to provide a flexible tool for generating the data layer for Google Tag Manager. It is easy to use and doesn't require any coding, but it allows developers to customize the plugin as needed.

## eCommerce events tracked with Google Analytics 4
The following GA4 events are automatically included in the dataLayer:

### WooCommerce
- view_item_list
- select_item
- view_item
- add_to_wishlist
- add_to_cart
- view_cart
- remove_from_cart
- begin_checkout
- add_shipping_info
- add_payment_info
- purchase

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

== Changelog ==

= 1.17.2 =

Release date: 2023-12-13

#### Bugfixes:
* Fixes an edge case where the loop index was not defined in WooCommerce product categories.

#### Other:
* Tested up to WooCommerce 8.4

= 1.17.1 =

Release date: 2023-12-05

#### Bugfixes:
* Fix missing datalayer when using Google Consent Mode default settings.
* Easy Digital Downloads assets where enqueued with wrong filename.

= 1.17.0 =

Release date: 2023-12-04

Find out about what's new in our [our release post](https://gtmkit.com/gtm-kit-1-17/).

#### Enhancements:
* Added option to specify the Google Tag Manager environment.
* Performance optimization through optimized database queries and general code improvements.
* Added a filter to deactivate the container, so the container can be deactivated on specific pages.
* Improved support of the 'add_to_wishlist' event. Themes and plugins just have to add the class 'add_to_wishlist' on the 'Add to wishlist' button.

#### Bugfixes:
* Add missing _sbp cookie in the cookie keeper.
* The constant GTMKIT_EDD_DEBUG_TRACK_PURCHASE and GTMKIT_WC_DEBUG_TRACK_PURCHASE was not overriding correct.
* Because of recent changes in WooCommerce clicks on grouped products in a product list would be treated as add_to_cart and not select_item.
* Clicks on variation products and grouped products in product lists was treated as add_to_cart events and are now select_item events as they should be.
* If a product ID prefix was used the 'add_to_cart' event did not work on grouped products.

#### Other:
* Refactoring code for simplicity and maintainability

= 1.16.2 =

Release date: 2023-11-15

#### Bugfixes:

* Fix critical JS bug.

= 1.16 =

Release date: 2023-11-15

Find out about what's new in our [our release post](https://gtmkit.com/gtm-kit-1-16/).

#### Enhancements:

* Add option to include customer data from Easy Digital Downloads on the purchase event.
* Added the filter 'gtmkit_datalayer_script', which allows you to filter the datalayer script.
* Added the constant 'GTMKIT_EDD_DEBUG_TRACK_PURCHASE', which allows users of Easy Digital Downloads to force tracking of purchase event on every page refresh for debugging.

#### Bugfixes:

* Billing state and shipping stat was not included in the customer data on the 'purchase' event.

#### Other:

* Tested up to WordPress 6.4
* Tested up to WooCommerce 8.3

= 1.15 =

Release date: 2023-10-24

Find out about what's new in our [our release post](https://gtmkit.com/gtm-kit-1-15/).

#### Enhancements:

* Added support for the WooCommerce block 'all-products'.
* Added support for Stape.io Cookie Keeper .
* Inline scripts are now registered with the wp_add_inline_script insted of wp_head. This allows easy extension GTM Kit and implementation of a CSP (Content Security Policy).

#### Bugfixes:

* The product ID Prefix was not added when a product variation was selected.
* Tax was not added on the total on the add_shipping_info and add_payment_info events.

= Earlier versions =
For the changelog of earlier versions, please refer to [the changelog on gtmkit.com](https://gtmkit.com/changelog/).
