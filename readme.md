### Giga Stock Alerts — Back in Stock Notifier for WooCommerce ===
Contributors: safayathossain
Tags: back in stock, waitlist, woocommerce, out of stock, stock alert
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 9.0
Stable tag: 1.0.0
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Capture customer demand on out-of-stock products and notify them via email when restocked.

== Description ==

Giga Stock Alerts is a simple, lightweight WooCommerce extension that captures lost sales by allowing customers to sign up for email notifications when out-of-stock products are restocked.

When a product's inventory is replenished, the plugin automatically batches and dispatches notifications using your store's built-in WooCommerce email system.

### Features
* **Beautiful Widget**: Integrates perfectly onto simple and variable products seamlessly.
* **Variable Product Support**: Only appears when out-of-stock variations are selected natively.
* **Admin Dashboard**: Comprehensive admin panel to view, filter, export and manage subscribers.
* **Custom Emails**: Fully integrated with standard WordPress and WooCommerce systems for customizable e-mails.
* **Waitlist Optimization**: Tracks conversions natively tracking when waitlist users return and actually purchase.
* **Performance Focused**: Uses asynchronous WP-Cron batch dispatch to completely protect server limits.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/giga-stock-alerts` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Once active, a "Notify Me" form will automatically appear on single-product pages for out-of-stock items.
4. Manage subscribers from **Stock Alerts > Subscribers**.
5. Customize the notification from **Stock Alerts > Settings**.

== Frequently Asked Questions ==

= Does it support variable products natively? =
Absolutely! It integrates directly into the `found_variation` JavaScript triggers, meaning the form dynamically slides down only when an empty variation is requested.

= How does it handle server overload when restocking? =
Instead of sending 500 emails during the 'save' action, it uses Asynchronous Batching. It queues the alerts utilizing `WP-Cron` to process 50 users at a time without slowing down your store.

= Are the templates customizable? =
Yes. You can override the native form by copying `templates/notify-me-widget.php` directly into your active theme folder under `/giga-stock-alerts/notify-me-widget.php`. 

= Is there a GDPR consent field? =
Yes. It includes an explicit GDPR consent toggle ensuring complete European compliance for your marketing lists. Note: this text can easily be translated or overridden in Settings.

= Can I place the form dynamically with a shortcode? =
Yes. Use `[giga_stock_alert product_id="..."]` anywhere inside WordPress to generate the sign-up component externally.

== Screenshots ==

1. Complete integration inside normal out-of-stock product pages.
2. The admin dashboard tracking waitlist states.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
First public release on WordPress.org.