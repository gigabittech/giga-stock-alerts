<?php
/**
 * Price Drop Alert Email Template.
 *
 * Variables available: {customer_name} {product_name} {product_url} {product_image}
 *                      {store_name} {old_price} {new_price} {unsubscribe_url}
 *
 * @package GigaStockAlerts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
	body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; background: #f7f7f7; color: #3c3c3c; margin: 0; padding: 0; }
	.wrap { max-width: 600px; margin: 0 auto; padding: 40px 10px; }
	.card { background: #fff; border-radius: 6px; border: 1px solid #e5e5e5; overflow: hidden; }
	.header { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); padding: 28px 32px; text-align: center; }
	.header h1 { color: #fff; font-size: 22px; margin: 0 0 4px; }
	.header p { color: rgba(255,255,255,0.85); font-size: 14px; margin: 0; }
	.badge { display: inline-block; background: #fef3c7; color: #92400e; padding: 5px 14px; border-radius: 20px; font-size: 13px; font-weight: 700; margin: 24px 0 16px; }
	.body { padding: 24px 32px; text-align: center; }
	.product-img { max-width: 220px; height: auto; border-radius: 8px; margin-bottom: 16px; border: 1px solid #f0f0f0; }
	.product-name { font-size: 20px; font-weight: 700; color: #111; margin: 0 0 16px; }
	.price-row { display: flex; justify-content: center; align-items: center; gap: 16px; margin: 0 0 28px; }
	.price-old { font-size: 18px; color: #9ca3af; text-decoration: line-through; }
	.price-new { font-size: 32px; font-weight: 800; color: #ea580c; }
	.price-badge { background: #dcfce7; color: #166534; font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
	.btn { display: inline-block; padding: 14px 32px; background: #ea580c; color: #fff !important; text-decoration: none; font-weight: 700; border-radius: 6px; font-size: 15px; }
	.footer { padding: 20px 32px; border-top: 1px solid #f0f0f0; text-align: center; font-size: 12px; color: #9ca3af; }
	.footer a { color: #9ca3af; text-decoration: underline; }
</style>
</head>
<body>
<div class="wrap">
<div class="card">
	<div class="header">
		<h1>🏷️ <?php esc_html_e( 'Price Drop Alert', 'giga-stock-alerts' ); ?></h1>
		<p>{store_name}</p>
	</div>
	<div class="body">
		<p style="text-align:left;font-size:16px;margin:0 0 8px;"><?php esc_html_e( 'Hi', 'giga-stock-alerts' ); ?> {customer_name},</p>
		<p style="text-align:left;font-size:15px;color:#555;margin:0 0 24px;"><?php esc_html_e( "Great news! A product you've been watching just dropped in price. Don't miss this deal — grab it while it lasts!", 'giga-stock-alerts' ); ?></p>

		<img src="{product_image}" alt="{product_name}" class="product-img" />
		<div class="product-name">{product_name}</div>

		<div class="price-row">
			<span class="price-old">{old_price}</span>
			<span class="price-new">{new_price}</span>
		</div>

		<a href="{product_url}" class="btn"><?php esc_html_e( 'Shop This Deal →', 'giga-stock-alerts' ); ?></a>
	</div>
	<div class="footer">
		<p><?php esc_html_e( 'You received this because you subscribed to price alerts at', 'giga-stock-alerts' ); ?> {store_name}.</p>
		<p><a href="{unsubscribe_url}"><?php esc_html_e( 'Unsubscribe from price alerts', 'giga-stock-alerts' ); ?></a></p>
	</div>
</div>
</div>
</body>
</html>
