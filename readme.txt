=== GTM Kit - Google Tag Manager integration ===
Contributors: tlamedia, torbenlundsgaard
Donate link: https://github.com/tlamedia/gtm-kit
Tags: google tag manager, gtm, woocommerce, analytics, ga4, gtag, easy digital downloads
Tested up to: 6.1
Stable tag: 1.6.2
License: GPL v3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Flexible tool for generating the data layer for Google Tag Manager. Including WooCommerce data for Google Analytics 4 and support for server side GTM.

== Description ==

GTM Kit puts the Google Tag Manager container code on your website so that you don't need to touch any code. It also pushes data from WooCommerce, Easy Digital Downloads and Contact Form 7 to the data layer for use with for Google Analytics 4, Facebook and other GTM tags.

The goal of GTM Kit is to provide a flexible tool for generating the data layer for Google Tag Manager. It is easy to use and doesn't require any coding, but it allows developers to customize the plugin as needed.

## eCommerce events tracked with Google Analytics 4
The following GA4 events are automatically included in the dataLayer:

### WooCommerce
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

### Easy Digital Downloads
- view_item
- add_to_cart
- begin_checkout
- purchase


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
It’s recommended that you create a Google Analytics 4 property instead. Note that it is possible to use GA4 events for GA3 Enhanced Ecommerce.

= Is this plugin cache friendly? =

Yes! Pagespeed is one of our main focus points, and we strive to make the plugin compatible with full page caching.

== Changelog ==

= 1.x =

Enhancements:

* Added the filter gtmkit_admin_capability, which allows you to set the capability required to manage the plugin options.

Bugfixes:

* Type cast the item value to prevent string values in the data layer

= 1.6.2 =

Bugfixes:

* Backwards compatibility. Trailing comma in function calls is only available since PHP 7.3.

= 1.6.1 =

Enhancements:

* Added declaration of plugin compatibility with WooCommerce High-Performance Order Storage.

Other:

* WooCommerce tested up to: 7.4

= 1.6 =

Enhancements:

* Added support Easy Digital Downloads for the events: view_item, add_to_cart, begin_checkout, purchase
* Added support for primary product category in Rank Math SEO

Other:

* WooCommerce tested up to: 7.3
* Refactored integrations
* Added missing translation string

= 1.5.3 =

Enhancements:

* Align text domain with GlotPress making the plugin compatible with translations from WordPress.org

= 1.5.2 =

Enhancements:

* Added better help texts.
* Fixed typos.

= 1.5.1 =

Enhancements:

* Added better support for the Google Ads remarketing tag.

= 1.5 =

Enhancements:

* Add option til select the Google business vertical value to include in all items. This is used for Google Ads remarketing.
* Add option to add a prefix to the item IDs.

Bugfixes:

* Fix wrong currency value in the Data Layer.

= 1.4.5 =

Enhancements:

* By default the Contact Form 7 integration script will only load on pages where the Contact Form 7 script is registered.
* Tutorials and help text have been improved

Bugfixes:

* Fix datalayer_name collision when both WooCommerce and CF7 integration are active.

Other:

* WooCommerce tested up to: 7.1

= 1.4.4 =

Enhancements:

* Added tutorials section to the dashboard.

Bugfixes:

* Fix the description og the CF7 integration.

= 1.4.3 =

Bugfixes:

* The items object was limited to 1 on the checkout page.

= 1.4 =

Enhancements:

* Added options to add user data to the datalayer (logged in, user ID, user role).
* Added option to limit view_item event on variable products to the master product instead of pushing the view_item event on both the master and selected variation.
* Added edit button on container dashboard

Bugfixes:

* Clear the previous ecommerce object before add_to_cart.
* Set default option for CF7 integration

Other:

* Added support links in help section

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
