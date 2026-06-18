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
			button.classList.toggle('is-active', button === tab);
		});
		tabs.querySelectorAll('[data-panel]').forEach(function (panel) {
			panel.classList.toggle('is-active', panel.getAttribute('data-panel') === name);
		});
	});
}());
