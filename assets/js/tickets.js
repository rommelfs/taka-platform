(function () {
	'use strict';

	var activeModal = null;

	function closeModal() {
		if (!activeModal) {
			return;
		}
		activeModal.setAttribute('hidden', 'hidden');
		activeModal.classList.remove('is-open');
		document.documentElement.classList.remove('taka-info-modal-open');
		activeModal = null;
	}

	function openModal(id) {
		var modal = document.getElementById(id);
		if (!modal) {
			return;
		}
		closeModal();
		modal.removeAttribute('hidden');
		modal.classList.add('is-open');
		document.documentElement.classList.add('taka-info-modal-open');
		activeModal = modal;
		var panel = modal.querySelector('.taka-info-modal__panel');
		if (panel) {
			panel.focus({ preventScroll: true });
		}
	}

	document.addEventListener('click', function (event) {
		var checkoutToggle = event.target.closest('[data-taka-native-checkout-toggle]');
		if (checkoutToggle) {
			event.preventDefault();
			var target = document.getElementById(checkoutToggle.getAttribute('aria-controls'));
			if (!target) {
				return;
			}
			var isOpen = !target.hasAttribute('hidden');
			if (isOpen) {
				target.setAttribute('hidden', 'hidden');
				target.classList.remove('is-open');
				checkoutToggle.setAttribute('aria-expanded', 'false');
			} else {
				target.removeAttribute('hidden');
				target.classList.add('is-open');
				checkoutToggle.setAttribute('aria-expanded', 'true');
				syncCheckoutRedirect(target.closest('[data-taka-native-checkout]') || target);
			}
			return;
		}

		var promotionButton = event.target.closest('[data-taka-apply-promotion]');
		if (promotionButton) {
			var checkout = promotionButton.closest('[data-taka-native-checkout]');
			if (checkout) {
				event.preventDefault();
				applyPromotion(checkout);
			}
			return;
		}

		var openButton = event.target.closest('[data-taka-info-modal-open]');
		if (openButton) {
			event.preventDefault();
			openModal(openButton.getAttribute('data-taka-info-modal-open'));
			return;
		}

		if (event.target.closest('[data-taka-info-modal-close]')) {
			event.preventDefault();
			closeModal();
		}
	});

	document.addEventListener('keydown', function (event) {
		if ('Escape' === event.key) {
			closeModal();
		}
	});

	function syncParticipantFields(root) {
		var checkbox = root.querySelector('[data-taka-participant-self]');
		var fields = root.querySelector('[data-taka-participant-identity-fields]');
		syncParticipantSelfLabel(root);
		if (!checkbox || !fields) {
			return;
		}
		if (checkbox.checked) {
			copyBuyerToParticipantSelection(root);
		}
		fields.hidden = checkbox.checked && ticketQuantity(root) <= 1;
		refreshCheckoutReview(root);
	}

	function syncParticipantSelfLabel(root) {
		var checkbox = root.querySelector('[data-taka-participant-self]');
		var label = root.querySelector('[data-taka-participant-self-label]');
		if (!checkbox || !label) {
			return;
		}
		label.textContent = ticketQuantity(root) > 1
			? (checkbox.getAttribute('data-taka-label-multi') || label.textContent)
			: (checkbox.getAttribute('data-taka-label-single') || label.textContent);
	}

	function copyBuyerToParticipant(root) {
		[
			['buyer_first_name', 'participant_first_name'],
			['buyer_last_name', 'participant_last_name'],
			['buyer_email', 'participant_email'],
			['buyer_country', 'participant_country']
		].forEach(function (pair) {
			var source = root.querySelector('[name="' + pair[0] + '"]');
			var target = root.querySelector('[name="' + pair[1] + '"]');
			if (source && target) {
				target.value = source.value;
			}
		});
	}

	function copyBuyerToFirstTicketParticipant(root) {
		var firstRow = root.querySelector('[data-taka-ticket-participant-row="0"]');
		if (!firstRow) {
			return;
		}
		var defaults = buyerParticipantDefaults(root);
		Object.keys(defaults).forEach(function (fieldKey) {
			if ('dojo' === fieldKey || 'rank' === fieldKey || 'dietary_preference' === fieldKey) {
				return;
			}
			var target = firstRow.querySelector('[data-taka-ticket-participant-field="' + fieldKey + '"]');
			if (target) {
				target.value = defaults[fieldKey] || '';
			}
		});
	}

	function copyBuyerToParticipantSelection(root) {
		if (ticketQuantity(root) > 1) {
			copyBuyerToFirstTicketParticipant(root);
			return;
		}
		copyBuyerToParticipant(root);
	}

	function syncDietaryNote(root) {
		var select = root.querySelector('[data-taka-dietary-preference]');
		var noteWrap = root.querySelector('[data-taka-dietary-note-wrap]');
		if (!select || !noteWrap) {
			return;
		}
		noteWrap.hidden = 'other' !== select.value;
	}

	function parseJsonAttribute(element, name, fallback) {
		if (!element) {
			return fallback;
		}
		try {
			return JSON.parse(element.getAttribute(name) || '');
		} catch (error) {
			return fallback;
		}
	}

	function setSectionDisabled(section, disabled) {
		if (!section) {
			return;
		}
		section.querySelectorAll('input, select, textarea, button').forEach(function (field) {
			field.disabled = !!disabled;
		});
	}

	function fieldValue(root, name) {
		var field = root.querySelector('[name="' + name + '"]');
		return field ? field.value.trim() : '';
	}

	function fullName(first, last) {
		return [first, last].filter(Boolean).join(' ').trim();
	}

	function checkoutReturnUrl() {
		try {
			var url = new URL(window.location.href);
			['taka_ticket_order', 'taka_ticketing_error', 'taka_ticket_payment_cancelled', 'token', 'PayerID'].forEach(function (key) {
				url.searchParams.delete(key);
			});
			if (url.hash) {
				url.hash = url.hash
					.replace(/([?&])(?:token|PayerID)=[^&]*/gi, '')
					.replace(/\?&/, '?')
					.replace(/[?&]+$/, '');
			}
			return url.toString();
		} catch (error) {
			return window.location.href;
		}
	}

	function syncCheckoutRedirect(root) {
		if (!root) {
			return;
		}
		root.querySelectorAll('[name="redirect_to"]').forEach(function (field) {
			field.value = checkoutReturnUrl();
		});
	}

	function setReview(root, selector, value) {
		var target = root.querySelector(selector);
		if (target) {
			target.textContent = value || '-';
		}
	}

	function setReviewRow(root, selector, visible) {
		var row = root.querySelector(selector);
		if (row) {
			row.hidden = !visible;
		}
	}

	function selectedTicket(root) {
		return root.querySelector('[name="ticket_type_id"]:checked');
	}

	function ticketQuantity(root) {
		var field = root.querySelector('[data-taka-ticket-quantity]');
		return field ? Math.max(1, parseInt(field.value || '1', 10) || 1) : 1;
	}

	function syncTicketQuantityBounds(root) {
		var ticket = selectedTicket(root);
		var field = root.querySelector('[data-taka-ticket-quantity]');
		if (!ticket || !field) {
			return;
		}
		var max = parseInt(ticket.getAttribute('data-taka-ticket-max') || '0', 10) || 0;
		if (max > 0) {
			field.max = String(max);
			if ((parseInt(field.value || '1', 10) || 1) > max) {
				field.value = String(max);
			}
		} else {
			field.removeAttribute('max');
		}
	}

	function participantSectionLabel(section, key, fallback) {
		return section ? (section.getAttribute('data-taka-label-' + key) || fallback) : fallback;
	}

	function currentTicketParticipants(root) {
		var rows = [];
		root.querySelectorAll('[data-taka-ticket-participant-row]').forEach(function (row) {
			var index = parseInt(row.getAttribute('data-taka-ticket-participant-row') || '0', 10) || 0;
			rows[index] = {};
			row.querySelectorAll('[data-taka-ticket-participant-field]').forEach(function (field) {
				rows[index][field.getAttribute('data-taka-ticket-participant-field')] = field.value;
			});
		});
		return rows;
	}

	function buyerParticipantDefaults(root) {
		return {
			first_name: fieldValue(root, 'buyer_first_name'),
			last_name: fieldValue(root, 'buyer_last_name'),
			email: fieldValue(root, 'buyer_email'),
			country: fieldValue(root, 'buyer_country'),
			dojo: '',
			rank: '',
			dietary_preference: 'none'
		};
	}

	function makeInput(name, value, required, type, fieldKey) {
		var input = document.createElement('input');
		input.type = type || 'text';
		input.name = name;
		input.value = value || '';
		input.setAttribute('data-taka-ticket-participant-field', fieldKey);
		if (required) {
			input.required = true;
			input.setAttribute('aria-required', 'true');
		}
		return input;
	}

	function makeSelect(name, value, options, required, fieldKey) {
		var select = document.createElement('select');
		select.name = name;
		select.setAttribute('data-taka-ticket-participant-field', fieldKey);
		if (required) {
			select.required = true;
			select.setAttribute('aria-required', 'true');
		}
		Object.keys(options || {}).forEach(function (optionValue) {
			var option = document.createElement('option');
			option.value = optionValue;
			option.textContent = options[optionValue];
			option.selected = String(value || '') === String(optionValue);
			select.appendChild(option);
		});
		return select;
	}

	function appendParticipantField(grid, labelText, control) {
		var label = document.createElement('label');
		var span = document.createElement('span');
		span.textContent = labelText;
		if (control.required) {
			var marker = document.createElement('span');
			marker.className = 'taka-native-checkout__required';
			marker.setAttribute('aria-hidden', 'true');
			marker.textContent = ' *';
			span.appendChild(marker);
		}
		label.appendChild(span);
		label.appendChild(control);
		grid.appendChild(label);
	}

	function renderTicketParticipants(root) {
		var quantity = ticketQuantity(root);
		var multi = root.querySelector('[data-taka-multi-participant-section]');
		var single = root.querySelector('[data-taka-single-participant-section]');
		syncParticipantSelfLabel(root);
		if (!multi || !single) {
			return;
		}

		if (quantity <= 1) {
			multi.hidden = true;
			setSectionDisabled(multi, true);
			single.hidden = false;
			setSectionDisabled(single, false);
			syncParticipantFields(root);
			return;
		}

		var target = multi.querySelector('[data-taka-ticket-participants]');
		if (!target) {
			return;
		}
		var existing = currentTicketParticipants(root);
		var prefill = existing.length ? existing : parseJsonAttribute(multi, 'data-taka-participants-prefill', []);
		var countries = parseJsonAttribute(multi, 'data-taka-country-options', {});
		var dietary = parseJsonAttribute(multi, 'data-taka-dietary-options', {});
		var collectDietary = '1' === (multi.getAttribute('data-taka-dietary-enabled') || '0');
		var buyerDefaults = buyerParticipantDefaults(root);

		target.innerHTML = '';
		for (var index = 0; index < quantity; index++) {
			var rowData = prefill[index] || {};
			if (0 === index && root.querySelector('[data-taka-participant-self]:checked')) {
				rowData = Object.assign({}, rowData, {
					first_name: buyerDefaults.first_name,
					last_name: buyerDefaults.last_name,
					email: buyerDefaults.email,
					country: buyerDefaults.country
				});
			}
			var article = document.createElement('article');
			article.className = 'taka-native-participant-card';
			article.setAttribute('data-taka-ticket-participant-row', String(index));
			var heading = document.createElement('h5');
			heading.textContent = participantSectionLabel(multi, 'participant', 'Participant') + ' ' + (index + 1);
			article.appendChild(heading);

			var grid = document.createElement('div');
			grid.className = 'taka-native-checkout__grid';
			appendParticipantField(grid, participantSectionLabel(multi, 'first-name', 'First name'), makeInput('ticket_participants[' + index + '][first_name]', rowData.first_name || '', true, 'text', 'first_name'));
			appendParticipantField(grid, participantSectionLabel(multi, 'last-name', 'Last name'), makeInput('ticket_participants[' + index + '][last_name]', rowData.last_name || '', true, 'text', 'last_name'));
			appendParticipantField(grid, participantSectionLabel(multi, 'email', 'Email (optional)'), makeInput('ticket_participants[' + index + '][email]', rowData.email || '', false, 'email', 'email'));
			appendParticipantField(grid, participantSectionLabel(multi, 'country', 'Country'), makeSelect('ticket_participants[' + index + '][country]', rowData.country || '', countries, true, 'country'));
			appendParticipantField(grid, participantSectionLabel(multi, 'dojo', 'Dojo / Club'), makeInput('ticket_participants[' + index + '][dojo]', rowData.dojo || '', false, 'text', 'dojo'));
			appendParticipantField(grid, participantSectionLabel(multi, 'rank', 'Rank / Belt'), makeInput('ticket_participants[' + index + '][rank]', rowData.rank || '', false, 'text', 'rank'));
			if (collectDietary) {
				appendParticipantField(grid, participantSectionLabel(multi, 'dietary', 'Dietary preference'), makeSelect('ticket_participants[' + index + '][dietary_preference]', rowData.dietary_preference || 'none', dietary, false, 'dietary_preference'));
			}
			article.appendChild(grid);
			target.appendChild(article);
		}

		single.hidden = true;
		setSectionDisabled(single, true);
		multi.hidden = false;
		setSectionDisabled(multi, false);
		if (root.querySelector('[data-taka-participant-self]:checked')) {
			copyBuyerToFirstTicketParticipant(root);
		}
		refreshCheckoutReview(root);
	}

	function setPromotionMessage(root, message, isError) {
		var target = root.querySelector('[data-taka-promotion-message]');
		if (!target) {
			return;
		}
		target.textContent = message || '';
		target.classList.toggle('is-error', !!isError);
	}

	function renderPromotionBenefits(root, benefits) {
		var list = root.querySelector('[data-taka-promotion-benefits]');
		if (!list) {
			return;
		}
		list.innerHTML = '';
		(benefits || []).forEach(function (benefit) {
			var item = document.createElement('li');
			item.textContent = [benefit.label, benefit.value, benefit.note].filter(Boolean).join(' - ');
			list.appendChild(item);
		});
		list.hidden = !list.children.length;
	}

	function renderReviewLineItems(root, quote) {
		var row = root.querySelector('[data-taka-review-line-items-row]');
		var target = root.querySelector('[data-taka-review-line-items]');
		if (!row || !target) {
			return;
		}
		var items = quote && quote.line_items ? quote.line_items : [];
		var productItems = items.filter(function (item) {
			return 'product' === item.item_type;
		});
		row.hidden = !productItems.length;
		target.textContent = productItems.map(function (item) {
			return item.quantity + ' x ' + item.title + ' (' + item.total_display + ')';
		}).join(', ') || '-';
	}

	function formatMoney(amount, currency) {
		var numeric = parseFloat(amount || '0') || 0;
		var value = numeric.toFixed(2).replace(/\.00$/, '');
		return ('EUR' === currency ? '€' : currency + ' ') + value;
	}

	function refreshStandaloneReview(root) {
		var target = root.querySelector('[data-taka-standalone-total]');
		if (!target) {
			return;
		}
		var quantityField = root.querySelector('[name="standalone_product_quantity"]');
		var quantity = quantityField ? Math.max(1, parseInt(quantityField.value || '1', 10) || 1) : 1;
		var unit = parseFloat(target.getAttribute('data-taka-standalone-unit') || '0') || 0;
		var currency = target.getAttribute('data-taka-standalone-currency') || 'EUR';
		target.textContent = formatMoney(unit * quantity, currency);
	}

	function setPaymentRequired(root, required) {
		var section = root.querySelector('[data-taka-payment-section]');
		var radios = root.querySelectorAll('[name="payment_method"]');
		if (section) {
			section.hidden = !required;
		}
		radios.forEach(function (radio, index) {
			radio.required = !!required;
			if (required && !root.querySelector('[name="payment_method"]:checked') && 0 === index) {
				radio.checked = true;
			}
		});
	}

	function clearPromotion(root, message) {
		root._takaPromotionQuote = null;
		renderPromotionBenefits(root, []);
		setPaymentRequired(root, true);
		setPromotionMessage(root, message || '', false);
		refreshCheckoutReview(root);
	}

	function collectProductQuantities(root) {
		var quantities = [];
		root.querySelectorAll('[data-taka-product-quantity]').forEach(function (field) {
			var quantity = 0;
			if ('checkbox' === field.type) {
				quantity = field.checked ? 1 : 0;
			} else {
				quantity = parseInt(field.value || '0', 10) || 0;
			}
			if (quantity > 0) {
				quantities.push({ id: field.getAttribute('data-taka-product-id'), quantity: quantity });
			}
		});
		return quantities;
	}

	function shouldRefreshPricingOnInit(root) {
		var code = root.querySelector('[data-taka-promotion-code]');
		return ticketQuantity(root) > 1 || collectProductQuantities(root).length > 0 || !!(code && code.value.trim());
	}

	function requestPricing(root, requirePromotionCode) {
		var form = root.querySelector('form[data-taka-promotion-endpoint]');
		var code = root.querySelector('[data-taka-promotion-code]');
		var ticket = selectedTicket(root);
		if (!form || !code || !ticket) {
			return;
		}
		var value = code.value.trim();
		if (requirePromotionCode && !value) {
			setPromotionMessage(root, form.getAttribute('data-taka-promotion-empty') || 'Enter a promotion code first.', true);
			return;
		}

		var button = root.querySelector('[data-taka-apply-promotion]');
		if (button) {
			button.disabled = true;
		}

		var body = new URLSearchParams();
		body.set('action', form.getAttribute('data-taka-promotion-action') || 'taka_ticketing_apply_promotion');
		body.set('nonce', form.getAttribute('data-taka-promotion-nonce') || '');
		body.set('event_id', fieldValue(root, 'event_id'));
		body.set('ticket_type_id', ticket.value);
		body.set('ticket_quantity', String(ticketQuantity(root)));
		body.set('promotion_code', value);
		body.set('buyer_email', fieldValue(root, 'buyer_email'));
		body.set('language', fieldValue(root, 'language'));
		collectProductQuantities(root).forEach(function (item) {
			body.append('product_quantities[' + item.id + ']', String(item.quantity));
		});

		fetch(form.getAttribute('data-taka-promotion-endpoint'), {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (response) {
			return response.json();
		}).then(function (payload) {
			if (!payload || !payload.success) {
				throw new Error(payload && payload.data && payload.data.message ? payload.data.message : 'Promotion could not be applied.');
			}
			root._takaPromotionQuote = payload.data;
			setPromotionMessage(root, value ? (payload.data.message || '') : '', false);
			renderPromotionBenefits(root, payload.data.benefits || []);
			setPaymentRequired(root, !!payload.data.payment_required);
			refreshCheckoutReview(root);
		}).catch(function (error) {
			root._takaPromotionQuote = null;
			renderPromotionBenefits(root, []);
			setPaymentRequired(root, true);
			setPromotionMessage(root, error.message, true);
			refreshCheckoutReview(root);
		}).finally(function () {
			if (button) {
				button.disabled = false;
			}
		});
	}

	function applyPromotion(root) {
		requestPricing(root, true);
	}

	function refreshCheckoutReview(root) {
		refreshStandaloneReview(root);
		var ticket = selectedTicket(root);
		var quantity = ticketQuantity(root);
		var payment = root.querySelector('[name="payment_method"]:checked');
		var quote = root._takaPromotionQuote || null;
		var buyerName = fullName(fieldValue(root, 'buyer_first_name'), fieldValue(root, 'buyer_last_name'));
		var participantSelf = root.querySelector('[data-taka-participant-self]');
		var participantName = '';

		if (quantity > 1) {
			participantName = currentTicketParticipants(root).map(function (participant) {
				return fullName(participant.first_name || '', participant.last_name || '');
			}).filter(Boolean).join(', ');
		} else if (participantSelf && participantSelf.checked) {
			participantName = buyerName;
		} else {
			participantName = fullName(fieldValue(root, 'participant_first_name'), fieldValue(root, 'participant_last_name'));
		}

		setReview(root, '[data-taka-review-ticket]', ticket ? ((quantity > 1 ? quantity + ' x ' : '') + ticket.getAttribute('data-taka-ticket-name')) : '');
		setReview(root, '[data-taka-review-price]', ticket ? fallbackTicketTotal(ticket, quantity) : '');
		renderReviewLineItems(root, quote);
		setReviewRow(root, '[data-taka-review-promotion-row]', !!(quote && quote.promotion_code));
		setReview(root, '[data-taka-review-promotion]', quote && quote.promotion_code ? quote.promotion_code : '');
		setReviewRow(root, '[data-taka-review-discount-row]', !!(quote && quote.discount_amount && '0' !== quote.discount_amount));
		setReview(root, '[data-taka-review-discount]', quote && quote.discount_display ? quote.discount_display : '');
		setReview(root, '[data-taka-review-buyer]', buyerName);
		setReview(root, '[data-taka-review-participant]', participantName);
		if (quote && false === quote.payment_required) {
			var form = root.querySelector('form');
			setReview(root, '[data-taka-review-payment]', quote.no_payment_label || (form ? form.getAttribute('data-taka-no-payment-label') : ''));
		} else {
			setReview(root, '[data-taka-review-payment]', payment ? payment.getAttribute('data-taka-payment-label') : '');
		}
		setReview(root, '[data-taka-review-total]', quote && quote.final_amount_display ? quote.final_amount_display : fallbackCheckoutTotal(root, ticket, quantity));
	}

	function fallbackTicketTotal(ticket, quantity) {
		if (!ticket) {
			return '';
		}
		var unit = parseFloat(ticket.getAttribute('data-taka-ticket-unit') || '0') || 0;
		var currency = ticket.getAttribute('data-taka-ticket-currency') || 'EUR';
		return formatMoney(unit * Math.max(1, quantity || 1), currency);
	}

	function fallbackCheckoutTotal(root, ticket, quantity) {
		if (!ticket) {
			return '';
		}
		var currency = ticket.getAttribute('data-taka-ticket-currency') || 'EUR';
		var total = (parseFloat(ticket.getAttribute('data-taka-ticket-unit') || '0') || 0) * Math.max(1, quantity || 1);
		collectProductQuantities(root).forEach(function (item) {
			var field = root.querySelector('[data-taka-product-id="' + item.id + '"]');
			if (field) {
				total += (parseFloat(field.getAttribute('data-taka-product-unit') || '0') || 0) * item.quantity;
			}
		});
		return formatMoney(total, currency);
	}

	function checkoutForm(root) {
		return root.querySelector('[data-taka-checkout-form]');
	}

	function currentCheckoutStep(root) {
		var form = checkoutForm(root);
		var initial = form ? parseInt(form.getAttribute('data-taka-initial-step') || '1', 10) : 1;
		return root._takaCheckoutStep || Math.min(3, Math.max(1, initial || 1));
	}

	function setCheckoutError(root, messages) {
		var target = root.querySelector('[data-taka-checkout-errors]');
		if (!target) {
			return;
		}
		target.innerHTML = '';
		(messages || []).forEach(function (message) {
			if (!message) {
				return;
			}
			var item = document.createElement('p');
			item.textContent = message;
			target.appendChild(item);
		});
		target.hidden = !target.children.length;
	}

	function formError(root, key, fallback) {
		var form = checkoutForm(root);
		return form ? (form.getAttribute(key) || fallback) : fallback;
	}

	function fieldIsVisible(field) {
		return !field.closest('[hidden]');
	}

	function validateCheckoutStep(root, step) {
		var fields = [];
		root.querySelectorAll('[data-taka-checkout-step-panel="' + step + '"] input, [data-taka-checkout-step-panel="' + step + '"] select, [data-taka-checkout-step-panel="' + step + '"] textarea').forEach(function (field) {
			if (field.disabled || 'hidden' === field.type || !fieldIsVisible(field)) {
				return;
			}
			fields.push(field);
		});

		var firstInvalid = null;
		fields.some(function (field) {
			if (!field.checkValidity()) {
				firstInvalid = field;
				return true;
			}
			return false;
		});

		if (!firstInvalid) {
			setCheckoutError(root, []);
			return true;
		}

		var message = 3 === step && ('checkbox' === firstInvalid.type)
			? formError(root, 'data-taka-error-terms', 'Please accept the terms and privacy notice.')
			: (firstInvalid.validationMessage || formError(root, 'data-taka-error-required', 'Please complete the required fields before continuing.'));
		setCheckoutError(root, [message]);
		if ('function' === typeof firstInvalid.reportValidity) {
			firstInvalid.reportValidity();
		}
		firstInvalid.focus({ preventScroll: false });
		return false;
	}

	function focusCheckoutStep(root, step) {
		var target = root.querySelector('[data-taka-checkout-progress]') || root.querySelector('[data-taka-checkout-step-panel="' + step + '"]') || checkoutForm(root);
		if (!target) {
			return;
		}
		if (!target.hasAttribute('tabindex')) {
			target.setAttribute('tabindex', '-1');
		}
		if ('function' === typeof target.focus) {
			target.focus({ preventScroll: true });
		}
		target.scrollIntoView({
			block: 'start',
			behavior: window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
		});
	}

	function setCheckoutStep(root, step, focusStep) {
		step = Math.min(3, Math.max(1, parseInt(step || '1', 10) || 1));
		root._takaCheckoutStep = step;

		root.querySelectorAll('[data-taka-checkout-step-panel]').forEach(function (panel) {
			panel.hidden = parseInt(panel.getAttribute('data-taka-checkout-step-panel') || '1', 10) !== step;
		});

		root.querySelectorAll('[data-taka-checkout-step-indicator]').forEach(function (indicator) {
			var indicatorStep = parseInt(indicator.getAttribute('data-taka-checkout-step-indicator') || '1', 10) || 1;
			indicator.classList.toggle('is-active', indicatorStep === step);
			indicator.classList.toggle('is-complete', indicatorStep < step);
			if (indicatorStep === step) {
				indicator.setAttribute('aria-current', 'step');
			} else {
				indicator.removeAttribute('aria-current');
			}
		});

		root.querySelectorAll('[data-taka-checkout-prev]').forEach(function (button) {
			button.hidden = step <= 1;
		});
		root.querySelectorAll('[data-taka-checkout-next]').forEach(function (button) {
			button.hidden = step >= 3;
		});
		root.querySelectorAll('[data-taka-checkout-submit]').forEach(function (button) {
			button.hidden = step !== 3;
		});
		refreshCheckoutReview(root);
		if (focusStep) {
			focusCheckoutStep(root, step);
		}
	}

	function advanceCheckoutStep(root, nextStep) {
		var current = currentCheckoutStep(root);
		nextStep = Math.min(3, Math.max(1, parseInt(nextStep || current, 10) || current));
		if (nextStep > current) {
			for (var step = current; step < nextStep; step++) {
				setCheckoutStep(root, step, false);
				if (!validateCheckoutStep(root, step)) {
					return;
				}
			}
		} else {
			setCheckoutError(root, []);
		}
		setCheckoutStep(root, nextStep, true);
	}

	document.querySelectorAll('[data-taka-native-checkout]').forEach(function (root) {
		syncCheckoutRedirect(root);
		syncTicketQuantityBounds(root);
		renderTicketParticipants(root);
		syncParticipantFields(root);
		syncDietaryNote(root);
		refreshCheckoutReview(root);
		if (checkoutForm(root)) {
			setCheckoutStep(root, currentCheckoutStep(root), false);
		}
		if (shouldRefreshPricingOnInit(root)) {
			requestPricing(root, false);
		}
	});
	document.addEventListener('change', function (event) {
		var root = event.target.closest('[data-taka-native-checkout]');
		if (!root) {
			return;
		}
		if (event.target.matches('[name="ticket_type_id"], [data-taka-ticket-quantity], [data-taka-product-quantity]')) {
			syncTicketQuantityBounds(root);
			renderTicketParticipants(root);
			requestPricing(root, false);
		} else if (event.target.matches('[data-taka-participant-self]')) {
			renderTicketParticipants(root);
			syncParticipantFields(root);
		} else if (event.target.matches('[data-taka-dietary-preference]')) {
			syncDietaryNote(root);
			refreshCheckoutReview(root);
		} else {
			if (root.querySelector('[data-taka-participant-self]:checked')) {
				copyBuyerToParticipantSelection(root);
			}
			refreshCheckoutReview(root);
		}
	});
	document.addEventListener('click', function (event) {
		var stepTarget = event.target.closest('[data-taka-checkout-step-target]');
		if (stepTarget) {
			var root = stepTarget.closest('[data-taka-native-checkout]');
			if (root) {
				event.preventDefault();
				advanceCheckoutStep(root, stepTarget.getAttribute('data-taka-checkout-step-target'));
			}
			return;
		}
		var next = event.target.closest('[data-taka-checkout-next]');
		if (next) {
			var nextRoot = next.closest('[data-taka-native-checkout]');
			if (nextRoot) {
				event.preventDefault();
				advanceCheckoutStep(nextRoot, currentCheckoutStep(nextRoot) + 1);
			}
			return;
		}
		var prev = event.target.closest('[data-taka-checkout-prev]');
		if (prev) {
			var prevRoot = prev.closest('[data-taka-native-checkout]');
			if (prevRoot) {
				event.preventDefault();
				advanceCheckoutStep(prevRoot, currentCheckoutStep(prevRoot) - 1);
			}
		}
	});
	document.addEventListener('input', function (event) {
		var root = event.target.closest('[data-taka-native-checkout]');
		if (root) {
			if (event.target.matches('[data-taka-promotion-code]')) {
				clearPromotion(root, '');
			} else if (event.target.matches('[data-taka-ticket-quantity], [data-taka-product-quantity]')) {
				renderTicketParticipants(root);
				requestPricing(root, false);
			}
			if (root.querySelector('[data-taka-participant-self]:checked')) {
				copyBuyerToParticipantSelection(root);
			}
			refreshCheckoutReview(root);
		}
	});
	document.addEventListener('submit', function (event) {
		var root = event.target.closest('[data-taka-native-checkout]');
		if (!root || !checkoutForm(root)) {
			return;
		}
		syncCheckoutRedirect(root);
		if (currentCheckoutStep(root) < 3) {
			event.preventDefault();
			advanceCheckoutStep(root, currentCheckoutStep(root) + 1);
			return;
		}
		if (!validateCheckoutStep(root, 3)) {
			event.preventDefault();
			return;
		}
		if (root.querySelector('[data-taka-participant-self]:checked')) {
			copyBuyerToParticipantSelection(root);
		}
	});
}());
