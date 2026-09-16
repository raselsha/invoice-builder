/* global WP_IM, jQuery */
(function ($) {
	'use strict';

	// ── Line item row template ───────────────────────────────────────────
	function buildRow(idx) {
		return `
		<tr class="wim-item-row">
			<td class="col-drag">
				<span class="wim-drag-handle" title="Drag to reorder">
					<span class="dashicons dashicons-menu"></span>
				</span>
			</td>
			<td class="col-desc">
				<div class="wim-rte">
					<div class="wim-rte-toolbar">
						<button type="button" class="wim-rte-btn" data-cmd="bold" title="Bold"><b>B</b></button>
						<button type="button" class="wim-rte-btn wim-rte-link-btn" title="Add link">
							<span class="dashicons dashicons-admin-links"></span>
						</button>
					</div>
					<div class="wim-rte-editable" contenteditable="true" data-placeholder="Item description"></div>
					<input type="hidden" class="wim-rte-input" name="items[${idx}][description]" value="" />
				</div>
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

	// ── Line item description rich-text field (bold / link / 2nd line) ───
	// Enter creates a <br> instead of a nested <div>/<p>, keeping the saved
	// HTML flat and simple to sanitize (see WP_IM_Invoice::description_allowed_html()).
	try {
		document.execCommand('defaultParagraphSeparator', false, 'br');
	} catch (err) { /* unsupported in this browser; Enter still works, just less tidy HTML */ }

	function syncRte($rte) {
		$rte.find('.wim-rte-input').val($rte.find('.wim-rte-editable').html());
	}

	// Keep the editable's selection/focus intact when a toolbar button is
	// pressed — without this, clicking the button blurs the field first and
	// execCommand has nothing selected to act on.
	$(document).on('mousedown', '.wim-rte-btn', function (e) {
		e.preventDefault();
	});

	$(document).on('click', '.wim-rte-btn[data-cmd]', function () {
		var $rte = $(this).closest('.wim-rte');
		$rte.find('.wim-rte-editable').trigger('focus');
		document.execCommand($(this).data('cmd'), false, null);
		syncRte($rte);
	});

	$(document).on('click', '.wim-rte-link-btn', function () {
		var $rte = $(this).closest('.wim-rte');
		$rte.find('.wim-rte-editable').trigger('focus');
		var url = window.prompt('Link URL:', 'https://');
		if (url) {
			document.execCommand('createLink', false, url);
		}
		syncRte($rte);
	});

	$(document).on('input', '.wim-rte-editable', function () {
		syncRte($(this).closest('.wim-rte'));
	});

	$(document).on('blur', '.wim-rte-editable', function () {
		syncRte($(this).closest('.wim-rte'));
	});

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
		$('#wim-items-body').sortable('refresh');
	});

	// ── Terms & Conditions repeater (Settings page) ──────────────────────
	function buildTermRow() {
		// A brand-new row starts in edit mode (empty, not readonly) since
		// there's nothing to "view" yet.
		return `
		<div class="wim-term-row" data-mode="edit">
			<span class="wim-drag-handle" title="Drag to reorder">
				<span class="dashicons dashicons-menu"></span>
			</span>
			<span class="wim-term-index"></span>
			<textarea name="terms[]" rows="2" placeholder="Enter a term or condition"></textarea>
			<div class="wim-term-row-actions">
				<button type="button" class="wim-term-edit-btn" title="Edit">
					<span class="dashicons dashicons-edit"></span>
				</button>
				<button type="button" class="wim-remove-row wim-remove-term" title="Remove">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</div>
		</div>`;
	}

	function reIndexTerms() {
		$('#wim-terms-body .wim-term-row').each(function (i) {
			$(this).find('.wim-term-index').text((i + 1) + '.');
		});
	}

	// Grow a term's textarea to fit its full content — no internal scrollbar,
	// the whole term is always readable at a glance.
	function autoGrowTerm(el) {
		el.style.height = 'auto';
		el.style.height = el.scrollHeight + 'px';
	}

	$(function () {
		$('#wim-terms-body textarea').each(function () {
			autoGrowTerm(this);
		});
	});

	$(document).on('input', '#wim-terms-body textarea', function () {
		autoGrowTerm(this);
	});

	$(document).on('click', '#wim-add-term', function () {
		var $row = $(buildTermRow());
		$('#wim-terms-body').append($row);
		$('#wim-terms-body').sortable('refresh');
		reIndexTerms();
		autoGrowTerm($row.find('textarea')[0]);
		$row.find('textarea').trigger('focus');
	});

	$(document).on('click', '.wim-remove-term', function () {
		$(this).closest('.wim-term-row').remove();
		reIndexTerms();
	});

	// Toggle a term row between read-only "view" text and an editable textarea.
	$(document).on('click', '.wim-term-edit-btn', function () {
		var $btn = $(this);
		var $icon = $btn.find('.dashicons');
		var $row = $btn.closest('.wim-term-row');
		var $textarea = $row.find('textarea');
		var editing = 'edit' === $row.attr('data-mode');

		if (editing) {
			$row.attr('data-mode', 'view');
			$textarea.prop('readonly', true);
			$icon.removeClass('dashicons-yes-alt').addClass('dashicons-edit');
			$btn.attr('title', 'Edit');
		} else {
			$row.attr('data-mode', 'edit');
			$textarea.prop('readonly', false);
			$textarea.trigger('focus');
			$icon.removeClass('dashicons-edit').addClass('dashicons-yes-alt');
			$btn.attr('title', 'Done');
		}
		autoGrowTerm($textarea[0]);
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

	// ── Quick color themes (Settings → Invoice Colors) ───────────────────
	function setPickerColor(role, color) {
		var $input = $('.wim-color-hex[data-role="' + role + '"]');
		$input.val(color);
		$input.closest('.wim-color-picker').find('.wim-color-swatch').css('background', color);
	}

	$(document).on('click', '.wim-theme-swatch', function () {
		var $btn = $(this);
		setPickerColor('primary', $btn.data('primary'));
		setPickerColor('header', $btn.data('header'));
		setPickerColor('date', $btn.data('date'));
		setPickerColor('header-text', $btn.data('headerText'));
		setPickerColor('date-text', $btn.data('dateText'));
		$('.wim-theme-swatch').removeClass('is-active');
		$btn.addClass('is-active');
	});

	// ── Color picker (Settings → Invoice Colors) ─────────────────────────
	function isValidHex(v) {
		return /^#[0-9a-fA-F]{6}$/.test(v);
	}

	function hexToHsv(hex) {
		var r = parseInt(hex.substr(1, 2), 16) / 255;
		var g = parseInt(hex.substr(3, 2), 16) / 255;
		var b = parseInt(hex.substr(5, 2), 16) / 255;
		var max = Math.max(r, g, b), min = Math.min(r, g, b);
		var d = max - min;
		var h = 0;
		if (d !== 0) {
			if (max === r) h = ((g - b) / d) % 6;
			else if (max === g) h = (b - r) / d + 2;
			else h = (r - g) / d + 4;
			h *= 60;
			if (h < 0) h += 360;
		}
		var s = max === 0 ? 0 : d / max;
		var v = max;
		return { h: h, s: s, v: v };
	}

	function hsvToHex(h, s, v) {
		var c = v * s;
		var x = c * (1 - Math.abs((h / 60) % 2 - 1));
		var m = v - c;
		var r, g, b;
		if (h < 60)       { r = c; g = x; b = 0; }
		else if (h < 120) { r = x; g = c; b = 0; }
		else if (h < 180) { r = 0; g = c; b = x; }
		else if (h < 240) { r = 0; g = x; b = c; }
		else if (h < 300) { r = x; g = 0; b = c; }
		else              { r = c; g = 0; b = x; }
		function toHex(n) {
			var val = Math.max(0, Math.min(255, Math.round((n + m) * 255)));
			var s2 = val.toString(16);
			return s2.length === 1 ? '0' + s2 : s2;
		}
		return '#' + toHex(r) + toHex(g) + toHex(b);
	}

	// Sync a picker's popover (SV background, both cursor positions) from its current hex value.
	function syncPopoverFromHex($picker) {
		var hex = $picker.find('.wim-color-hex').val();
		if (!isValidHex(hex)) {
			return;
		}
		var hsv = hexToHsv(hex);
		$picker.data('hue', hsv.h);

		var $sv = $picker.find('.wim-color-sv-area');
		$sv.css('background',
			'linear-gradient(to top, #000, transparent), ' +
			'linear-gradient(to right, #fff, transparent), ' +
			'hsl(' + hsv.h + ', 100%, 50%)'
		);
		$picker.find('.wim-color-sv-cursor').css({ left: (hsv.s * 100) + '%', top: ((1 - hsv.v) * 100) + '%' });
		$picker.find('.wim-color-hue-cursor').css({ left: (hsv.h / 360 * 100) + '%' });
	}

	function applyColor($picker, hex) {
		$picker.find('.wim-color-hex').val(hex);
		$picker.find('.wim-color-swatch').css('background', hex);
	}

	$(document).on('click', '.wim-color-swatch', function (e) {
		e.stopPropagation();
		var $picker = $(this).closest('.wim-color-picker');
		var $popover = $picker.find('.wim-color-popover');
		var wasOpen = $popover.hasClass('is-open');
		$('.wim-color-popover').removeClass('is-open');
		if (!wasOpen) {
			$popover.addClass('is-open');
			syncPopoverFromHex($picker);
		}
	});

	$(document).on('click', '.wim-color-popover', function (e) {
		e.stopPropagation();
	});

	$(document).on('click', '.wim-color-swatch-option', function () {
		var color = $(this).data('color');
		var $picker = $(this).closest('.wim-color-picker');
		applyColor($picker, color);
		syncPopoverFromHex($picker);
		$picker.find('.wim-color-popover').removeClass('is-open');
	});

	$(document).on('input', '.wim-color-hex', function () {
		var v = $(this).val();
		if (v && v[0] !== '#') {
			v = '#' + v;
			$(this).val(v);
		}
		if (isValidHex(v)) {
			var $picker = $(this).closest('.wim-color-picker');
			$picker.find('.wim-color-swatch').css('background', v);
			syncPopoverFromHex($picker);
		}
	});

	$(document).on('click', function () {
		$('.wim-color-popover').removeClass('is-open');
	});

	// Saturation/Value square + hue slider dragging.
	var wimDrag = null; // { type: 'sv'|'hue', $picker }

	function svAreaToHsv($area, clientX, clientY, hue) {
		var rect = $area[0].getBoundingClientRect();
		var x = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
		var y = Math.max(0, Math.min(1, (clientY - rect.top) / rect.height));
		return { h: hue, s: x, v: 1 - y };
	}

	function hueSliderToHue($slider, clientX) {
		var rect = $slider[0].getBoundingClientRect();
		var x = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
		return x * 360;
	}

	$(document).on('mousedown', '.wim-color-sv-area', function (e) {
		e.preventDefault();
		wimDrag = { type: 'sv', $picker: $(this).closest('.wim-color-picker') };
		handleDragMove(e.clientX, e.clientY);
	});

	$(document).on('mousedown', '.wim-color-hue-slider', function (e) {
		e.preventDefault();
		wimDrag = { type: 'hue', $picker: $(this).closest('.wim-color-picker') };
		handleDragMove(e.clientX, e.clientY);
	});

	function handleDragMove(clientX, clientY) {
		if (!wimDrag) {
			return;
		}
		var $picker = wimDrag.$picker;
		var hue = $picker.data('hue') || 0;

		if (wimDrag.type === 'sv') {
			var hsv = svAreaToHsv($picker.find('.wim-color-sv-area'), clientX, clientY, hue);
			var hex = hsvToHex(hsv.h, hsv.s, hsv.v);
			applyColor($picker, hex);
			$picker.find('.wim-color-sv-cursor').css({ left: (hsv.s * 100) + '%', top: ((1 - hsv.v) * 100) + '%' });
		} else {
			var newHue = hueSliderToHue($picker.find('.wim-color-hue-slider'), clientX);
			var curHex = $picker.find('.wim-color-hex').val();
			var curHsv = isValidHex(curHex) ? hexToHsv(curHex) : { s: 1, v: 1 };
			var hex2 = hsvToHex(newHue, curHsv.s, curHsv.v);
			$picker.data('hue', newHue);
			applyColor($picker, hex2);
			$picker.find('.wim-color-sv-area').css('background',
				'linear-gradient(to top, #000, transparent), ' +
				'linear-gradient(to right, #fff, transparent), ' +
				'hsl(' + newHue + ', 100%, 50%)'
			);
			$picker.find('.wim-color-hue-cursor').css({ left: (newHue / 360 * 100) + '%' });
		}
	}

	$(document).on('mousemove', function (e) {
		if (wimDrag) {
			handleDragMove(e.clientX, e.clientY);
		}
	});

	$(document).on('mouseup', function () {
		wimDrag = null;
	});

	// ── Shared popover positioning helper ─────────────────────────────────
	// Positioned with fixed coordinates computed from the trigger's
	// getBoundingClientRect() (not CSS position:absolute) so it can't be
	// clipped by an ancestor's `overflow: hidden` (e.g. .wim-table-wrap) or
	// hidden behind a sticky table header — same technique as the custom
	// date-picker popovers elsewhere. Flips above the trigger automatically
	// when there isn't enough room below. `align` is 'left' (panel's left
	// edge lines up with the trigger's left edge, e.g. a dropdown) or
	// 'right' (panel's right edge lines up with the trigger's right edge).
	function positionPopover($trigger, $popover, align) {
		var rect = $trigger[0].getBoundingClientRect();
		var popWidth  = $popover.outerWidth();
		var popHeight = $popover.outerHeight();
		var gap = 8;
		var vw = window.innerWidth;
		var vh = window.innerHeight;

		var top;
		if (rect.bottom + gap + popHeight <= vh) {
			top = rect.bottom + gap;              // enough room below -> open downward
		} else if (rect.top - gap - popHeight >= 0) {
			top = rect.top - gap - popHeight;      // not enough below -> flip above
		} else {
			top = Math.max(gap, Math.min(rect.bottom + gap, vh - popHeight - gap));
		}

		var left = 'left' === align ? rect.left : rect.right - popWidth;
		left = Math.max(gap, Math.min(left, vw - popWidth - gap));

		$popover.css({ top: top + 'px', left: left + 'px' });
	}

	$(document).on('click', '.wim-share-btn', function (e) {
		e.stopPropagation();
		var $btn = $(this);
		var $popover = $btn.closest('.wim-share-wrap').find('.wim-share-popover');
		var wasOpen = $popover.hasClass('is-open');
		$('.wim-share-popover').removeClass('is-open');
		if (!wasOpen) {
			$popover.addClass('is-open');
			positionPopover($btn, $popover, 'right');
			$popover.find('.wim-share-url').trigger('focus').select();
		}
	});

	$(document).on('click', '.wim-share-popover', function (e) {
		e.stopPropagation();
	});

	$(document).on('click', function () {
		$('.wim-share-popover').removeClass('is-open');
	});

	$(window).on('scroll resize', function () {
		$('.wim-share-popover').removeClass('is-open');
	});

	// ── Custom dropdown (replaces native <select> visuals) ───────────────
	// The real <select> stays in the DOM (hidden) and is what actually
	// submits with the form; a styled trigger + option panel stay in sync
	// with it via setValue(). Reference pattern: doctor-appointment plugin's
	// initCustomSelect().
	function initCustomSelect($wrap) {
		var $native  = $wrap.find('.wim-select-native');
		var $trigger = $wrap.find('.wim-select-trigger');
		var $valueEl = $trigger.find('.wim-select-value');
		var $panel   = $wrap.find('.wim-select-panel');

		function setValue(val) {
			$native.val(val);
			var $opt = $panel.find('.wim-select-option[data-value="' + val + '"]');
			$valueEl.text($opt.text());
			$panel.find('.wim-select-option').removeClass('is-selected');
			$opt.addClass('is-selected');
		}

		$trigger.on('click', function (e) {
			e.stopPropagation();
			var wasOpen = $panel.hasClass('is-open');
			closeAllSelectPanels();
			if (!wasOpen) {
				$panel.css('width', $trigger.outerWidth() + 'px');
				$panel.addClass('is-open');
				$wrap.addClass('is-open');
				positionPopover($trigger, $panel, 'left');
			}
		});

		$panel.on('click', '.wim-select-option', function (e) {
			e.stopPropagation();
			setValue($(this).data('value'));
			closeAllSelectPanels();
			$native.trigger('change');
		});

		setValue($native.val());
	}

	function closeAllSelectPanels() {
		$('.wim-select-panel').removeClass('is-open');
		$('.wim-select').removeClass('is-open');
	}

	$(function () {
		$('.wim-select').each(function () {
			initCustomSelect($(this));
		});
	});

	$(document).on('click', '.wim-select-panel', function (e) {
		e.stopPropagation();
	});

	$(document).on('click', closeAllSelectPanels);
	$(window).on('scroll resize', closeAllSelectPanels);

	$(document).on('click', '.wim-share-copy', function () {
		var $btn = $(this);
		var $icon = $btn.find('.dashicons');
		var $input = $btn.closest('.wim-share-row').find('.wim-share-url');
		var url = $input.val();

		function showCopied() {
			$btn.addClass('is-copied').attr('title', 'Copied!').attr('aria-label', 'Copied!');
			$icon.removeClass('dashicons-admin-page').addClass('dashicons-yes-alt');
			setTimeout(function () {
				$btn.removeClass('is-copied').attr('title', 'Copy link').attr('aria-label', 'Copy link');
				$icon.removeClass('dashicons-yes-alt').addClass('dashicons-admin-page');
			}, 1500);
		}

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(url).then(showCopied, function () {
				$input.trigger('focus').select();
				document.execCommand('copy');
				showCopied();
			});
		} else {
			$input.trigger('focus').select();
			document.execCommand('copy');
			showCopied();
		}
	});

	// ── Delete confirmation ──────────────────────────────────────────────
	$(document).on('click', '.wim-delete-link', function () {
		if (!window.confirm(WP_IM.i18n.confirm_delete)) {
			return false;
		}
	});

	// ── Add / Edit Customer modal (Customers list page) ──────────────────
	function openCustomerModal(customer) {
		var $overlay = $('#wim-customer-modal-overlay');
		if (!$overlay.length) {
			return;
		}
		customer = customer || {};

		$('#wim-customer-modal-title').text(customer.id ? 'Edit Customer: ' + customer.name : 'Add New Customer');
		$('#wim-customer-post-id').val(customer.id || '');
		$('#wim-customer-name').val(customer.name || '');
		$('#wim-customer-email').val(customer.email || '');
		$('#wim-customer-phone').val(customer.phone || '');
		$('#wim-customer-address').val(customer.address || '');

		$overlay.addClass('is-open');
		$('#wim-customer-name').trigger('focus');
	}

	function closeCustomerModal() {
		$('#wim-customer-modal-overlay').removeClass('is-open');
	}

	$(document).on('click', '#wim-add-customer-btn, #wim-add-customer-btn-empty', function () {
		openCustomerModal(null);
	});

	$(document).on('click', '.wim-edit-customer-btn', function () {
		var $btn = $(this);
		openCustomerModal({
			id: $btn.data('id'),
			name: $btn.data('name'),
			email: $btn.data('email'),
			phone: $btn.data('phone'),
			address: $btn.data('address')
		});
	});

	$(document).on('click', '#wim-customer-modal-close, .wim-modal-cancel', function () {
		closeCustomerModal();
	});

	$(document).on('click', '#wim-customer-modal-overlay', function (e) {
		if (e.target === this) {
			closeCustomerModal();
		}
	});

	$(document).on('click', '.wim-modal', function (e) {
		e.stopPropagation();
	});

	$(document).on('keydown', function (e) {
		if (27 === e.keyCode) { // Escape
			closeCustomerModal();
		}
	});

	// ── Customer search (New Invoice → Client Details) ───────────────────
	var wimCustomerSearchTimer = null;

	function escText(s) {
		return $('<div>').text(s || '').html();
	}

	function renderCustomerResults(customers) {
		var $results = $('.wim-customer-search-results');

		if (!customers.length) {
			$results.html('<div class="wim-customer-search-empty">No matching customers.</div>').addClass('is-open');
			return;
		}

		var html = '';
		customers.forEach(function (c) {
			var sub = [c.email, c.phone].filter(Boolean).join(' · ');
			html += '<button type="button" class="wim-customer-result"' +
				' data-name="' + escText(c.name) + '"' +
				' data-email="' + escText(c.email) + '"' +
				' data-phone="' + escText(c.phone) + '"' +
				' data-address="' + escText(c.address) + '">' +
				'<span class="wim-customer-result-name">' + escText(c.name) + '</span>' +
				(sub ? '<span class="wim-customer-result-sub">' + escText(sub) + '</span>' : '') +
				'</button>';
		});
		$results.html(html).addClass('is-open');
	}

	$(document).on('input', '#wim-customer-search-input', function () {
		var term = $(this).val().trim();
		clearTimeout(wimCustomerSearchTimer);

		if (term.length < 2) {
			$('.wim-customer-search-results').removeClass('is-open').empty();
			return;
		}

		wimCustomerSearchTimer = setTimeout(function () {
			$.get(WP_IM.ajax_url, {
				action: 'wp_im_search_customers',
				nonce: WP_IM.nonce,
				term: term
			}, function (response) {
				if (response && response.success) {
					renderCustomerResults(response.data.customers);
				}
			});
		}, 300);
	});

	$(document).on('click', '.wim-customer-result', function () {
		var $r = $(this);
		$('#wim-client-name').val($r.data('name'));
		$('#wim-client-email').val($r.data('email'));
		$('#wim-client-phone').val($r.data('phone'));
		$('#wim-client-address').val($r.data('address'));
		$('#wim-customer-search-input').val($r.data('name'));
		$('.wim-customer-search-results').removeClass('is-open').empty();
	});

	$(document).on('click', function (e) {
		if (!$(e.target).closest('.wim-customer-search-wrap').length) {
			$('.wim-customer-search-results').removeClass('is-open');
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

	// ── Drag-to-reorder (line items + Terms & Conditions) ────────────────
	$(function () {
		if ($.fn.sortable) {
			if ($('#wim-items-body').length) {
				$('#wim-items-body').sortable({
					handle: '.wim-drag-handle',
					axis: 'y',
					placeholder: 'wim-item-row-placeholder',
					forcePlaceholderSize: true,
					helper: function (e, $row) {
						// Keep the dragged row's column widths (a plain <tr> helper
						// collapses to auto-width once detached from the table).
						$row.children().each(function () {
							$(this).width($(this).width());
						});
						return $row;
					},
					update: function () {
						reIndex();
						calcTotals();
					}
				});
			}
			if ($('#wim-terms-body').length) {
				$('#wim-terms-body').sortable({
					handle: '.wim-drag-handle',
					axis: 'y',
					placeholder: 'wim-term-row-placeholder',
					forcePlaceholderSize: true,
					update: function () {
						reIndexTerms();
					}
				});
			}
		}
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
