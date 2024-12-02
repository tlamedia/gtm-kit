=== GTM Kit - Google Tag Manager & GA4 integration ===
Contributors: tlamedia, torbenlundsgaard, gtmkit
Donate link: https://github.com/tlamedia/gtm-kit
Tags: google tag manager, gtm, woocommerce, analytics, ga4
Tested up to: 6.7
Stable tag: 2.2.2
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

== Changelog ==

= 2.2.2 =

Release date: 2024-12-DD

#### Other:
* Tested up to WooCommerce 9.4.
* Tested up to PHP 8.4.

= 2.2.1 =

Release date: 2024-10-29

#### Bugfixes:
* If the cart and checkout endpoints were set to the same page in order to skip the cart the 'checkout' and 'purchase' events would not fire.

#### Other:
* Tested up to WordPress 6.7.

= 2.2.0 =

Release date: 2024-10-16

Find out about what's new in our [our release post](https://gtmkit.com/gtm-kit-2-2/).

#### Enhancements:
* Added notifications and warnings for missing GTM container, debug log usage, auto-update and event inspector usage. These improvements aim to ensure smooth production operations.

#### Other:
* Minor refactoring

= 2.1.0 =

Release date: 2024-10-01

Find out about what's new in our [our release post](https://gtmkit.com/gtm-kit-2-1/).

#### Enhancements:
* Added support for the Bricks theme builder.

#### Bugfixes:
* In some cases other plugins or a theme could block GTM Kit from firing JS event like the one that fires the add_to_cart GTM event.

= 2.0.1 =

Release date: 2024-09-23

#### Bugfixes:
* Fix an edge case that could cause a fatal error in admin_body_class().

= 2.0.0 =

Release date: 2024-09-DD

We are introducing [GTM Kit Woo Add-On](https://jump.gtmkit.com/link/2-30DDC), which is a premium plugin that unlock premium features in GTM Kit.

Find out about what else is new in our [our release post](https://gtmkit.com/gtm-kit-2-0/).

#### Breaking change:
* Starting with GTM Kit version 2.0, the add_to_wishlist event is no longer supported in the free version of GTM Kit.

#### Enhancements:
* A notifications handler has been added to communicate issues and notifications that require the user’s attention.
* Added a warning when other Google Tag Manager plugins that may cause a conflict are active.
* WooCommerce users are advised to install a supported SEO plugin to take advantage of a default category in data layer items.

#### Other:
* Increased PHPStan analysis level to 6, enhancing static code analysis and catching potential issues earlier in the development process.
* Require WordPress 6.3.
* Require WooCommerce 8.3.
* Require PHP 7.4.


== Upgrade Notice ==

= 2.0 =
Starting with GTM Kit version 2.0, the add_to_wishlist event is no longer supported in the free version of GTM Kit.

= Earlier versions =
For the changelog of earlier versions, please refer to [the changelog on gtmkit.com](https://gtmkit.com/changelog/).
