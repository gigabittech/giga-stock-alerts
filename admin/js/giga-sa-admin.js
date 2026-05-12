/**
 * Admin JavaScript for Giga Stock Alerts.
 *
 * Handles settings page tabs and subscribers inline editing.
 *
 * @package GigaStockAlerts
 */
(function ($) {
	'use strict';

	// =====================================================================
	// Settings Tabs
	// =====================================================================

	var STORAGE_KEY = 'giga_sa_active_tab';
	var validTabs   = ['widget', 'email', 'general', 'advanced', 'support'];

	function activateTab(tab) {
		if (validTabs.indexOf(tab) === -1) {
			tab = 'widget';
		}

		$('.giga-sa-tab-btn').removeClass('active');
		$('.giga-sa-tab-btn[data-tab="' + tab + '"]').addClass('active');

		$('.giga-sa-tab-panel').hide();
		$('#tab-' + tab).show();

		try {
			localStorage.setItem(STORAGE_KEY, tab);
		} catch (e) {
			// Storage unavailable
		}

		if (history.replaceState) {
			history.replaceState(null, '', '#' + tab);
		}
	}

	// =====================================================================
	// Inline Edit — Subscribers Table
	// =====================================================================

	var statusOptions = [
		{ value: 'pending',      label: 'Pending' },
		{ value: 'confirmed',    label: 'Confirmed' },
		{ value: 'notified',     label: 'Notified' },
		{ value: 'purchased',    label: 'Purchased' },
		{ value: 'unsubscribed', label: 'Unsubscribed' }
	];

	var alertTypeOptions = [
		{ value: 'restock',    label: 'Restock' },
		{ value: 'price_drop', label: 'Price Drop' }
	];

	/**
	 * Remove any existing inline edit row and clear active state.
	 */
	function closeInlineEdit() {
		$('.giga-sa-inline-edit-row').remove();
		$('.giga-sa-edit-active').removeClass('giga-sa-edit-active');
	}

	/**
	 * Transform the current row into an editable state.
	 *
	 * @param {jQuery} $row The <tr> element to edit.
	 */
	function openInlineEdit($row) {
		if ($row.hasClass('is-editing')) return;

		var id        = $row.data('id');
		var name      = $row.data('name') || '';
		var email     = $row.data('email') || '';
		var status    = $row.data('status') || 'pending';

		$row.addClass('is-editing');

		// 1. Email & Name Cell
		var $emailCell = $row.find('.column-email');
		$emailCell.data('original-html', $emailCell.html());
		$emailCell.html(
			'<input type="email" class="inline-edit-input inline-email" value="' + $('<div>').text(email).html() + '" style="width:100%;"><br>' +
			'<input type="text" class="inline-edit-input inline-name" value="' + $('<div>').text(name).html() + '" placeholder="Customer Name" style="width:100%; margin-top:5px; font-size:11px;">'
		);

		// 2. Status Cell
		var $statusCell = $row.find('.column-status');
		$statusCell.data('original-html', $statusCell.html());
		var statusHtml = '<select class="inline-edit-select inline-status" style="width:100%;">';
		$.each(statusOptions, function (i, opt) {
			statusHtml += '<option value="' + opt.value + '"' + (opt.value === status ? ' selected' : '') + '>' + opt.label + '</option>';
		});
		statusHtml += '</select>';
		$statusCell.html(statusHtml);

		// 3. Actions Cell
		var $actionsCell = $row.find('.column-actions');
		$actionsCell.data('original-html', $actionsCell.html());
		$actionsCell.html(
			'<div class="giga-sa-inline-actions">' +
				'<button type="button" class="giga-sa-save-direct" data-id="' + id + '" title="Save">✅</button>' +
				'<button type="button" class="giga-sa-cancel-direct" title="Cancel">❌</button>' +
			'</div>'
		);
	}

	/**
	 * Cancel editing and restore original cells.
	 *
	 * @param {jQuery} $row
	 */
	function cancelInlineEdit($row) {
		$row.removeClass('is-editing');
		$row.find('.column-email, .column-status, .column-actions').each(function() {
			var original = $(this).data('original-html');
			if (original) {
				$(this).html(original);
			}
		});
	}

	/**
	 * Save the direct inline edit via AJAX.
	 *
	 * @param {jQuery} $row
	 */
	function saveDirectEdit($row) {
		var id = $row.data('id');
		var $saveBtn = $row.find('.giga-sa-save-direct');
		
		var data = {
			action:        'giga_sa_update_subscription',
			nonce:         (typeof gigaSAAdmin !== 'undefined' ? gigaSAAdmin.updateNonce : ''),
			id:            id,
			customer_name: $row.find('.inline-name').val(),
			email:         $row.find('.inline-email').val(),
			status:        $row.find('.inline-status').val()
		};

		$saveBtn.text('⏳').prop('disabled', true);

		$.post((typeof gigaSAAdmin !== 'undefined' ? gigaSAAdmin.ajaxUrl : '/wp-admin/admin-ajax.php'), data, function(response) {
			if (response.success) {
				var d = response.data;
				
				// Update row state
				$row.data('name', d.customer_name).attr('data-name', d.customer_name);
				$row.data('email', d.email).attr('data-email', d.email);
				$row.data('status', d.status).attr('data-status', d.status);

				$row.removeClass('is-editing');

				// Update static content
				var nameHtml = d.customer_name ? '<br><small>' + $('<div>').text(d.customer_name).html() + '</small>' : '';
				$row.find('.column-email').html('<strong>' + $('<div>').text(d.email).html() + '</strong>' + nameHtml);
				$row.find('.column-status').html('<span class="giga-sa-badge giga-sa-badge-' + d.status + '">' + d.status_label + '</span>');
				
				// Restore original actions (which has the edit button)
				$row.find('.column-actions').html($row.find('.column-actions').data('original-html'));

				// Visual feedback
				$row.css('background', '#ECFDF5');
				setTimeout(function() { $row.css('background', ''); }, 1000);
			} else {
				alert(response.data.message || 'Error saving changes.');
				$saveBtn.text('✅').prop('disabled', false);
			}
		});
	}

	// =====================================================================
	// Document Ready
	// =====================================================================

	$(document).ready(function () {

		// --- Colour pickers ---
		if ( $.fn.wpColorPicker ) {
			$('.giga-sa-color-picker').wpColorPicker();
		}

		// --- Tab init ---
		var hash    = window.location.hash.replace('#', '');
		var initial = hash || '';

		if (!initial) {
			try {
				initial = localStorage.getItem(STORAGE_KEY) || '';
			} catch (e) {
				initial = '';
			}
		}

		activateTab(initial);

		$('.giga-sa-tab-btn').on('click', function (e) {
			e.preventDefault();
			activateTab($(this).data('tab'));
		});

		$(window).on('hashchange', function () {
			var h = window.location.hash.replace('#', '');
			if (h && validTabs.indexOf(h) !== -1) {
				activateTab(h);
			}
		});

		$('.giga-sa-settings-wrap form').on('submit', function () {
			var active = '';
			try {
				active = localStorage.getItem(STORAGE_KEY) || '';
			} catch (e) {
				active = '';
			}
			if (active && validTabs.indexOf(active) !== -1) {
				var action = $(this).attr('action');
				if (action && action.indexOf('#') === -1) {
					$(this).attr('action', action + '#' + active);
				}
			}
		});

		// --- Copy to clipboard ---
		$('.giga-sa-copy-btn').on('click', function () {
			var $btn  = $(this);
			var $area = $('.giga-sa-sysinfo');

			$area.select();

			try {
				document.execCommand('copy');
				var original = $btn.text();
				$btn.text('Copied!');
				setTimeout(function () {
					$btn.text(original);
				}, 2000);
			} catch (e) {
				// Fallback
			}
		});

		// --- Inline Edit: Edit button ---
		$(document).on('click', '.giga-sa-edit-btn', function (e) {
			e.preventDefault();
			var $row = $(this).closest('tr');
			openInlineEdit($row);
		});

		// --- Inline Edit: Cancel button ---
		$(document).on('click', '.giga-sa-cancel-direct', function (e) {
			e.preventDefault();
			cancelInlineEdit($(this).closest('tr'));
		});

		// --- Inline Edit: Save button ---
		$(document).on('click', '.giga-sa-save-direct', function (e) {
			e.preventDefault();
			saveDirectEdit($(this).closest('tr'));
		});
	});

})(jQuery);
