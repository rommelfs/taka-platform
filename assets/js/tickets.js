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
		if (!checkbox || !fields) {
			return;
		}
		if (checkbox.checked) {
			copyBuyerToParticipant(root);
		}
		fields.hidden = checkbox.checked;
		refreshCheckoutReview(root);
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

	function syncDietaryNote(root) {
		var select = root.querySelector('[data-taka-dietary-preference]');
		var noteWrap = root.querySelector('[data-taka-dietary-note-wrap]');
		if (!select || !noteWrap) {
			return;
		}
		noteWrap.hidden = 'other' !== select.value;
	}

	function fieldValue(root, name) {
		var field = root.querySelector('[name="' + name + '"]');
		return field ? field.value.trim() : '';
	}

	function fullName(first, last) {
		return [first, last].filter(Boolean).join(' ').trim();
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

	function applyPromotion(root) {
		var form = root.querySelector('form[data-taka-promotion-endpoint]');
		var code = root.querySelector('[data-taka-promotion-code]');
		var ticket = selectedTicket(root);
		if (!form || !code || !ticket) {
			return;
		}
		var value = code.value.trim();
		if (!value) {
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
		body.set('promotion_code', value);
		body.set('buyer_email', fieldValue(root, 'buyer_email'));
		body.set('language', fieldValue(root, 'language'));

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
			setPromotionMessage(root, payload.data.message || '', false);
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

	function refreshCheckoutReview(root) {
		var ticket = selectedTicket(root);
		var payment = root.querySelector('[name="payment_method"]:checked');
		var quote = root._takaPromotionQuote || null;
		var buyerName = fullName(fieldValue(root, 'buyer_first_name'), fieldValue(root, 'buyer_last_name'));
		var participantSelf = root.querySelector('[data-taka-participant-self]');
		var participantName = '';

		if (participantSelf && participantSelf.checked) {
			participantName = buyerName;
		} else {
			participantName = fullName(fieldValue(root, 'participant_first_name'), fieldValue(root, 'participant_last_name'));
		}

		setReview(root, '[data-taka-review-ticket]', ticket ? ticket.getAttribute('data-taka-ticket-name') : '');
		setReview(root, '[data-taka-review-price]', ticket ? ticket.getAttribute('data-taka-ticket-price') : '');
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
		setReview(root, '[data-taka-review-total]', quote && quote.final_amount_display ? quote.final_amount_display : (ticket ? ticket.getAttribute('data-taka-ticket-price') : ''));
	}

	document.querySelectorAll('[data-taka-native-checkout]').forEach(function (root) {
		syncParticipantFields(root);
		syncDietaryNote(root);
		refreshCheckoutReview(root);
	});
	document.addEventListener('change', function (event) {
		var root = event.target.closest('[data-taka-native-checkout]');
		if (!root) {
			return;
		}
		if (event.target.matches('[name="ticket_type_id"]')) {
			var form = root.querySelector('form');
			clearPromotion(root, form ? form.getAttribute('data-taka-promotion-cleared') : '');
		} else if (event.target.matches('[data-taka-participant-self]')) {
			syncParticipantFields(root);
		} else if (event.target.matches('[data-taka-dietary-preference]')) {
			syncDietaryNote(root);
			refreshCheckoutReview(root);
		} else {
			if (root.querySelector('[data-taka-participant-self]:checked')) {
				copyBuyerToParticipant(root);
			}
			refreshCheckoutReview(root);
		}
	});
	document.addEventListener('input', function (event) {
		var root = event.target.closest('[data-taka-native-checkout]');
		if (root) {
			if (event.target.matches('[data-taka-promotion-code]')) {
				clearPromotion(root, '');
			}
			if (root.querySelector('[data-taka-participant-self]:checked')) {
				copyBuyerToParticipant(root);
			}
			refreshCheckoutReview(root);
		}
	});
}());
