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

## Security (Non-Negotiable)
- Every AJAX handler must call `wp_verify_nonce()` first
- Every `$wpdb` query must use `$wpdb->prepare()`
- Sanitize ALL input: `sanitize_email()`, `sanitize_text_field()`, `absint()`
- Escape ALL output: `esc_html()`, `esc_attr()`, `esc_url()`
- Never use `$_POST` or `$_GET` directly without sanitization

## WooCommerce Integration
- Use WooCommerce hooks — never modify core files
- Check `$product->is_in_stock()` for stock status
- Use `wc_get_product()` to fetch products
- Declare HPOS compatibility via `FeaturesUtil::declare_compatibility()`

## Architecture
- This is FREE version only — no Twilio, no WhatsApp, no Pro features
- All database queries go through `Giga_SA_DB` class
- Never send emails synchronously in hooks — use WP-Cron
- Rate limit: max 3 subscriptions per IP per minute

## File Structure
- Main plugin file: `giga-stock-alerts.php`
- All classes in `includes/` with prefix `class-giga-sa-`
- Templates in `templates/` (overridable by theme)
- Assets in `assets/css/` and `assets/js/`