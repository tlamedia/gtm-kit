=== GTM Kit - Google Tag Manager & GA4 integration ===
Contributors: tlamedia, torbenlundsgaard, gtmkit
Donate link: https://github.com/tlamedia/gtm-kit
Tags: google tag manager, gtm, woocommerce, analytics, ga4
Tested up to: 6.5
Stable tag: 1.22.3.1
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

Find out about what's new in our [our release post](https://gtmkit.com/gtm-kit-1-24/).

#### Enhancements:

#### Bugfixes:

#### Other:
* Tested up to WooCommerce 8.9.

= 1.22.3 =

Release date: 2024-05-22

#### Bugfixes:
- The datalayer was not pushed when the container was disabled.

#### Other:
- Improved quality assurance (CI). Thanks [szepeviktor](https://github.com/szepeviktor)

= 1.22.2 =

#### Bugfixes:
- Fix items in add_shipping_info and add_payment_info

= 1.22.1 =

Release date: 2024-05-13

#### Bugfixes:
- wait_for_update was not printed in Google Consent Mode default settings.

= 1.22 =

Release date: 2024-05-08

Find out about what's new in our [our release post](https://gtmkit.com/gtm-kit-1-22/).

#### Enhancements:
- Added an Event Inspector for verifying that events are being pushed to the datalayer. You can now debug events without using GTM preview mode.
- Added advanced GCM settings: 'ads_data_redaction', 'url_passthrough' and 'wait_for_update'.
- Updated the custom container loader for use with Stape.io.

= 1.21 =

Release date: 2024-04-15

Find out about what's new in our [our release post](https://gtmkit.com/gtm-kit-1-21/).

#### Enhancements:
* Added user-data in the datalayer containing user data formatted for the User-Provided Data variable so you don't have to map it manually.
* Added Google Tag Manager templates for Google Ads and Facebook.
* Added dynamic template sections that allows for template updates wíthout updating the plugin.
* The tutorial section is now dynamic and based on a feed, so we don't have to update the plugin to include new tutorials.

#### Bugfixes:
* JavaScript translations was not loaded correct.

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

== Upgrade Notice ==

= 1.20 =
The parameter 'paymentType' on the event 'add_payment_info' has been renamed to 'payment_type'.
