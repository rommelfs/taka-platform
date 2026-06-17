(function () {
	'use strict';

	var activeDrawer = null;

	function closeDrawer() {
		if (!activeDrawer) {
			return;
		}
		activeDrawer.setAttribute('hidden', 'hidden');
		activeDrawer.classList.remove('is-open');
		document.documentElement.classList.remove('taka-info-drawer-open');
		activeDrawer = null;
	}

	function openDrawer(id) {
		var drawer = document.getElementById(id);
		if (!drawer) {
			return;
		}
		closeDrawer();
		drawer.removeAttribute('hidden');
		drawer.classList.add('is-open');
		document.documentElement.classList.add('taka-info-drawer-open');
		activeDrawer = drawer;
		var panel = drawer.querySelector('.taka-info-drawer__panel');
		if (panel) {
			panel.focus({ preventScroll: true });
		}
	}

	document.addEventListener('click', function (event) {
		var openButton = event.target.closest('[data-taka-info-drawer-open]');
		if (openButton) {
			event.preventDefault();
			openDrawer(openButton.getAttribute('data-taka-info-drawer-open'));
			return;
		}

		if (event.target.closest('[data-taka-info-drawer-close]')) {
			event.preventDefault();
			closeDrawer();
		}
	});

	document.addEventListener('keydown', function (event) {
		if ('Escape' === event.key) {
			closeDrawer();
		}
	});
}());
