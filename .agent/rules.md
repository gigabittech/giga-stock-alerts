# Giga Stock Alerts — Agent Rules

## Project Context
This is a WordPress WooCommerce plugin called "Giga Stock Alerts".
It captures customer demand on out-of-stock products and notifies 
them via email when restocked. Currently building the FREE version only.

## Completed Work (Do Not Redo)
- Plugin scaffold created with all files
- Database tables designed (giga_stock_alerts + giga_stock_alerts_log)
- "Notify Me" widget built (frontend form + AJAX + CSS)
- Subscription backend built (AJAX handler, double opt-in, rate limiting)
- Restock detection and WP-Cron email queue built
- Admin panel built (subscribers list + settings page)
- WP.org standards audit completed — all 10 items PASS
- Folder structure migrated to WordPress standard
- Admin new subscriber email alert (with settings toggle giga_sa_admin_notify)
- Subscriber count badge on WooCommerce product list table
- My Account "Stock Alerts" tab with AJAX unsubscribe

## Current Folder Structure
giga-stock-alerts/
├── giga-stock-alerts.php
├── uninstall.php
├── readme.txt
├── includes/
│   ├── class-giga-sa-core.php
│   ├── class-giga-sa-db.php
│   ├── class-giga-sa-widget.php
│   ├── class-giga-sa-subscription.php
│   ├── class-giga-sa-notifier.php
│   └── class-giga-sa-email.php
├── admin/
│   ├── class-giga-sa-admin.php
│   ├── js/giga-sa-admin.js
│   ├── css/giga-sa-admin.css
│   └── images/
├── public/
│   ├── js/giga-sa-frontend.js
│   ├── css/giga-sa-frontend.css
│   └── images/
├── templates/
│   ├── notify-me-widget.php
│   └── email-restock.php
└── languages/
    └── giga-stock-alerts.pot

## Always Do
- Before writing any code, state which file you're editing and why
- After writing code, list what still needs to be done
- If a task touches multiple files, show the plan before starting
- Use the `wordpress-plugin-standards` skill for all PHP code

## Never Do
- Never add Pro features (SMS, WhatsApp, license checks)
- Never modify WooCommerce or WordPress core files
- Never write raw SQL without `$wpdb->prepare()`
- Never redo work listed in "Completed Work" section above
- Never assume a task is complete without listing verification steps

## When Stuck
If you encounter a WooCommerce hook or function you're unsure about,
say so explicitly rather than guessing. Ask for clarification.
