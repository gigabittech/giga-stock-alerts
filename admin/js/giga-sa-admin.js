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
	 * Open inline edit form below the given row.
	 *
	 * @param {jQuery} $row The <tr> element to edit.
	 */
	function openInlineEdit($row) {
		var id = $row.data('id');

		// Toggle: if same row is already being edited, close and return.
		// If different row is being edited, remove the old one first.
		var $existing = $('.giga-sa-inline-edit-row');
		if ($existing.length) {
			var existingId = $existing.data('editing-id');
			$existing.remove();
			$('.giga-sa-edit-active').removeClass('giga-sa-edit-active');
			if (existingId === id) {
				return;
			}
		}

		var name        = $row.data('name') || '';
		var email       = $row.data('email') || '';
		var status      = $row.data('status') || 'pending';
		var alertType   = $row.data('alert-type') || 'restock';
		var colCount    = $row.find('td').length;

		var statusHtml = '';
		$.each(statusOptions, function (i, opt) {
			statusHtml += '<option value="' + opt.value + '"' + (opt.value === status ? ' selected' : '') + '>' + opt.label + '</option>';
		});

		var alertHtml = '';
		$.each(alertTypeOptions, function (i, opt) {
			alertHtml += '<option value="' + opt.value + '"' + (opt.value === alertType ? ' selected' : '') + '>' + opt.label + '</option>';
		});

		var html =
			'<tr class="giga-sa-inline-edit-row" data-editing-id="' + id + '">' +
				'<td colspan="' + colCount + '">' +
					'<div class="giga-sa-edit-fields">' +
						'<div>' +
							'<label for="giga-sa-edit-name-' + id + '">Name</label>' +
							'<input type="text" id="giga-sa-edit-name-' + id + '" class="giga-sa-edit-name" value="' + $('<div>').text(name).html() + '" />' +
						'</div>' +
						'<div>' +
							'<label for="giga-sa-edit-email-' + id + '">Email</label>' +
							'<input type="email" id="giga-sa-edit-email-' + id + '" class="giga-sa-edit-email" value="' + $('<div>').text(email).html() + '" />' +
						'</div>' +
						'<div>' +
							'<label for="giga-sa-edit-status-' + id + '">Status</label>' +
							'<select id="giga-sa-edit-status-' + id + '" class="giga-sa-edit-status">' + statusHtml + '</select>' +
						'</div>' +
						'<div>' +
							'<label for="giga-sa-edit-alert-' + id + '">Alert Type</label>' +
							'<select id="giga-sa-edit-alert-' + id + '" class="giga-sa-edit-alert">' + alertHtml + '</select>' +
						'</div>' +
					'</div>' +
					'<div class="giga-sa-inline-edit-actions">' +
						'<button type="button" class="button button-primary giga-sa-save-btn" data-id="' + id + '">Save Changes</button>' +
						'<button type="button" class="button giga-sa-cancel-btn">Cancel</button>' +
						'<span class="giga-sa-edit-error" style="color:#b32d2e;margin-left:8px;"></span>' +
					'</div>' +
				'</td>' +
			'</tr>';

		$row.after(html);
		$row.find('.giga-sa-edit-btn').addClass('giga-sa-edit-active');
	}

	/**
	 * Save the inline edit via AJAX.
	 *
	 * @param {number} subscriptionId
	 */
	function saveInlineEdit(subscriptionId) {
		var $editRow = $('.giga-sa-inline-edit-row');
		var $saveBtn = $editRow.find('.giga-sa-save-btn');
		var $error   = $editRow.find('.giga-sa-edit-error');

		var data = {
			action:          'giga_sa_update_subscription',
			nonce:           (typeof gigaSAAdmin !== 'undefined' ? gigaSAAdmin.updateNonce : ''),
			id:              subscriptionId,
			customer_name:   $editRow.find('.giga-sa-edit-name').val(),
			email:           $editRow.find('.giga-sa-edit-email').val(),
			status:          $editRow.find('.giga-sa-edit-status').val()
		};

		$saveBtn.prop('disabled', true).text('Saving...');
		$error.text('');

		$.ajax({
			url:  (typeof gigaSAAdmin !== 'undefined' ? gigaSAAdmin.ajaxUrl : '/wp-admin/admin-ajax.php'),
			type: 'POST',
			data: data,
			success: function (response) {
				$saveBtn.prop('disabled', false).text('Save Changes');

				if (response.success) {
					var d   = response.data;
					var $row = $('tr[data-id="' + subscriptionId + '"]');

					// Update the row's data attributes
					$row.data('name', d.customer_name);
					$row.data('email', d.email);
					$row.data('status', d.status);
					$row.attr('data-name', d.customer_name);
					$row.attr('data-email', d.email);
					$row.attr('data-status', d.status);

					// Update email cell — clear and rebuild text content
					var $emailCell = $row.find('td.column-email');
					if ($emailCell.length) {
						var nameHtml = d.customer_name ? '<br><small>' + $('<div>').text(d.customer_name).html() + '</small>' : '';
						var $actions = $emailCell.find('.row-actions').detach();
						$emailCell.html('<strong>' + $('<div>').text(d.email).html() + '</strong>' + nameHtml);
						if ($actions.length) {
							$emailCell.append($actions);
						}
					}

					// Update status cell
					var $statusCell = $row.find('td.column-status');
					if ($statusCell.length) {
						$statusCell.html('<span class="giga-badge" style="' + d.status_style + '">' + $('<div>').text(d.status_label).html() + '</span>');
					}

					// Show success notice
					var $notice = $('<span class="giga-sa-update-notice"></span>').text(d.message);
					$emailCell.find('.row-actions').after($notice);
					setTimeout(function () { $notice.fadeOut(400, function () { $(this).remove(); }); }, 3000);

					closeInlineEdit();
				} else {
					$error.text(response.data.message || 'An error occurred.');
				}
			},
			error: function () {
				$saveBtn.prop('disabled', false).text('Save Changes');
				$error.text('Request failed. Please try again.');
			}
		});
	}

	// =====================================================================
	// Document Ready
	// =====================================================================

	$(document).ready(function () {

		// --- Colour pickers ---
		$('.giga-sa-color-picker').wpColorPicker();

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
		$(document).on('click', '.giga-sa-cancel-btn', function () {
			closeInlineEdit();
		});

		// --- Inline Edit: Save button ---
		$(document).on('click', '.giga-sa-save-btn', function () {
			var id = $(this).data('id');
			saveInlineEdit(id);
		});
	});

})(jQuery);
