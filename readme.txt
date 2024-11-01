=== Worldnet Payments Gateway for WooCommerce ===
Contributors: bbondane
Tags: payment, gateway, woocommerce
Requires at least: 4.9.8
Tested up to: 6.6.1
Stable tag: 2.7.9.2
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This plugin enables HPP and XML payments for Worldnet Payments Gateway using WooCommerce store.

== Description ==

This plugin enables HPP and XML payments for Worldnet Payments Gateway using WooCommerce store.

If HPP payment selected, a checkout button on your site will redirect your client to a secure payment page, where the
cardholder enters credit card details. Once the payment is completed, the cardholder is redirected back to your site.
On XML payment, a credit card form will appear, and the gathered data will be sent to Worldnet Payments servers that will
process it and return the payment details.

ApplePay can be used as a payment method during checkout process.

Please make sure the Secure Card storage is enabled on your terminal before enabling payment methods storage in plugin settings.
Please make sure the both Secure Card storage and the Subscriptions features are enabled on your terminal before adding products of Subscription type to your site.
Please note that a successful payment has been made for orders in a PROCESSING state when the order notes are updated with this message:
"Payment successfully processed #uniqueRef"

== Installation ==

The Worldnet Payments Gateway plugin V2.7.9.2 for WooCommerce

Before continuing with the plugin installation, please get in touch with us at https://www.worldnetpayments.com/ or support@worldnettps.com
to receive your merchant details.

There is one folder within the Worldnet Payments Gateway plugin for WooCommerce.

Before this module is installed, you must complete the installation of WooCommerce into WordPress.

This folder should then be moved into "wp-content/plugins/" or upload the zip through the WordPress' add plugin form.

Once you have added this you will need to go to the admin section of your WordPress installation:

1. WordPress Admin -> Plugins -> Installed Plugins ->
2. Click on "Activate" below "Worldnet Payments"
3. Click on WooCommerce (in the menu) -> Settings - > Payments -> Payment methods -> Worldnet Payments -> Set up/Manage
4. Tick "Enable/Disable"
5. Enter the details given to you by Worldnet Payments and click "Save Changes".

== Changelog ==

= 2.7.9 =
* Sentry integration

= 2.7.6 =
* HPOS Support

= 2.7.5 =
* Maintenance update

= 2.7.3 =
* Added MPIREF to Secure Token registration request
* Additional checks on data structure

= 2.7.2 =
* PHP 8.0 support

= 2.7 =
* ApplePay support on checkout page

= 2.6 =
* Saved payment methods
** Please make sure the Secure Card storage is enabled on your terminal.
* Recurring payments
** Please make sure the both Secure Card storage and the Subscriptions features are enabled on your terminal before adding products to your site.

= 2.5 =
* UI improvements on checkout page

= 2.4 =
* Refund functionality added

= 2.3 =
* Additional protection on data integrity

= 2.2 =
* Mastercard bin range fix

= 2.1 =
* Background validation implemented
* This will sync transaction status between gateway terminal and WooCommerce order

= 2.0 =
* Plugin refactored for WordPress marketplace.
* Tested with WordPress 5.3, WooCommerce 3.8.1.
