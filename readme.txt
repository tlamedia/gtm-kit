=== GTM Kit - Google Tag Manager & GA4 integration ===
Contributors: tlamedia, torbenlundsgaard, gtmkit
Donate link: https://github.com/tlamedia/gtm-kit
Tags: google tag manager, gtm, woocommerce, analytics, ga4
Tested up to: 6.8
Stable tag: 2.5.1
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

= How can I report security bugs? =

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team help validate, triage and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/vdp/gtm-kit)

== Changelog ==

= 2.X.Y =

Release date: 2025-MM-DD

#### Enhancements:

#### Bugfixes:

#### Other:
* Tested up to WooCommerce 10.2.

= 2.5.1 =

Release date: 2025-08-22

#### Bugfixes:
* Force rounding values to 2 decimal places to fix rounding issues in edge cases.
* In some cases the GTM container would not load after activating and deactivating settings in 'Server-side Tagging (sGTM)'.

#### Other:
* Tested up to WooCommerce 10.1.

= 2.5.0 =

Release date: 2025-08-05

#### Bugfixes:
* The add_to_cart event did not fire in the all-products block.

#### Other:
* Require WooCommerce 9.0.
* Tested up to WooCommerce 10.0.

= 2.4.4 =

Release date: 2025-04-30

#### Bugfixes:
* The $hook type hint in enqueue_page_assets was removed to avoid conflicts with plugins passing non-standard data types.

#### Other:
* Introduced the gtmkit_options_set action and the gtmkit_process_options filter.
* Fixed deprecation in Easy Digital Downloads.

= 2.4.3 =

Release date: 2025-04-15

#### Bugfixes:
* Fix conflict with GTM Kit Woo in admin settings.

= 2.4.2 =

Release date: 2025-04-09

#### Bugfixes:
* Fix fatal error when used together with MC4WP: Mailchimp for WordPress.

#### Other:
* Tested up to WordPress 6.8.
* Tested up to WooCommerce 9.8.

= 2.4.1 =

Release date: 2025-04-02

#### Security:
* When debug logging is enabled, the Easy Digital Downloads integration was activaed sensitive customer information was logged to server error logs. If debug logging remains active in a production environment or if logs are not properly secured, it could lead to unauthorized access to personal data. - [CVE-2025-31001](https://www.cve.org/CVERecord?id=CVE-2025-31001).

= 2.4.0 =

Release date: 2025-04-03

#### Feature Removed:
* The Event Inspector has been removed and is only available the premium version. It was often used in an inappropriate way where end-users unintentionally were shown debug data.

= 2.3.2 =

Release date: 2025-04-02

#### Security:
* Permissions were not checked correct on the admin API.

= 2.3.1 =

Release date: 2025-03-12

#### Bugfixes:
* Fix a rare case of divisionByZero in calculation of discount.

#### Other:
* Tested up to WooCommerce 9.7.

= 2.3 =

Release date: 2025-01-28

#### Bugfixes:
* Fix an edge case fatal error in admin if $hook for some reason is missing,

#### Other:
* Require WordPress 6.4.
* Require WooCommerce 8.4.
* Tested up to WooCommerce 9.6.

= 2.2.2 =

Release date: 2024-12-03

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

= Earlier versions =
For the changelog of earlier versions, please refer to [the changelog on gtmkit.com](https://gtmkit.com/changelog/).
