(function () {
	'use strict';

	function closeLanguageDropdowns() {
		document.querySelectorAll('.taka-language-dropdown.is-open').forEach(function (dropdown) {
			dropdown.classList.remove('is-open');
			var trigger = dropdown.querySelector('[data-taka-language-dropdown]');
			if (trigger) {
				trigger.setAttribute('aria-expanded', 'false');
			}
		});
	}

	document.addEventListener('click', function (event) {
		var languageTrigger = event.target.closest('[data-taka-language-dropdown]');
		if (languageTrigger) {
			var dropdown = languageTrigger.closest('.taka-language-dropdown');
			var isOpen = dropdown.classList.contains('is-open');
			closeLanguageDropdowns();
			dropdown.classList.toggle('is-open', !isOpen);
			languageTrigger.setAttribute('aria-expanded', String(!isOpen));
			return;
		}

		if (!event.target.closest('.taka-language-dropdown')) {
			closeLanguageDropdowns();
		}

		var tab = event.target.closest('[data-taka-tabs] [data-tab]');
		if (tab) {
			var tabs = tab.closest('[data-taka-tabs]');
			var name = tab.getAttribute('data-tab');
			tabs.querySelectorAll('[data-tab]').forEach(function (button) {
				button.classList.toggle('is-active', button === tab);
			});
			tabs.querySelectorAll('[data-panel]').forEach(function (panel) {
				panel.classList.toggle('is-active', panel.getAttribute('data-panel') === name);
			});
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			closeLanguageDropdowns();
		}
	});
}());
