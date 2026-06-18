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
}());
