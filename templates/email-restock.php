<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
	body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; background-color: #f7f7f7; color: #3c3c3c; margin: 0; padding: 0; }
	.container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff; border-radius: 4px; border: 1px solid #e5e5e5; }
	.header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #eeeeee; }
	.header h1 { font-size: 24px; color: #96588a; margin: 0; }
	.content { padding: 20px 0; text-align: center; }
	.product-img { max-width: 250px; height: auto; border-radius: 4px; margin-bottom: 20px; }
	.product-title { font-size: 20px; font-weight: bold; margin: 0 0 10px 0; }
	.product-price { font-size: 18px; color: #777777; margin: 0 0 20px 0; }
	.btn { display: inline-block; padding: 12px 24px; background-color: #96588a; color: #ffffff !important; text-decoration: none; font-weight: bold; border-radius: 3px; font-size: 16px; }
	.footer { margin-top: 30px; font-size: 12px; color: #999999; text-align: center; }
	.footer a { color: #999999; text-decoration: underline; }
</style>
</head>
<body>
<table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f7f7f7">
	<tr>
		<td align="center" style="padding: 40px 10px;">
			<div class="container">
				<div class="header">
					<h1>{store_name}</h1>
				</div>
				<div class="content">
					<p style="font-size: 16px; text-align: left;"><?php echo esc_html__( 'Hi', 'giga-stock-alerts' ); ?> {customer_name},</p>
					<p style="font-size: 16px; text-align: left; margin-bottom: 30px;"><?php echo esc_html__( 'Great news! The item you\'ve been waiting for is finally back in stock. Grab it before it sells out again!', 'giga-stock-alerts' ); ?></p>
					
					<img src="{product_image}" alt="{product_name}" class="product-img" />
					<div class="product-title">{product_name}</div>
					<div class="product-price">{product_price}</div>
					
					<a href="{product_url}" class="btn"><?php echo esc_html__( 'Shop Now', 'giga-stock-alerts' ); ?></a>
				</div>
				<div class="footer">
					<p><?php echo esc_html__( 'You received this email because you subscribed to stock alerts at', 'giga-stock-alerts' ); ?> {store_name}.</p>
					<p><a href="{unsubscribe_url}"><?php echo esc_html__( 'Unsubscribe from this alert', 'giga-stock-alerts' ); ?></a></p>
				</div>
			</div>
		</td>
	</tr>
</table>
</body>
</html>
