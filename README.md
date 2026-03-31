# Giga Stock Alerts — Back in Stock Notifier for WooCommerce

> Capture customer demand on out-of-stock products and notify them via email when restocked.

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)
![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0%2B-orange.svg)
![License](https://img.shields.io/badge/license-GPLv2-green.svg)

---

## Table of Contents

- [Description](#description)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [Configuration](#configuration)
- [Shortcodes](#shortcodes)
- [Template Customization](#template-customization)
- [Database Schema](#database-schema)
- [Contributing](#contributing)
- [License](#license)
- [Support](#support)

---

## Description

**Giga Stock Alerts** is a lightweight, performance-focused WooCommerce extension designed to help store owners capture lost sales by allowing customers to sign up for email notifications when out-of-stock products are restocked.

When a product's inventory is replenished, the plugin automatically batches and dispatches notifications using your store's built-in WooCommerce email system, ensuring optimal performance even with large subscriber lists.

### Key Benefits

- **Never Miss a Sale**: Capture customer interest even when products are unavailable
- **Automatic Notifications**: Seamlessly inform customers when products return to stock
- **Performance Optimized**: Uses asynchronous WP-Cron batch processing to prevent server overload
- **GDPR Compliant**: Built-in consent fields for European compliance
- **Conversion Tracking**: Monitor when waitlist users return and make purchases

---

## Features

### 🎨 Beautiful Widget Integration
- Seamlessly integrates into single product pages
- Automatically appears on out-of-stock products
- Clean, modern design that matches your store's aesthetic

### 🔄 Variable Product Support
- Native integration with WooCommerce variable products
- Form dynamically appears only when out-of-stock variations are selected
- Tracks subscriptions per variation for precise notifications

### 📊 Comprehensive Admin Dashboard
- View, filter, and manage all subscribers
- Export subscriber data for external marketing tools
- Monitor notification status and delivery logs
- Track conversion rates from waitlist to purchase

### 📧 Custom Email System
- Fully integrated with WordPress and WooCommerce email infrastructure
- Customizable email subjects and content
- HTML email templates with product images and details
- Double opt-in confirmation for verified subscribers

### ⚡ Performance Focused
- Asynchronous WP-Cron batch dispatch (50 emails at a time)
- Protects server limits during bulk restocking
- Automatic retry mechanism for failed notifications
- Comprehensive logging for troubleshooting

### 🔒 GDPR Compliance
- Explicit consent checkbox required for all subscriptions
- Customizable consent text for your specific needs
- One-click unsubscribe functionality
- Data privacy focused design

### 📈 Waitlist Optimization
- Track conversion metrics for waitlist subscribers
- Monitor which products generate the most interest
- Identify high-demand products for inventory planning

### 🎯 My Account Integration
- Customers can view their active subscriptions
- Easy unsubscribe options from customer dashboard
- Subscription history tracking

---

## Requirements

### System Requirements

- **WordPress**: 6.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **WooCommerce**: 7.0 or higher (tested up to 9.0)

### PHP Extensions

- `mysqli` or `pdo_mysql` for database operations
- `mbstring` for string manipulation
- `hash` for token generation

### Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

---

## Installation

### Method 1: WordPress Plugin Repository (Recommended)

1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins** → **Add New**
3. Search for "Giga Stock Alerts"
4. Click **Install Now** on the plugin
5. Click **Activate** after installation completes

### Method 2: Manual Upload

1. Download the latest version of the plugin from the [GitHub repository](https://github.com/gigabittech/giga-stock-alerts)
2. Extract the zip file to your computer
3. Upload the `giga-stock-alerts` folder to your WordPress installation:
   ```
   /wp-content/plugins/giga-stock-alerts/
   ```
4. Log in to your WordPress admin dashboard
5. Navigate to **Plugins** → **Installed Plugins**
6. Find "Giga Stock Alerts" in the list
7. Click **Activate**

### Method 3: FTP/SFTP Upload

1. Download the plugin zip file
2. Extract it locally
3. Connect to your server via FTP or SFTP
4. Navigate to `/wp-content/plugins/`
5. Upload the `giga-stock-alerts` folder
6. Log in to WordPress admin and activate the plugin

### Post-Installation Setup

After activation, the plugin will:

1. Create two custom database tables:
   - `wp_giga_stock_alerts` - Stores subscriber data
   - `wp_giga_stock_alerts_log` - Stores notification logs

2. Set default configuration options

3. The "Notify Me" form will automatically appear on single-product pages for out-of-stock items

4. Access the admin panel at **Stock Alerts > Subscribers** to manage subscriptions

5. Customize settings at **Stock Alerts > Settings**

### Verification

To verify installation:

1. Visit any out-of-stock product on your store
2. You should see a "Notify Me" form below the product
3. Check **Stock Alerts > Subscribers** in the admin panel
4. The dashboard should be accessible and display subscriber data

---

## Usage

### Basic Usage

The plugin works automatically after installation. When a customer visits an out-of-stock product:

1. The "Notify Me" form appears below the product information
2. Customer enters their email (and optionally their name)
3. Customer agrees to GDPR consent checkbox
4. Customer clicks "Notify Me!" button
5. If double opt-in is enabled, a confirmation email is sent
6. Upon confirmation, the subscription is marked as "confirmed"

### Automatic Notification Process

When you restock a product:

1. Update product stock in WooCommerce (set stock quantity > 0)
2. The plugin detects the stock change
3. Subscribers with "confirmed" status are queued for notification
4. WP-Cron processes the queue in batches (default: 50 emails at a time)
5. Each subscriber receives an email notification
6. Subscription status updates to "notified"
7. If the subscriber purchases the product, status updates to "purchased"

### Managing Subscribers

Navigate to **Stock Alerts > Subscribers** to:

- View all subscriptions with filtering options
- Search by email, product name, or status
- Export subscriber data to CSV
- Manually update subscription status
- Delete individual subscriptions
- View notification logs

### Email Templates

The plugin includes two email templates:

1. **Confirmation Email**: Sent when a customer subscribes (if double opt-in is enabled)
2. **Restock Notification Email**: Sent when a product is back in stock

Both templates are customizable and support placeholder variables:

#### Restock Email Variables

- `{customer_name}` - Subscriber's name
- `{product_name}` - Product name
- `{product_price}` - Product price
- `{product_url}` - Direct link to product
- `{product_image}` - Product image URL
- `{store_name}` - Your store name
- `{unsubscribe_url}` - Unsubscribe link

### Conversion Tracking

The plugin automatically tracks when:

- A subscriber clicks the product link in the notification email
- The subscriber adds the product to cart
- The subscriber completes a purchase
- These actions update the subscription status to "purchased"

View conversion metrics in the admin dashboard to understand your waitlist effectiveness.

---

## Configuration

### Accessing Settings

Navigate to **Stock Alerts > Settings** in your WordPress admin panel.

### Available Settings

#### Widget Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Button Heading | Text displayed above the form | "Out of Stock — Get Notified!" |
| Button Text | Text on the submit button | "Notify Me!" |
| Success Message | Message shown after successful subscription | "You'll be notified when this product is back!" |
| Button Color | Hex color code for the button | `#2271b1` |
| Show Name Field | Display optional name input field | `true` |

#### Email Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Email Subject | Subject line for restock notifications | "Great news! {product_name} is back in stock!" |
| From Name | Custom sender name (optional) | Store name |
| Admin Notification | Notify admin of new subscriptions | `true` |

#### Subscription Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Double Opt-in | Require email confirmation | `true` |
| GDPR Text | Consent checkbox text | "I agree to receive stock notifications for this product." |
| Rate Limit | Maximum subscriptions per IP per hour | `3` |
| Auto-confirm Days | Days before pending subscriptions auto-confirm | `7` |

#### Performance Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Batch Size | Number of emails sent per cron job | `50` |
| Notification Delay | Minutes to wait after restock before sending | `1` |

#### Display Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Hide Out-of-Stock | Hide form for out-of-stock products | `false` |

#### Advanced Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Delete Data on Uninstall | Remove all data when plugin is deleted | `false` |
| Debug Mode | Enable detailed error logging | `false` |

### Recommended Configuration

For most stores, the default settings work well. However, consider adjusting:

- **Batch Size**: Increase if your server can handle more emails per cron job
- **Rate Limit**: Increase if you expect legitimate multiple subscriptions from same IP
- **Double Opt-in**: Disable if you want immediate notifications (less secure)
- **Notification Delay**: Increase if you need time to prepare for traffic surge

---

## Shortcodes

### Basic Shortcode

Place the notify form anywhere using:

```php
[giga_stock_alert product_id="123"]
```

### With Variation

For variable products:

```php
[giga_stock_alert product_id="123" variation_id="456"]
```

### Custom Heading

Override the default heading:

```php
[giga_stock_alert product_id="123" heading="Custom Heading Text"]
```

### Custom Button Text

Override the button text:

```php
[giga_stock_alert product_id="123" button_text="Sign Me Up!"]
```

### PHP Template Usage

Use in your theme files:

```php
<?php
if ( function_exists( 'giga_sa_get_widget' ) ) {
    echo giga_sa_get_widget( $product_id );
}
?>
```

### Examples

#### In a Custom Page Template

```php
<?php
/**
 * Template Name: Custom Product Page
 */

get_header();

$product_id = 42; // Your product ID

// Display product
wc_get_template_part( 'content', 'single-product' );

// Display notify form
if ( function_exists( 'giga_sa_get_widget' ) ) {
    echo giga_sa_get_widget( $product_id );
}

get_footer();
?>
```

#### In a Shortcode Container

```php
[custom_section]
    [giga_stock_alert product_id="123"]
[/custom_section]
```

---

## Template Customization

### Overriding Templates

You can override plugin templates by copying them to your active theme:

1. Navigate to `/wp-content/plugins/giga-stock-alerts/templates/`
2. Copy the desired template file
3. Create a folder in your theme: `/wp-content/themes/your-theme/giga-stock-alerts/`
4. Paste the template file there
5. The plugin will automatically use your theme's version

### Available Templates

#### 1. Notify Me Widget (`notify-me-widget.php`)

Controls the frontend subscription form display.

**Variables available:**
- `$product` - WC_Product object
- `$heading` - Form heading text
- `$btn_text` - Button text
- `$gdpr_text` - GDPR consent text
- `$is_hidden` - Whether form should be hidden initially

**Example customization:**

```php
<?php
/**
 * Custom Notify Me Widget
 * Location: /wp-content/themes/your-theme/giga-stock-alerts/notify-me-widget.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="custom-notify-wrapper">
    <div class="custom-icon">🔔</div>
    <h3><?php echo esc_html( $heading ); ?></h3>
    
    <form class="giga-sa-form" method="POST">
        <!-- Your custom form structure -->
    </form>
</div>
```

#### 2. Restock Email (`email-restock.php`)

Controls the HTML email sent to customers.

**Variables available:**
- `$customer_name` - Subscriber's name
- `$product_name` - Product name
- `$product_price` - Product price
- `$product_url` - Product link
- `$product_image` - Product image URL
- `$store_name` - Store name
- `$unsubscribe_url` - Unsubscribe link

**Example customization:**

```php
<?php
/**
 * Custom Restock Email Template
 * Location: /wp-content/themes/your-theme/giga-stock-alerts/email-restock.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        /* Your custom email styles */
        .custom-email { font-family: Arial, sans-serif; }
        .product-image { max-width: 300px; }
    </style>
</head>
<body class="custom-email">
    <h1><?php echo esc_html( $product_name ); ?> is back!</h1>
    <img src="<?php echo esc_url( $product_image ); ?>" class="product-image" alt="<?php echo esc_attr( $product_name ); ?>">
    
    <p>Hi <?php echo esc_html( $customer_name ); ?>,</p>
    
    <p>Great news! <?php echo esc_html( $product_name ); ?> is now available at <?php echo esc_html( $store_name ); ?>.</p>
    
    <p><strong>Price:</strong> <?php echo wp_kses_post( $product_price ); ?></p>
    
    <a href="<?php echo esc_url( $product_url ); ?>" class="button">Buy Now</a>
    
    <p><small><a href="<?php echo esc_url( $unsubscribe_url ); ?>">Unsubscribe</a></small></p>
</body>
</html>
```

### CSS Customization

Add custom CSS to override plugin styles:

```css
/* Custom button styling */
.giga-sa-submit-btn {
    background-color: #ff6b6b !important;
    border-radius: 25px !important;
    padding: 12px 30px !important;
}

/* Custom form layout */
.giga-sa-notify-wrapper {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

/* Custom input fields */
.giga-sa-input {
    border: 2px solid #ddd;
    padding: 10px;
    width: 100%;
}
```

### JavaScript Customization

Hook into the plugin's JavaScript events:

```javascript
jQuery(document).ready(function($) {
    // After successful subscription
    $(document).on('giga_sa_subscription_success', function(e, data) {
        console.log('Subscription successful:', data);
        // Your custom code
    });

    // After subscription error
    $(document).on('giga_sa_subscription_error', function(e, error) {
        console.log('Subscription error:', error);
        // Your custom code
    });
});
```

---

## Database Schema

The plugin creates two custom database tables upon activation.

### Subscriptions Table: `wp_giga_stock_alerts`

Stores subscriber information and subscription status.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT(20) UNSIGNED | Primary key, auto-increment |
| `product_id` | BIGINT(20) UNSIGNED | Product ID |
| `variation_id` | BIGINT(20) UNSIGNED | Variation ID (0 for simple products) |
| `email` | VARCHAR(255) | Subscriber email address |
| `customer_name` | VARCHAR(255) | Optional customer name |
| `status` | ENUM | Subscription status: `pending`, `confirmed`, `notified`, `purchased`, `unsubscribed` |
| `subscribed_at` | DATETIME | Subscription timestamp |
| `notified_at` | DATETIME | Notification timestamp |
| `confirm_token` | VARCHAR(64) | Double opt-in confirmation token |
| `ip_address` | VARCHAR(45) | Subscriber IP address |

**Indexes:**
- PRIMARY KEY (`id`)
- UNIQUE KEY `email_product_variation` (`email`, `product_id`, `variation_id`)
- KEY `product_status` (`product_id`, `variation_id`, `status`)

### Log Table: `wp_giga_stock_alerts_log`

Stores notification delivery logs for tracking and troubleshooting.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT(20) UNSIGNED | Primary key, auto-increment |
| `subscription_id` | BIGINT(20) UNSIGNED | Reference to subscription |
| `channel` | VARCHAR(50) | Notification type: `confirmation`, `restock`, `admin_alert` |
| `status` | ENUM | Delivery status: `sent`, `failed`, `retried` |
| `error_message` | TEXT | Error details (if failed) |
| `sent_at` | DATETIME | Timestamp of notification attempt |

**Indexes:**
- PRIMARY KEY (`id`)
- KEY `subscription_id` (`subscription_id`)

### Status Flow

```
pending → confirmed → notified → purchased
          ↓
       unsubscribed
```

### Database Queries

#### Get all confirmed subscribers for a product:

```php
$subscribers = Giga_SA_DB::get_subscribers_for_product(
    $product_id,
    $variation_id,
    'confirmed'
);
```

#### Count waiting subscribers:

```php
$count = Giga_SA_DB::count_subscriptions_by_product($product_id);
```

#### Get subscriber by email:

```php
$subscriptions = Giga_SA_DB::get_subscriptions_by_email($email);
```

---

## Contributing

We welcome contributions from the community! Here's how you can help:

### Reporting Bugs

1. Check existing issues on GitHub
2. Create a new issue with:
   - Clear description of the problem
   - Steps to reproduce
   - Expected vs actual behavior
   - WordPress, PHP, and WooCommerce versions
   - Error messages or logs

### Suggesting Features

1. Check existing feature requests
2. Create a new issue with:
   - Feature description
   - Use case scenario
   - Potential implementation approach

### Submitting Pull Requests

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make your changes following coding standards
4. Write/update tests if applicable
5. Commit your changes: `git commit -m 'Add amazing feature'`
6. Push to branch: `git push origin feature/amazing-feature`
7. Open a Pull Request

### Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Use PHPCS for code quality: `composer phpcs`
- Add PHPDoc comments to all functions and classes
- Ensure all user input is sanitized and escaped
- Use prepared statements for all database queries

### Development Setup

```bash
# Clone repository
git clone https://github.com/gigabittech/giga-stock-alerts.git
cd giga-stock-alerts

# Install development dependencies
composer install

# Run code sniffer
composer phpcs

# Run tests
composer test
```

### Testing

Before submitting PRs, ensure:

- All existing tests pass
- New features include tests
- Code follows WordPress standards
- No PHP notices or warnings
- Tested on latest WordPress and WooCommerce versions

---

## License

This plugin is licensed under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html).

```
Giga Stock Alerts — Back in Stock Notifier for WooCommerce
Copyright (C) 2024 Gigabit

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
```

### Third-Party Licenses

This plugin may include third-party libraries under their respective licenses. See individual source files for details.

---

## Support

### Getting Help

- **Documentation**: Check this README and inline code comments
- **GitHub Issues**: [Report bugs or request features](https://github.com/gigabittech/giga-stock-alerts/issues)
- **WordPress.org Forum**: [Community support](https://wordpress.org/support/plugin/giga-stock-alerts/)

### Troubleshooting

#### Form Not Appearing

1. Verify WooCommerce is active
2. Check product stock status (must be "Out of Stock")
3. Ensure plugin is activated
4. Check for JavaScript errors in browser console

#### Emails Not Sending

1. Check WordPress email configuration
2. Verify WooCommerce email settings
3. Enable debug mode in plugin settings
4. Check notification logs in admin panel
5. Review server mail logs

#### High Server Load

1. Reduce batch size in settings
2. Increase notification delay
3. Check WP-Cron is running properly
4. Consider using external cron service

### Professional Support

For priority support, custom development, or enterprise solutions:

- **Website**: [https://gigabit.agency/](https://gigabit.agency/)
- **Email**: support@gigabit.agency

---

## Changelog

### Version 1.0.0 (2024)
- Initial public release
- Core functionality implemented
- WooCommerce HPOS compatibility
- Variable product support
- Admin dashboard
- Email notification system
- GDPR compliance features

---

## Credits

- **Developer**: [Gigabit Team](https://gigabit.agency/)
- **Contributors**: [Safayat Hossain]
- **Inspired by**: Community feedback and WooCommerce best practices

---

## Acknowledgments

- Built with [WordPress](https://wordpress.org/)
- Powered by [WooCommerce](https://woocommerce.com/)
- Icons and design inspired by modern e-commerce standards

---

<div align="center">

**Made with ❤️ by [Gigabit](https://gigabit.agency/)**

[⬆ Back to Top](#giga-stock-alerts--back-in-stock-notifier-for-woocommerce)

</div>
