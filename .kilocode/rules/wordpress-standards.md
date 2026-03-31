---
name: wordpress-plugin-standards
description: >
  Use this skill whenever writing or reviewing PHP code for the 
  Giga Stock Alerts WordPress plugin. Applies WordPress coding 
  standards, WooCommerce best practices, and plugin security rules.
---

# WordPress Plugin Development Standards

## Core Rules
- PHP 7.4+ compatible code only
- Prefix ALL functions, classes, hooks with `giga_sa_`
- Every PHP file must start with: `if ( ! defined( 'ABSPATH' ) ) { exit; }`
- PHPDoc comments on every class and public method
- All strings wrapped in `__('text', 'giga-stock-alerts')` for i18n
- Every class must be wrapped in `if ( ! class_exists( 'ClassName' ) ) { }`
- Admin classes must only load inside `if ( is_admin() ) { }`

## Security (Non-Negotiable)
- Every AJAX handler must call `wp_verify_nonce()` as the very first line
- Every `$wpdb` query must use `$wpdb->prepare()`
- Exception: DROP TABLE queries cannot use prepare() — add inline comment explaining why
- Sanitize ALL input: `sanitize_email()`, `sanitize_text_field()`, `absint()`
- Escape ALL output: `esc_html()`, `esc_attr()`, `esc_url()`
- Never use `$_POST` or `$_GET` directly without sanitization
- Rate limit: max 3 subscriptions per IP per minute via transients

## WooCommerce Integration
- Use WooCommerce hooks — never modify core files
- Check `$product->is_in_stock()` for stock status
- Use `wc_get_product()` to fetch products
- Declare HPOS compatibility via `FeaturesUtil::declare_compatibility()`

## Architecture
- This is FREE version only — no Twilio, no WhatsApp, no Pro features
- All database queries go through `Giga_SA_DB` class
- Never send emails synchronously in hooks — use WP-Cron
- Never add Pro features (SMS, WhatsApp, license checks)

## Translation
- Every user-facing string must use `__()` or `_e()` with domain 'giga-stock-alerts'
- In template files use: `<?php echo esc_html__( 'string', 'giga-stock-alerts' ); ?>`
- Default option values in activate() must also use `__()`

## License
- License header in main plugin file must be exactly: `License: GPLv2 or later`
- License URI: https://www.gnu.org/licenses/gpl-2.0.html

## File Structure
- Main plugin file: `giga-stock-alerts.php`
- All includes classes in `includes/` with prefix `class-giga-sa-`
- Admin class in `admin/class-giga-sa-admin.php`
- Templates in `templates/` (overridable by theme)
- Frontend assets in `public/css/` and `public/js/`
- Admin assets in `admin/css/` and `admin/js/`
