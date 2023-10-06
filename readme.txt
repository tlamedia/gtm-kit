=== GTM Kit - Google Tag Manager integration ===
Contributors: tlamedia, torbenlundsgaard
Donate link: https://github.com/tlamedia/gtm-kit
Tags: google tag manager, gtm, woocommerce, analytics, ga4, gtag, easy digital downloads
Tested up to: 6.3
Stable tag: 1.14.1
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

= 1.14.1 =

Bugfixes:
* Set custom page type in post sidebar was removed by mistake in 1.14
* Fix possible naming collision with other GTM plugins


= 1.14 =

Enhancements:
* There is a new admin GUI based on WordPress components and the admin is now more extendable.
* We have added a more robust method of adding data attributes to the HTML.
* The autoload of options has been optimized for better performance.

Bugfixes:
* Flush cache after setting or updating an option.

Other:
* Require WordPress 6.2
* Require WooCommerce 7.6
* Tested up to WooCommerce 8.2

= 1.13.2 =

Bugfixes:
* Fixes a bug that prevented the option to import settings from other plugins on first install.

= 1.13.1 =

Bugfixes:
* Fixes a bug where a REST request could fail because the function is_plugin_active() is not ready.

Other:
* Tested up to WooCommerce 8.1

= 1.13 =

Enhancements:
* Add option to set Google Consent Mode default settings if a Consent Management Platform is not used.
* Added support for import of more plugin configurations.
* Prevent Consent Management Platforms from blocking GTM Kit and the Google Tag Manager container. This can be overridden with the filter hook 'gtmkit_header_script_attributes'.

Bugfixes:
* Fix a bug that in some cases would cause a namespace problem.

Other:
* Declare compatibility with the WooCommerce Cart and Checkout blocks.
* Comply with WordPress Coding Standards.

= 1.12.2 =

Enhancements:

Bugfixes:
* Fixes an error when no payment gateways are defined.
* Fixes empty items in begin_checkout event.

Other:
* Removes About GTM Kit section from help page

= 1.12.1 =

Bugfixes:
* A custom datalayer name was not registered correct.

= 1.12 =

Enhancements:
* Improved support for the WooCommerce cart and checkout blocks
* Added the class input-needed to the block list of the add_to_cart event
* Added option to import plugin settings from GTM4WP in the setup wizard
* Improved the setup wizard with option to enable essential settings

Other:
* Use global object for script data and settings
* Require WooCommerce 7.1
* Require WordPress 6.1
* Tested up to WordPress 6.3
* Tested up to WooCommerce 8.0

= 1.11.1 =

Bugfixes:
* The ecommerce object was not cleared on some events

* Cast transaction_id to string. According to the GA4 documentation transaction_id should be a string as it allows numbers, letters, and special characters like dashes or spaces.

= 1.11 =

Enhancements:

* Added Just-The-Container mode for those with simple needs. This option reduces the functionality to just the GTM container code.
* Added help section with GTM container import files

Other:

* Removed option to delay the container script by 2 seconds.
* WooCommerce tested up to: 7.9
* Added PHPStan to ensure code quality
* Refactored code and improved code quality
* All PHP classes are marked 'final'
* The Gulp build tool has been removed and we are now using NPM as a build tool

= 1.10 =

Enhancements:

* Added setup wizard for onboarding new users

Other:

* Updated dependencies
* Implemented Tailwind

= 1.9 =

Enhancements:

* Added option to include the customer data in the data layer on the "purchase" event.
* Added the constant 'GTMKIT_WC_DEBUG_TRACK_PURCHASE', which allows you to force tracking of purchase event on every page refresh for debugging.

Bugfixes:

* Fixed a bug where Easy Digital Download Pro was not detected.

Other:

* Changed required capability from 'install_plugins' to 'manage_options'.
* WooCommerce tested up to: 7.6

= 1.8 =

Enhancements:

* Added 'add_to_wishlist' event when used together with the most popular wishlist plugins 'YITH WooCommerce Wishlist' and 'TI WooCommerce Wishlist'.
* Improve compatibility with other plugins and customisations.
* Added item_variant to the items element in the datalayer.
* Added 'coupon' to the datalayer.
* Added option to log helpful messages and warnings to the browser log.

Bugfixes:

* Fixed a bug where inline styles would be stripped from the HTML.
* Fixed af bug where items in the 'view_cart' event had double brackets.

= 1.7.1 =

Bugfixes:

* The 'begin_checkout' event did not contain data layer values for 'ecommerce.currency' and 'ecommerce.value'

= 1.7 =

Enhancements:

* Added support for the new WooCommerce checkout block
* Added meta box on posts where you can enter the page type to be included in the data layer.
* Added the filter gtmkit_admin_capability, which allows you to set the capability required to manage the plugin options.
* Added option to share anonymous data with the development team to help improve GTM Kit.

Bugfixes:

* Type cast the item value to prevent string values in the data layer
* On installations of Easy Digital Download where the Confirmation Page was not configured the Purchase event would not fire.

Other:

* Minor styling of the dashboard
* Updated the tutorial section
* Improved some help texts
* Added missing translation strings
* WordPress tested up to 6.2
* WooCommerce tested up to: 7.5.

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
