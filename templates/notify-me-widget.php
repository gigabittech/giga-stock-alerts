<?php
/**
 * Notify Me Widget Template.
 *
 * This template can be overridden by copying it to yourtheme/giga-stock-alerts/notify-me-widget.php.
 *
 * @package GigaStockAlerts
 * @since   1.0.0
 *
 * @var WC_Product $product
 * @var string     $heading
 * @var string     $btn_text
 * @var string     $gdpr_text
 * @var bool       $is_hidden
 * @var bool       $show_name_field
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$giga_sa_wrapper_style = $is_hidden ? 'display: none;' : '';
$giga_sa_wrapper_class = 'giga-sa-notify-wrapper';

if ( $product->is_type( 'variable' ) ) {
	$giga_sa_wrapper_class .= ' is-variable';
}
?>

<div class="<?php echo esc_attr( $giga_sa_wrapper_class ); ?>" style="<?php echo esc_attr( $giga_sa_wrapper_style ); ?>">
	<h3 class="giga-sa-heading"><?php echo esc_html( $heading ); ?></h3>
	
	<form class="giga-sa-form" method="POST">
		<input type="hidden" name="giga_sa_product_id" value="<?php echo esc_attr( $product->get_id() ); ?>" />
		<input type="hidden" name="giga_sa_variation_id" value="0" />
		<input type="hidden" name="giga_sa_nonce" value="" />
		
		<?php if ( ! empty( $show_name_field ) ) : ?>
		<div class="giga-sa-field-group">
			<label class="giga-sa-label" for="giga_sa_name_<?php echo esc_attr( $product->get_id() ); ?>">
				<?php esc_html_e( 'Name (optional)', 'giga-stock-alerts' ); ?>
			</label>
			<input type="text" id="giga_sa_name_<?php echo esc_attr( $product->get_id() ); ?>" name="giga_sa_name" class="giga-sa-input" placeholder="<?php esc_attr_e( 'John Doe', 'giga-stock-alerts' ); ?>" />
		</div>
		<?php endif; ?>

		<div class="giga-sa-field-group">
			<label class="giga-sa-label" for="giga_sa_email_<?php echo esc_attr( $product->get_id() ); ?>">
				<?php esc_html_e( 'Email (required)', 'giga-stock-alerts' ); ?> <span class="required">*</span>
			</label>
			<input type="email" id="giga_sa_email_<?php echo esc_attr( $product->get_id() ); ?>" name="giga_sa_email" class="giga-sa-input" placeholder="<?php esc_attr_e( 'you@example.com', 'giga-stock-alerts' ); ?>" required />
		</div>

		<div class="giga-sa-gdpr-group">
			<label class="giga-sa-gdpr-label">
				<input type="checkbox" name="giga_sa_gdpr" required />
				<span class="giga-sa-gdpr-text"><?php echo wp_kses_post( $gdpr_text ); ?></span>
			</label>
		</div>
		
		<button type="submit" class="button alt giga-sa-submit-btn">
			<?php echo esc_html( $btn_text ); ?>
		</button>
	</form>

	<div class="giga-sa-message-success" style="display: none;"></div>
	<div class="giga-sa-message-error" style="display: none;"></div>
</div>
