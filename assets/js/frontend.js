(function () {
	'use strict';

	document.addEventListener('click', function (event) {
		var tab = event.target.closest('[data-taka-tabs] [data-tab]');
		if (!tab) {
			return;
		}

		var tabs = tab.closest('[data-taka-tabs]');
		var name = tab.getAttribute('data-tab');
		tabs.querySelectorAll('[data-tab]').forEach(function (button) {
			var isActive = button === tab;
			button.classList.toggle('is-active', isActive);
			button.setAttribute('aria-selected', String(isActive));
		});
		tabs.querySelectorAll('[data-panel]').forEach(function (panel) {
			var isActive = panel.getAttribute('data-panel') === name;
			panel.classList.toggle('is-active', isActive);
			panel.toggleAttribute('hidden', !isActive);
		});
	});
}());
