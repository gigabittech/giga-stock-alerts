<?php
/**
 * Weekly Digest Admin Email Template.
 *
 * Variables: {admin_name} {store_name} {period_start} {period_end}
 *            {new_subscribers} {total_subscribers} {notifications_sent}
 *            {conversion_rate} {top_products_rows}
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
	body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; background: #f0f4f8; color: #3c3c3c; margin: 0; padding: 0; }
	.wrap { max-width: 620px; margin: 0 auto; padding: 40px 10px; }
	.card { background: #fff; border-radius: 8px; border: 1px solid #e5e5e5; overflow: hidden; }
	.header { background: linear-gradient(135deg, #6c3ef4 0%, #4f46e5 100%); padding: 28px 32px; }
	.header h1 { color: #fff; font-size: 22px; margin: 0 0 4px; }
	.header p { color: rgba(255,255,255,0.8); font-size: 13px; margin: 0; }
	.body { padding: 28px 32px; }
	.kpi-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin: 16px 0 28px; }
	.kpi { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; text-align: center; }
	.kpi-value { font-size: 28px; font-weight: 800; color: #6c3ef4; margin: 0 0 4px; }
	.kpi-label { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
	h3 { font-size: 16px; font-weight: 700; color: #1e293b; margin: 0 0 12px; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px; }
	table { width: 100%; border-collapse: collapse; font-size: 14px; }
	th { background: #f8fafc; padding: 10px 12px; text-align: left; color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e2e8f0; }
	td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; color: #334155; }
	.count-badge { background: #ede9ff; color: #6c3ef4; padding: 2px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
	.btn { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #6c3ef4; color: #fff !important; text-decoration: none; font-weight: 700; border-radius: 6px; font-size: 14px; }
	.footer { padding: 16px 32px; background: #f8fafc; border-top: 1px solid #e2e8f0; font-size: 12px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>
<div class="wrap">
<div class="card">
	<div class="header">
		<h1>📊 <?php esc_html_e( 'Weekly Stock Alerts Digest', 'giga-stock-alerts' ); ?></h1>
		<p>{store_name} &mdash; {period_start} &ndash; {period_end}</p>
	</div>
	<div class="body">
		<p style="font-size:15px;margin:0 0 20px;"><?php esc_html_e( 'Hi', 'giga-stock-alerts' ); ?> {admin_name}, <?php esc_html_e( "here's your weekly summary:", 'giga-stock-alerts' ); ?></p>

		<div class="kpi-grid">
			<div class="kpi">
				<div class="kpi-value">+{new_subscribers}</div>
				<div class="kpi-label"><?php esc_html_e( 'New This Week', 'giga-stock-alerts' ); ?></div>
			</div>
			<div class="kpi">
				<div class="kpi-value">{total_subscribers}</div>
				<div class="kpi-label"><?php esc_html_e( 'Total Subscribers', 'giga-stock-alerts' ); ?></div>
			</div>
			<div class="kpi">
				<div class="kpi-value">{notifications_sent}</div>
				<div class="kpi-label"><?php esc_html_e( 'Notifications Sent', 'giga-stock-alerts' ); ?></div>
			</div>
			<div class="kpi">
				<div class="kpi-value">{conversion_rate}%</div>
				<div class="kpi-label"><?php esc_html_e( 'Conversion Rate', 'giga-stock-alerts' ); ?></div>
			</div>
		</div>

		<h3><?php esc_html_e( 'Top Wanted Products', 'giga-stock-alerts' ); ?></h3>
		<table>
			<thead>
				<tr>
					<th><?php esc_html_e( 'Product', 'giga-stock-alerts' ); ?></th>
					<th><?php esc_html_e( 'Subscribers', 'giga-stock-alerts' ); ?></th>
				</tr>
			</thead>
			<tbody>
				{top_products_rows}
			</tbody>
		</table>

		<a href="{dashboard_url}" class="btn"><?php esc_html_e( 'View Full Dashboard →', 'giga-stock-alerts' ); ?></a>
	</div>
	<div class="footer">
		<p><?php esc_html_e( 'Powered by Giga Stock Alerts — Sent automatically every week.', 'giga-stock-alerts' ); ?></p>
	</div>
</div>
</div>
</body>
</html>
