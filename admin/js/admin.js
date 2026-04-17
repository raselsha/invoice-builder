/* global WP_IM, jQuery */
(function ($) {
	'use strict';

	// ── Line item row template ───────────────────────────────────────────
	function buildRow(idx) {
		return `
		<tr class="wim-item-row">
			<td class="col-desc">
				<input type="text" name="items[${idx}][description]" placeholder="Item description" />
			</td>
			<td class="col-qty">
				<input type="number" name="items[${idx}][quantity]" value="1" min="0" step="0.01" class="wim-qty" />
			</td>
			<td class="col-price">
				<input type="number" name="items[${idx}][unit_price]" value="0.00" min="0" step="0.01" class="wim-price" />
			</td>
			<td class="col-tax">
				<input type="number" name="items[${idx}][tax_rate]" value="0" min="0" max="100" step="0.01" class="wim-tax" />
			</td>
			<td class="col-total">
				<span class="wim-line-total">0.00</span>
			</td>
			<td class="col-del">
				<button type="button" class="wim-remove-row" title="Remove">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</td>
		</tr>`;
	}

	// ── Recalculate a single row ─────────────────────────────────────────
	function calcRow($row) {
		var qty   = parseFloat($row.find('.wim-qty').val()) || 0;
		var price = parseFloat($row.find('.wim-price').val()) || 0;
		var tax   = parseFloat($row.find('.wim-tax').val()) || 0;
		var line  = qty * price;
		var total = line + line * (tax / 100);
		$row.find('.wim-line-total').text(total.toFixed(2));
	}

	// ── Recalculate all totals ───────────────────────────────────────────
	function calcTotals() {
		var subtotal  = 0;
		var taxTotal  = 0;

		$('.wim-item-row').each(function () {
			var $row  = $(this);
			var qty   = parseFloat($row.find('.wim-qty').val()) || 0;
			var price = parseFloat($row.find('.wim-price').val()) || 0;
			var tax   = parseFloat($row.find('.wim-tax').val()) || 0;
			var line  = qty * price;
			subtotal += line;
			taxTotal += line * (tax / 100);
		});

		var discount = parseFloat($('#wim-discount').val()) || 0;
		var total    = Math.max(0, subtotal + taxTotal - discount);

		$('#wim-subtotal').text(subtotal.toFixed(2));
		$('#wim-tax-total').text(taxTotal.toFixed(2));
		$('#wim-discount-display').text(discount.toFixed(2));
		$('#wim-total').text(total.toFixed(2));
	}

	// ── Re-index all row input names ─────────────────────────────────────
	function reIndex() {
		$('.wim-item-row').each(function (i) {
			$(this).find('input').each(function () {
				var name = $(this).attr('name');
				if (name) {
					$(this).attr('name', name.replace(/items\[\d+\]/, 'items[' + i + ']'));
				}
			});
		});
	}

	// ── Event: add row ───────────────────────────────────────────────────
	$(document).on('click', '#wim-add-item', function () {
		var idx = $('.wim-item-row').length;
		$('#wim-items-body').append(buildRow(idx));
	});

	// ── Event: remove row ────────────────────────────────────────────────
	$(document).on('click', '.wim-remove-row', function () {
		if ($('.wim-item-row').length <= 1) {
			alert('An invoice must have at least one item.');
			return;
		}
		$(this).closest('tr').remove();
		reIndex();
		calcTotals();
	});

	// ── Event: input change → recalc ────────────────────────────────────
	$(document).on('input change', '.wim-qty, .wim-price, .wim-tax, #wim-discount', function () {
		var $row = $(this).closest('.wim-item-row');
		if ($row.length) {
			calcRow($row);
		}
		calcTotals();
	});

	// ── Delete confirmation ──────────────────────────────────────────────
	$(document).on('click', '.wim-delete-link', function () {
		if (!window.confirm(WP_IM.i18n.confirm_delete)) {
			return false;
		}
	});

	// ── AJAX status update (inline quick-edit) ───────────────────────────
	$(document).on('change', '.wim-status-select', function () {
		var $select  = $(this);
		var post_id  = $select.data('post-id');
		var status   = $select.val();
		var $badge   = $select.closest('tr').find('.wim-badge');

		$.post(WP_IM.ajax_url, {
			action  : 'wp_im_update_status',
			nonce   : WP_IM.nonce,
			post_id : post_id,
			status  : status,
		}, function (response) {
			if (response.success) {
				// Update badge class and text
				$badge.attr('class', 'wim-badge wim-badge-' + status);
				$badge.text(status.charAt(0).toUpperCase() + status.slice(1));
			}
		});
	});

	// ── Init on page load ────────────────────────────────────────────────
	$(function () {
		// Calc on page load (edit mode)
		$('.wim-item-row').each(function () {
			calcRow($(this));
		});
		calcTotals();
	});

}(jQuery));
