=== Fancy Product Designer REST API ===
Contributors: Rafael Dery
Requires at least: 4.6 
Tested up to: 4.8
Tags: product designer, tool, online, shirt, rest, fancy product designer, customize products
Stable tag: trunk
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

This plugin registers all required routes and methods for the Fancy Product Designer Admin. So you can manage your [Fancy Product Designer](http://fancyproductdesigner.com) with the Admin hosted on [http://admin.fancyproductdesigner.com](http://admin.fancyproductdesigner.com).

= Required Plugins =

You also need the [Fancy Product Designer Plugin for WordPress/WooCommerce](https://codecanyon.net/item/fancy-product-designer-woocommercewordpress/6318393?ref=radykal) in order to use this plugin.

See [Documentation](http://support.fancyproductdesigner.com/support/solutions) for further help.

== Changelog ==

= 1.1.7 =
* Fixed bug for orders in WooCommerce 3.1 because of extra backslashes in the FPD data.

= 1.1.6 =
* Fixed bug when trying to log in via /wp-admin.

= 1.1.5 =
* Shows an error when the necessary header entries are missing in the $_SERVER variable.
* Fixed a bug that new orders from WooCommerce 3.1.0 could not be loaded into ADMIN.

= 1.1.4 =
* WooCommerce orders in the trash were also queried when calling all orders

= 1.1.3 =
* Undefined error issue fixed for authentication process

= 1.1.2 =
* Get username and password from HTTP_AUTHORIZATION or REDIRECT_HTTP_AUTHORIZATION string, if PHP_AUTH_USER or PHP_AUTH_PW is not set in the header. This will fix the forbidden issue.

= 1.1.1 =
* Fixed a Fatal error bug

= 1.1.0 =
* You do not need the Application Passwords anymore, set a custom password for authentication in the Fancy Product Designer REST API options. These options can be found in the General settings of Fancy Product Designer settings.

= 1.0.1 =
* Checks if Shortcode order database table exists


== Installation ==

1. Go to your admin area and select Plugins -> Add new from the menu.
2. Search for „Fancy Product Designer REST API“.
3. Click install.
4. Click activate.


