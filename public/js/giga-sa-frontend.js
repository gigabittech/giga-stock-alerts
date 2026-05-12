/**
 * Frontend JavaScript for Giga Stock Alerts.
 *
 * Handles:
 * - Variable product variation stock changes (show/hide widget)
 * - AJAX restock subscription form submission
 * - AJAX price drop subscription form submission
 * - My Account unsubscribe button
 * - My Account re-subscribe button
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		// -----------------------------------------------------------------
		// Variation Swatching Logic
		// -----------------------------------------------------------------

		$('.variations_form').each(function() {
			var $form = $(this);

			$form.on('found_variation', function(event, variation) {
				var $productWrap = $form.closest('.product');
				var $widget      = $productWrap.find('.giga-sa-notify-wrapper');

				if ( !$widget.length ) {
					$widget = $('.giga-sa-notify-wrapper.is-variable');
				}
				if ( !$widget.length ) return;

				if ( !variation.is_in_stock ) {
					$widget.slideDown(200);
					$widget.find('input[name="giga_sa_variation_id"]').val( variation.variation_id );
					$widget.find('.giga-sa-form').show();
					$widget.find('.giga-sa-message-success').hide();
					$widget.find('.giga-sa-message-error').hide();
					$widget.find('input[name="giga_sa_email"]').val('');
				} else {
					$widget.slideUp(200);
					$widget.find('input[name="giga_sa_variation_id"]').val( 0 );
				}
			});

			$form.on('reset_data', function() {
				var $productWrap = $form.closest('.product');
				var $widget      = $productWrap.find('.giga-sa-notify-wrapper');

				if ( !$widget.length ) {
					$widget = $('.giga-sa-notify-wrapper.is-variable');
				}

				$widget.slideUp(200);
				$widget.find('input[name="giga_sa_variation_id"]').val( 0 );
			});
		});

		// -----------------------------------------------------------------
		// Restock Subscription Form
		// -----------------------------------------------------------------

		$(document).on('submit', '.giga-sa-notify-wrapper:not(.is-price-drop) .giga-sa-form', function(e) {
			e.preventDefault();
			submitAlertForm($(this), 'giga_sa_subscribe');
		});

		// -----------------------------------------------------------------
		// Price Drop Subscription Form
		// -----------------------------------------------------------------

		$(document).on('submit', '.giga-sa-notify-wrapper.is-price-drop .giga-sa-form', function(e) {
			e.preventDefault();
			submitAlertForm($(this), 'giga_sa_price_drop_subscribe');
		});

		/**
		 * Shared AJAX subscription form handler.
		 *
		 * @param {jQuery} $form
		 * @param {string} action  WP AJAX action name.
		 */
		function submitAlertForm($form, action) {
			var $wrapper  = $form.closest('.giga-sa-notify-wrapper');
			var $msgOk    = $wrapper.find('.giga-sa-message-success');
			var $msgErr   = $wrapper.find('.giga-sa-message-error');
			var $btn      = $form.find('.giga-sa-submit-btn');

			var productId = $form.find('input[name="giga_sa_product_id"]').val();
			var varId     = $form.find('input[name="giga_sa_variation_id"]').val();
			var alertType = $form.find('input[name="giga_sa_alert_type"]').val() || 'restock';
			var name      = $form.find('input[name="giga_sa_name"]').val() || '';
			var email     = $form.find('input[name="giga_sa_email"]').val().trim();
			var gdpr      = $form.find('input[name="giga_sa_gdpr"]').is(':checked') ? 1 : 0;

			if ( !email || !gdpr ) {
				return;
			}

			$msgOk.hide().html('');
			$msgErr.hide().html('');

			$btn.prop('disabled', true);
			var originalText = $btn.text();

			if ( typeof gigaSaParams !== 'undefined' && gigaSaParams.submitting ) {
				$btn.text(gigaSaParams.submitting);
			} else {
				$btn.text('...');
			}

			var nonce = typeof gigaSaParams !== 'undefined' ? gigaSaParams.nonce : '';

			$.ajax({
				url:  typeof gigaSaParams !== 'undefined' ? gigaSaParams.ajaxUrl : '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: {
					action:       action,
					nonce:        nonce,
					product_id:   productId,
					variation_id: varId,
					alert_type:   alertType,
					name:         name,
					email:        email,
					gdpr:         gdpr
				},
				success: function(response) {
					$btn.prop('disabled', false).text(originalText);

					if ( response.success ) {
						$form.slideUp(200);
						$msgOk.html(response.data.message || 'Subscribed successfully.').fadeIn();
					} else {
						var msg = response.data.message || 'An error occurred.';
						if ( response.data && response.data.code === 'duplicate' ) {
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
		}

		// -----------------------------------------------------------------
		// My Account — Unsubscribe
		// -----------------------------------------------------------------

		$(document).on('click', '.giga-sa-unsubscribe-btn', function(e) {
			e.preventDefault();

			var $btn   = $(this);
			var subId  = $btn.data('id');
			var nonce  = $btn.data('nonce');

			if ( !confirm( 'Are you sure you want to unsubscribe from this stock alert?' ) ) {
				return;
			}

			$btn.prop('disabled', true).text('...');

			$.ajax({
				url:  typeof gigaSaParams !== 'undefined' ? gigaSaParams.ajaxUrl : '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: {
					action:          'giga_sa_my_account_unsubscribe',
					subscription_id: subId,
					nonce:           nonce
				},
				success: function(response) {
					if ( response.success ) {
						var $card = $btn.closest('.giga-sa-subscription-card');
						$card.fadeOut(400, function() {
							$(this).remove();
							if ( $('.giga-sa-subscription-card').length === 0 ) {
								location.reload();
							}
						});
					} else {
						alert( response.data.message || 'Error occurred.' );
						$btn.prop('disabled', false).text('Unsubscribe');
					}
				},
				error: function() {
					alert( 'An unexpected error occurred.' );
					$btn.prop('disabled', false).text('Unsubscribe');
				}
			});
		});

		// -----------------------------------------------------------------
		// My Account — Re-subscribe
		// -----------------------------------------------------------------

		$(document).on('click', '.giga-sa-resubscribe-btn', function(e) {
			e.preventDefault();

			var $btn  = $(this);
			var subId = $btn.data('id');
			var nonce = $btn.data('nonce');

			$btn.prop('disabled', true).text('...');

			$.ajax({
				url:  typeof gigaSaParams !== 'undefined' ? gigaSaParams.ajaxUrl : '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: {
					action:          'giga_sa_resubscribe',
					subscription_id: subId,
					nonce:           nonce
				},
				success: function(response) {
					if ( response.success ) {
						var $card = $btn.closest('.giga-sa-subscription-card');
						// Move from historical to active section visually.
						$card.fadeOut(300, function() {
							$(this).remove();
						});
						// Show a brief success notice.
						var $notice = $('<div class="woocommerce-message" style="margin-top:1rem;">' + ( response.data.message || "You're back on the list!" ) + '</div>');
						$('.giga-sa-subscription-list').first().before( $notice );
						setTimeout(function() { $notice.fadeOut(); }, 4000);
					} else {
						alert( response.data.message || 'Error occurred.' );
						$btn.prop('disabled', false).text('Re-subscribe');
					}
				},
				error: function() {
					alert( 'An unexpected error occurred.' );
					$btn.prop('disabled', false).text('Re-subscribe');
				}
			});
		});

	});

})(jQuery);
