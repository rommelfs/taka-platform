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


	function activateTab(tab) {
		if (!tab) {
			return;
		}

		var tabs = tab.closest('[data-taka-tabs]');
		if (!tabs) {
			return;
		}

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
	}

	function takaCssEscape(value) {
		if (window.CSS && CSS.escape) {
			return CSS.escape(value);
		}
		return String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
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

		var stationLink = event.target.closest('[data-taka-ticket-tab]');
		if (stationLink) {
			var target = stationLink.getAttribute('data-taka-ticket-tab');
			var ticketSection = document.getElementById('tickets');
			var stationTab = ticketSection ? ticketSection.querySelector('[data-tab="' + takaCssEscape(target) + '"]') : null;
			if (stationTab) {
				event.preventDefault();
				activateTab(stationTab);
				ticketSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
				stationTab.focus({ preventScroll: true });
			}
			return;
		}

		var tab = event.target.closest('[data-taka-tabs] [data-tab]');
		if (tab) {
			activateTab(tab);
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			closeLanguageDropdowns();
		}
	});
}());
