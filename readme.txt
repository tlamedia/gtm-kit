=== GTM Kit - Google Tag Manager & GA4 integration ===
Contributors: tlamedia, torbenlundsgaard, gtmkit
Donate link: https://github.com/tlamedia/gtm-kit
Tags: google tag manager, gtm, woocommerce, analytics, ga4
Tested up to: 6.5
Stable tag: 1.20
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

= 1.X =

Release date: 2024-MM-DD

Find out about what's new in our [our release post](https://gtmkit.com/gtm-kit-1-21/).

#### Enhancements:
* Added user-data in the datalayer containing user data formatted for the User-Provided Data variable so you don't have to map it manually.
* Added dynamic template sections that allows for template updates wíthout updating the plugin.
* The tutorial section is now dynamic and based on a feed, so we don't have to update the plugin to include new tutorials.

#### Bugfixes:

#### Other:
* Tested up to WooCommerce 8.8.

= 1.20 =

Release date: 2024-03-20

Find out about what's new in our [our release post](https://gtmkit.com/gtm-kit-1-20/).

#### Enhancements:
* Added a new section for GTM templates that you can use to configure the necessary Tags, Triggers and Variables in Google Tag Manager.
* Added support for the 'new_customer' parameter on the 'purchase' event, which is used for customer acquisition reporting and Google Smart Shopping campaigns.
* Added a debug log for debugging the 'purchase' event without make a purchase.
* Improved the pagespeed of the control panel. The control panel is now loading significantly faster than before.

#### Bugfixes:
* Add missing 'currency' on the 'view_item' event.
* Add missing 'payment_type' on the 'add_payment_info' event.
* On the 'add_shipping_info' and 'add_payment_info' events the items parameter was cast as an object instead of an array as it should be.

#### Other:
* Require WooCommerce 8.0.

= 1.19.1 =

Release date: 2024-03-05

#### Bugfixes:
* Scripts were not enqueued if the container was inactive.

#### Other:
* Tested up to WordPress 6.5.
* Tested up to WooCommerce 8.7.

= 1.19 =

Release date: 2024-02-07

Find out about what's new in our [our release post](https://gtmkit.com/gtm-kit-1-19/).

#### Enhancements:
* Add option to fire a 'load_delayed_js' event, which can be used to delay JavaScript execution in Google Tag Manager.
* This release comes with many behind-the-scenes improvements and general enhancements.
* The script settings are the same on all pages and are now cached in the object cache for better performance.
* The function to share system data with the support team has been improved with more data.
* The code has been refactored for extendability.

= 1.18.1 =

Release date: 2024-01-08

#### Bugfixes:
* Added missing flush of the ecommerce object on the select_item event.
* 1.17 introduced a bug that caused item brand to be limited to a product attribute.

#### Other:
* Tested up to WooCommerce 8.5.
* Minor refactoring.

= 1.18 =

Release date: 2024-01-02

Find out about what's new in our [our release post](https://gtmkit.com/gtm-kit-1-18/).

#### Enhancements:
* Add support for Google Consent Mode v2
* Added a function to share system data with the GTM Kit support team. If you have registered a support request on WordPress.org and the GTM Kit support team has asked you to send your system data you can now do that in a secure way without posting any private information in the support forum.
* Enhanced support for the select_item event in more WordPress themes. This update significantly improves the compatibility of the select_item event handling in themes such as Woodmart.

#### Bugfixes:
* When adding a product to the cart from a product category page the quantity was not specified in the add_to_cart event.
* On Single product pages a click on a quantity control would result in an undefined event.

= Earlier versions =
For the changelog of earlier versions, please refer to [the changelog on gtmkit.com](https://gtmkit.com/changelog/).


== Upgrade Notice ==

= 1.20 =
The parameter 'paymentType' on the event 'add_payment_info' has been renamed to 'payment_type'.
