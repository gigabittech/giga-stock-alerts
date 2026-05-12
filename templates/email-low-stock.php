<?php
/**
 * Low Stock Urgency Email Template.
 *
 * Variables: {customer_name} {product_name} {product_url} {product_image}
 *            {store_name} {stock_count} {unsubscribe_url}
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
	.header { background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); padding: 28px 32px; text-align: center; }
	.header h1 { color: #fff; font-size: 22px; margin: 0 0 4px; }
	.header p { color: rgba(255,255,255,0.85); font-size: 14px; margin: 0; }
	.urgency-bar { background: #fef2f2; border: 2px solid #fca5a5; border-radius: 8px; padding: 12px 20px; margin: 16px 0 24px; font-size: 15px; font-weight: 700; color: #b91c1c; }
	.body { padding: 24px 32px; text-align: center; }
	.product-img { max-width: 220px; height: auto; border-radius: 8px; margin-bottom: 16px; border: 1px solid #f0f0f0; }
	.product-name { font-size: 20px; font-weight: 700; color: #111; margin: 0 0 8px; }
	.product-price { font-size: 18px; color: #6b7280; margin: 0 0 28px; }
	.btn { display: inline-block; padding: 14px 32px; background: #dc2626; color: #fff !important; text-decoration: none; font-weight: 700; border-radius: 6px; font-size: 15px; }
	.footer { padding: 20px 32px; border-top: 1px solid #f0f0f0; text-align: center; font-size: 12px; color: #9ca3af; }
	.footer a { color: #9ca3af; text-decoration: underline; }
</style>
</head>
<body>
<div class="wrap">
<div class="card">
	<div class="header">
		<h1>🔥 <?php esc_html_e( 'Almost Gone — Act Fast!', 'giga-stock-alerts' ); ?></h1>
		<p>{store_name}</p>
	</div>
	<div class="body">
		<div class="urgency-bar">
			⚠️ <?php
			/* translators: %s: number of items remaining */
			printf( esc_html__( 'Only %s left in stock!', 'giga-stock-alerts' ), '{stock_count}' );
			?>
		</div>

		<p style="text-align:left;font-size:16px;margin:0 0 8px;"><?php esc_html_e( 'Hi', 'giga-stock-alerts' ); ?> {customer_name},</p>
		<p style="text-align:left;font-size:15px;color:#555;margin:0 0 24px;"><?php esc_html_e( "You subscribed to a stock alert for this product — and we want to give you first pick before it sells out completely!", 'giga-stock-alerts' ); ?></p>

		<img src="{product_image}" alt="{product_name}" class="product-img" />
		<div class="product-name">{product_name}</div>
		<div class="product-price">{product_price}</div>

		<a href="{product_url}" class="btn"><?php esc_html_e( 'Grab It Now →', 'giga-stock-alerts' ); ?></a>
	</div>
	<div class="footer">
		<p><?php esc_html_e( 'You received this because you subscribed to stock alerts at', 'giga-stock-alerts' ); ?> {store_name}.</p>
		<p><a href="{unsubscribe_url}"><?php esc_html_e( 'Unsubscribe from this alert', 'giga-stock-alerts' ); ?></a></p>
	</div>
</div>
</div>
</body>
</html>
