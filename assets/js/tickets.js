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
		var fields = root.querySelector('[data-taka-participant-fields]');
		if (!checkbox || !fields) {
			return;
		}
		fields.hidden = checkbox.checked;
		refreshCheckoutReview(root);
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

	function refreshCheckoutReview(root) {
		var ticket = root.querySelector('[name="ticket_type_id"]:checked');
		var payment = root.querySelector('[name="payment_method"]:checked');
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
		setReview(root, '[data-taka-review-buyer]', buyerName);
		setReview(root, '[data-taka-review-participant]', participantName);
		setReview(root, '[data-taka-review-payment]', payment ? payment.getAttribute('data-taka-payment-label') : '');
		setReview(root, '[data-taka-review-total]', ticket ? ticket.getAttribute('data-taka-ticket-price') : '');
	}

	document.querySelectorAll('[data-taka-native-checkout]').forEach(function (root) {
		syncParticipantFields(root);
		refreshCheckoutReview(root);
	});
	document.addEventListener('change', function (event) {
		var root = event.target.closest('[data-taka-native-checkout]');
		if (!root) {
			return;
		}
		if (event.target.matches('[data-taka-participant-self]')) {
			syncParticipantFields(root);
		} else {
			refreshCheckoutReview(root);
		}
	});
	document.addEventListener('input', function (event) {
		var root = event.target.closest('[data-taka-native-checkout]');
		if (root) {
			refreshCheckoutReview(root);
		}
	});
}());
