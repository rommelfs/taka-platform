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

	function currentContextHash() {
		if (window.location.hash) {
			return window.location.hash;
		}

		var tickets = document.getElementById('tickets');
		if (!tickets) {
			return '';
		}

		var rect = tickets.getBoundingClientRect();
		var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
		var ticketSectionIsVisible = rect.top < viewportHeight * 0.75 && rect.bottom > viewportHeight * 0.2;
		if (!ticketSectionIsVisible) {
			return '';
		}

		var activeTicketTab = tickets.querySelector('[data-taka-tabs] [data-tab].is-active');
		if (!activeTicketTab) {
			return '';
		}

		var tabName = activeTicketTab.getAttribute('data-tab');
		return tabName ? '#tickets/' + encodeURIComponent(tabName) : '';
	}

	function updateLanguageLinkContext(link) {
		if (!link) {
			return;
		}

		try {
			var url = new URL(link.getAttribute('href'), window.location.href);
			var hash = currentContextHash();
			if (hash) {
				url.hash = hash.replace(/^#/, '');
			}
			link.href = url.toString();
		} catch (error) {
			// Leave the original href intact for older browsers or malformed URLs.
		}
	}

	function scrollToPageTop() {
		var target = document.getElementById('top') || document.body;
		var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		target.scrollIntoView({
			behavior: reduceMotion ? 'auto' : 'smooth',
			block: 'start'
		});
		if (window.history && window.history.replaceState) {
			window.history.replaceState(null, '', window.location.pathname + window.location.search + '#top');
		}
	}

	document.addEventListener('click', function (event) {
		var topLink = event.target.closest('[data-taka-scroll-top]');
		if (topLink) {
			event.preventDefault();
			scrollToPageTop();
			closeLanguageDropdowns();
			return;
		}

		var languageLink = event.target.closest('[data-taka-language-link]');
		if (languageLink) {
			updateLanguageLinkContext(languageLink);
			closeLanguageDropdowns();
			return;
		}

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
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			closeLanguageDropdowns();
		}
	});
}());
