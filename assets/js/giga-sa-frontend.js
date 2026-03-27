/**
 * Frontend JavaScript for Giga Stock Alerts.
 * 
 * Handles variable product variation stock changes and AJAX form submission.
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		// ---------------------------------------------------------------------
		// Variation Swatching Logic
		// ---------------------------------------------------------------------
		
		$('.variations_form').each(function() {
			var $form = $(this);
			
			// Use WooCommerce's built-in 'found_variation' event
			$form.on('found_variation', function(event, variation) {
				var $productWrap = $form.closest('.product');
				var $widget      = $productWrap.find('.giga-sa-notify-wrapper');
				
				if ( !$widget.length ) {
					// Fallback if widget is outside .product element
					$widget = $('.giga-sa-notify-wrapper.is-variable');
				}

				if ( !$widget.length ) return;

				if ( !variation.is_in_stock ) {
					// Show widget, set variation ID
					$widget.slideDown(200);
					$widget.find('input[name="giga_sa_variation_id"]').val( variation.variation_id );
					// Reset UI from previous submits
					$widget.find('.giga-sa-form').show();
					$widget.find('.giga-sa-message-success').hide();
					$widget.find('.giga-sa-message-error').hide();
					$widget.find('input[name="giga_sa_email"]').val('');
				} else {
					// In stock, hide widget
					$widget.slideUp(200);
					$widget.find('input[name="giga_sa_variation_id"]').val( 0 );
				}
			});

			// Hide widget if options are cleared/reset
			$form.on('reset_data', function() {
				var $productWrap = $form.closest('.product');
				var $widget      = $productWrap.find('.giga-sa-notify-wrapper');
				
				if ( !$widget.length ) {
					$widget = $('.giga-sa-notify-wrapper.is-variable');
				}
				
				// Only hide if the main product is in stock.
				// For simplicity, we just slide up.
				$widget.slideUp(200);
				$widget.find('input[name="giga_sa_variation_id"]').val( 0 );
			});
		});

		// ---------------------------------------------------------------------
		// Form Submission Logic
		// ---------------------------------------------------------------------

		$(document).on('submit', '.giga-sa-form', function(e) {
			e.preventDefault();

			var $form     = $(this);
			var $wrapper  = $form.closest('.giga-sa-notify-wrapper');
			var $msgOk    = $wrapper.find('.giga-sa-message-success');
			var $msgErr   = $wrapper.find('.giga-sa-message-error');
			var $btn      = $form.find('.giga-sa-submit-btn');
			
			var productId = $form.find('input[name="giga_sa_product_id"]').val();
			var varId     = $form.find('input[name="giga_sa_variation_id"]').val();
			var name      = $form.find('input[name="giga_sa_name"]').val().trim();
			var email     = $form.find('input[name="giga_sa_email"]').val().trim();
			var gdpr      = $form.find('input[name="giga_sa_gdpr"]').is(':checked') ? 1 : 0;

			if ( !email || !gdpr ) {
				return;
			}

			// Clear messages
			$msgOk.hide().html('');
			$msgErr.hide().html('');

			// Loading state
			$btn.prop('disabled', true);
			var originalText = $btn.text();
			
			if ( typeof gigaSaParams !== 'undefined' && gigaSaParams.submitting ) {
				$btn.text(gigaSaParams.submitting);
			} else {
				$btn.text('...');
			}

			// Assign nonce dynamically to avoid caching issues on static page generation
			var nonce = typeof gigaSaParams !== 'undefined' ? gigaSaParams.nonce : '';

			$.ajax({
				url: typeof gigaSaParams !== 'undefined' ? gigaSaParams.ajaxUrl : '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: {
					action: 'giga_sa_subscribe',
					nonce: nonce,
					product_id: productId,
					variation_id: varId,
					name: name,
					email: email,
					gdpr: gdpr
				},
				success: function(response) {
					$btn.prop('disabled', false).text(originalText);
					
					if ( response.success ) {
						// Hide form, show success
						$form.slideUp(200);
						$msgOk.html(response.data.message || 'Subscribed successfully.').fadeIn();
					} else {
						// Error logic
						var msg = response.data.message || 'An error occurred.';
						if ( response.data.code === 'duplicate' ) {
							msg = "You're already subscribed!";
						}
						$msgErr.html(msg).fadeIn();
					}
				},
				error: function() {
					$btn.prop('disabled', false).text(originalText);
					$msgErr.html('An unexpected error occurred. Please try again.').fadeIn();
				}
			});
		});

	});

})(jQuery);
