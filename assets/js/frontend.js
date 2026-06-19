(function () {
	'use strict';

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
		setActiveLocation(name);
	}

	function setActiveLocation(name) {
		document.querySelectorAll('[data-taka-ticket-tab]').forEach(function (link) {
			var isActive = link.getAttribute('data-taka-ticket-tab') === name;
			link.classList.toggle('is-active', isActive);
			if (isActive) {
				link.setAttribute('aria-current', 'true');
			} else {
				link.removeAttribute('aria-current');
			}
		});
	}

	function takaCssEscape(value) {
		if (window.CSS && CSS.escape) {
			return CSS.escape(value);
		}
		return String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
	}

	document.addEventListener('click', function (event) {
		var stationLink = event.target.closest('[data-taka-ticket-tab]');
		if (stationLink) {
			var target = stationLink.getAttribute('data-taka-ticket-tab');
			var ticketSection = document.getElementById('tickets');
			var tab = ticketSection ? ticketSection.querySelector('[data-tab="' + takaCssEscape(target) + '"]') : null;
			if (tab) {
				event.preventDefault();
				activateTab(tab);
				ticketSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
				tab.focus({ preventScroll: true });
			}
			return;
		}

		var tab = event.target.closest('[data-taka-tabs] [data-tab]');
		if (!tab) {
			return;
		}

		activateTab(tab);
	});

	document.addEventListener('DOMContentLoaded', function () {
		var activeTab = document.querySelector('[data-taka-tabs] [data-tab].is-active');
		if (activeTab) {
			setActiveLocation(activeTab.getAttribute('data-tab'));
		}
	});
}());
