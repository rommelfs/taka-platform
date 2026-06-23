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


	function activateTab(tab, options) {
		if (!tab) {
			return;
		}
		options = options || {};

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
		if (options.updateUrl) {
			updateTicketUrl(name, !!options.replaceUrl);
		}
	}

	function takaCssEscape(value) {
		if (window.CSS && CSS.escape) {
			return CSS.escape(value);
		}
		return String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
	}

	function ticketTabByName(name) {
		var ticketSection = document.getElementById('tickets');
		return ticketSection && name ? ticketSection.querySelector('[data-tab="' + takaCssEscape(name) + '"]') : null;
	}

	function decodeUrlValue(value) {
		try {
			return decodeURIComponent(String(value).replace(/\+/g, ' '));
		} catch (error) {
			return String(value);
		}
	}

	function requestedTicketTabFromHash() {
		var hash = window.location.hash || '';
		var prefix = '#tickets/';
		if (hash.indexOf(prefix) === 0) {
			return decodeUrlValue(hash.slice(prefix.length));
		}
		if (hash.indexOf('#tickets?') === 0) {
			try {
				var params = new URLSearchParams(hash.slice('#tickets?'.length));
				return params.get('taka_ticket_event') || params.get('taka_event') || '';
			} catch (error) {
				var match = hash.match(/[?&](?:taka_ticket_event|taka_event)=([^&]+)/);
				return match ? decodeUrlValue(match[1]) : '';
			}
		}
		return '';
	}

	function requestedTicketTab() {
		var hashEvent = requestedTicketTabFromHash();
		if (hashEvent) {
			return hashEvent;
		}
		try {
			var params = new URLSearchParams(window.location.search);
			return params.get('taka_ticket_event') || params.get('taka_event') || '';
		} catch (error) {
			var match = window.location.search.match(/[?&](?:taka_ticket_event|taka_event)=([^&]+)/);
			return match ? decodeUrlValue(match[1]) : '';
		}
	}

	function scrollToTicketsFromHash() {
		if ((window.location.hash || '').indexOf('#tickets') !== 0) {
			return;
		}
		var ticketSection = document.getElementById('tickets');
		if (ticketSection) {
			window.setTimeout(function () {
				ticketSection.scrollIntoView({ block: 'start' });
			}, 0);
		}
	}

	function updateTicketUrl(name, replaceUrl) {
		if (!name || !window.history || !window.history.pushState) {
			return;
		}
		try {
			var url = new URL(window.location.href);
			url.searchParams.delete('taka_event');
			url.searchParams.delete('taka_ticket_event');
			url.hash = 'tickets/' + encodeURIComponent(name);
			window.history[replaceUrl ? 'replaceState' : 'pushState']({ takaEvent: name }, '', url.toString());
		} catch (error) {
			return;
		}
	}

	function activateRequestedTicketTab(replaceUrl) {
		var requested = requestedTicketTab();
		var tab = ticketTabByName(requested);
		if (!tab) {
			return false;
		}
		activateTab(tab, { updateUrl: !!replaceUrl, replaceUrl: !!replaceUrl });
		return true;
	}

	function closeSharePanels(exceptRoot) {
		document.querySelectorAll('[data-taka-share-root]').forEach(function (root) {
			if (exceptRoot && root === exceptRoot) {
				return;
			}
			var panel = root.querySelector('[data-taka-share-panel]');
			var button = root.querySelector('[data-taka-share-event]');
			if (panel) {
				panel.setAttribute('hidden', 'hidden');
			}
			if (button) {
				button.setAttribute('aria-expanded', 'false');
			}
		});
	}

	function toggleSharePanel(button) {
		var root = button.closest('[data-taka-share-root]');
		var panel = root ? root.querySelector('[data-taka-share-panel]') : null;
		if (!root || !panel) {
			return;
		}
		var open = panel.hasAttribute('hidden');
		closeSharePanels(root);
		panel.toggleAttribute('hidden', !open);
		button.setAttribute('aria-expanded', String(open));
		if (open) {
			renderShareQr(root.querySelector('[data-taka-share-qr]'), button.getAttribute('data-share-url') || window.location.href);
		}
	}

	function copyText(text) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text);
		}
		return new Promise(function (resolve, reject) {
			var textarea = document.createElement('textarea');
			textarea.value = text;
			textarea.setAttribute('readonly', 'readonly');
			textarea.style.position = 'fixed';
			textarea.style.left = '-9999px';
			document.body.appendChild(textarea);
			textarea.select();
			try {
				if (document.execCommand('copy')) {
					resolve();
				} else {
					reject(new Error('Copy failed'));
				}
			} catch (error) {
				reject(error);
			}
			document.body.removeChild(textarea);
		});
	}

	function showShareFeedback(button, copiedLabel) {
		var label = button.getAttribute('data-original-label') || button.getAttribute('data-share-label') || button.textContent;
		button.setAttribute('data-original-label', label);
		copiedLabel = copiedLabel || button.getAttribute('data-share-copied-label') || label;
		button.textContent = copiedLabel;
		window.setTimeout(function () {
			button.textContent = label;
		}, 1800);
	}

	function renderShareQr(container, text) {
		if (!container || !text || container.getAttribute('data-qr-value') === text) {
			return;
		}
		var svg = makeQrSvg(text, container.getAttribute('data-qr-label') || 'QR-Code');
		if (!svg) {
			container.textContent = text;
			return;
		}
		container.innerHTML = svg;
		container.setAttribute('data-qr-value', text);
	}

	function makeQrSvg(text, label) {
		var bytes = utf8Bytes(text);
		if (bytes.length > 106) {
			return '';
		}
		var size = 37;
		var matrix = makeQrMatrix(size);
		var data = makeQrDataCodewords(bytes);
		var allCodewords = data.concat(reedSolomonRemainder(data, 26));
		var bits = [];
		allCodewords.forEach(function (codeword) {
			appendBits(bits, codeword, 8);
		});
		drawQrFunctionPatterns(matrix);
		placeQrData(matrix, bits);
		drawQrFormatBits(matrix, 0);
		return qrSvg(matrix.modules, label);
	}

	function utf8Bytes(text) {
		if (window.TextEncoder) {
			return Array.prototype.slice.call(new TextEncoder().encode(text));
		}
		return unescape(encodeURIComponent(text)).split('').map(function (character) {
			return character.charCodeAt(0);
		});
	}

	function appendBits(bits, value, length) {
		for (var i = length - 1; i >= 0; i--) {
			bits.push((value >>> i) & 1);
		}
	}

	function makeQrDataCodewords(bytes) {
		var bits = [];
		appendBits(bits, 4, 4);
		appendBits(bits, bytes.length, 8);
		bytes.forEach(function (byte) {
			appendBits(bits, byte, 8);
		});
		var capacity = 108 * 8;
		for (var i = 0; i < 4 && bits.length < capacity; i++) {
			bits.push(0);
		}
		while (bits.length % 8) {
			bits.push(0);
		}
		var codewords = [];
		for (var j = 0; j < bits.length; j += 8) {
			var codeword = 0;
			for (var k = 0; k < 8; k++) {
				codeword = (codeword << 1) | bits[j + k];
			}
			codewords.push(codeword);
		}
		for (var pad = 0; codewords.length < 108; pad++) {
			codewords.push(0 === pad % 2 ? 0xEC : 0x11);
		}
		return codewords;
	}

	var qrTables = null;

	function qrGfTables() {
		if (qrTables) {
			return qrTables;
		}
		var exp = [];
		var log = [];
		var value = 1;
		for (var i = 0; i < 255; i++) {
			exp[i] = value;
			log[value] = i;
			value <<= 1;
			if (value & 0x100) {
				value ^= 0x11D;
			}
		}
		for (var j = 255; j < 512; j++) {
			exp[j] = exp[j - 255];
		}
		qrTables = { exp: exp, log: log };
		return qrTables;
	}

	function qrGfMultiply(a, b) {
		if (0 === a || 0 === b) {
			return 0;
		}
		var tables = qrGfTables();
		return tables.exp[tables.log[a] + tables.log[b]];
	}

	function reedSolomonDivisor(degree) {
		var tables = qrGfTables();
		var result = new Array(degree).fill(0);
		result[degree - 1] = 1;
		for (var i = 0; i < degree; i++) {
			var root = tables.exp[i];
			for (var j = 0; j < degree; j++) {
				result[j] = qrGfMultiply(result[j], root);
				if (j + 1 < degree) {
					result[j] ^= result[j + 1];
				}
			}
		}
		return result;
	}

	function reedSolomonRemainder(data, degree) {
		var divisor = reedSolomonDivisor(degree);
		var result = new Array(degree).fill(0);
		data.forEach(function (byte) {
			var factor = byte ^ result.shift();
			result.push(0);
			for (var i = 0; i < degree; i++) {
				result[i] ^= qrGfMultiply(divisor[i], factor);
			}
		});
		return result;
	}

	function makeQrMatrix(size) {
		return {
			modules: new Array(size).fill(null).map(function () { return new Array(size).fill(false); }),
			reserved: new Array(size).fill(null).map(function () { return new Array(size).fill(false); })
		};
	}

	function setQrModule(matrix, row, col, dark) {
		if (row < 0 || col < 0 || row >= matrix.modules.length || col >= matrix.modules.length) {
			return;
		}
		matrix.modules[row][col] = !!dark;
		matrix.reserved[row][col] = true;
	}

	function drawQrFunctionPatterns(matrix) {
		var size = matrix.modules.length;
		drawQrFinder(matrix, 0, 0);
		drawQrFinder(matrix, size - 7, 0);
		drawQrFinder(matrix, 0, size - 7);
		for (var i = 8; i < size - 8; i++) {
			setQrModule(matrix, 6, i, 0 === i % 2);
			setQrModule(matrix, i, 6, 0 === i % 2);
		}
		drawQrAlignment(matrix, 30, 30);
		setQrModule(matrix, size - 8, 8, true);
		drawQrFormatBits(matrix, 0);
	}

	function drawQrFinder(matrix, row, col) {
		for (var dy = -1; dy <= 7; dy++) {
			for (var dx = -1; dx <= 7; dx++) {
				var rr = row + dy;
				var cc = col + dx;
				var dark = dy >= 0 && dy <= 6 && dx >= 0 && dx <= 6 && (0 === dy || 6 === dy || 0 === dx || 6 === dx || (dy >= 2 && dy <= 4 && dx >= 2 && dx <= 4));
				setQrModule(matrix, rr, cc, dark);
			}
		}
	}

	function drawQrAlignment(matrix, row, col) {
		for (var dy = -2; dy <= 2; dy++) {
			for (var dx = -2; dx <= 2; dx++) {
				setQrModule(matrix, row + dy, col + dx, 1 !== Math.max(Math.abs(dx), Math.abs(dy)));
			}
		}
	}

	function drawQrFormatBits(matrix, mask) {
		var size = matrix.modules.length;
		var bits = qrFormatBits(mask);
		for (var i = 0; i <= 5; i++) {
			setQrModule(matrix, 8, i, getQrBit(bits, i));
		}
		setQrModule(matrix, 8, 7, getQrBit(bits, 6));
		setQrModule(matrix, 8, 8, getQrBit(bits, 7));
		setQrModule(matrix, 7, 8, getQrBit(bits, 8));
		for (var j = 9; j < 15; j++) {
			setQrModule(matrix, 14 - j, 8, getQrBit(bits, j));
		}
		for (var k = 0; k < 8; k++) {
			setQrModule(matrix, size - 1 - k, 8, getQrBit(bits, k));
		}
		for (var m = 8; m < 15; m++) {
			setQrModule(matrix, 8, size - 15 + m, getQrBit(bits, m));
		}
		setQrModule(matrix, size - 8, 8, true);
	}

	function qrFormatBits(mask) {
		var data = (1 << 3) | mask;
		var bits = data << 10;
		for (var i = 14; i >= 10; i--) {
			if ((bits >>> i) & 1) {
				bits ^= 0x537 << (i - 10);
			}
		}
		return ((data << 10) | (bits & 0x3FF)) ^ 0x5412;
	}

	function getQrBit(value, index) {
		return 0 !== ((value >>> index) & 1);
	}

	function placeQrData(matrix, bits) {
		var size = matrix.modules.length;
		var bitIndex = 0;
		var upward = true;
		for (var right = size - 1; right >= 1; right -= 2) {
			if (6 === right) {
				right--;
			}
			for (var vert = 0; vert < size; vert++) {
				var row = upward ? size - 1 - vert : vert;
				for (var j = 0; j < 2; j++) {
					var col = right - j;
					if (!matrix.reserved[row][col]) {
						var bit = bitIndex < bits.length ? bits[bitIndex++] : 0;
						if (0 === (row + col) % 2) {
							bit ^= 1;
						}
						matrix.modules[row][col] = !!bit;
					}
				}
			}
			upward = !upward;
		}
	}

	function qrSvg(modules, label) {
		var quiet = 4;
		var size = modules.length;
		var path = '';
		for (var row = 0; row < size; row++) {
			for (var col = 0; col < size; col++) {
				if (modules[row][col]) {
					path += 'M' + (col + quiet) + ' ' + (row + quiet) + 'h1v1h-1z';
				}
			}
		}
		var dimension = size + quiet * 2;
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + dimension + ' ' + dimension + '" role="img" aria-label="' + escapeHtml(label) + '" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#fff"/><path fill="#111" d="' + path + '"/></svg>';
	}

	function escapeHtml(value) {
		return String(value).replace(/[&<>"']/g, function (character) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character];
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

		var copyButton = event.target.closest('[data-taka-copy-share-url]');
		if (copyButton) {
			event.preventDefault();
			var root = copyButton.closest('[data-taka-share-root]');
			var trigger = root ? root.querySelector('[data-taka-share-event]') : null;
			var shareUrl = trigger ? trigger.getAttribute('data-share-url') : '';
			copyText(shareUrl || window.location.href).then(function () {
				showShareFeedback(copyButton, trigger ? trigger.getAttribute('data-share-copied-label') : '');
			}).catch(function () {
				window.prompt(trigger ? trigger.getAttribute('data-share-prompt-label') : 'Copy link', shareUrl || window.location.href);
			});
			return;
		}

		var shareButton = event.target.closest('[data-taka-share-event]');
		if (shareButton) {
			event.preventDefault();
			toggleSharePanel(shareButton);
			return;
		}

		var stationLink = event.target.closest('[data-taka-ticket-tab]');
		if (stationLink) {
			var target = stationLink.getAttribute('data-taka-ticket-tab');
			var ticketSection = document.getElementById('tickets');
			var stationTab = ticketTabByName(target);
			if (stationTab) {
				event.preventDefault();
				activateTab(stationTab, { updateUrl: true });
				ticketSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
				stationTab.focus({ preventScroll: true });
			}
			return;
		}

		if (!event.target.closest('[data-taka-share-root]')) {
			closeSharePanels(null);
		}

		var tab = event.target.closest('[data-taka-tabs] [data-tab]');
		if (tab) {
			activateTab(tab, { updateUrl: true });
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			closeLanguageDropdowns();
			closeSharePanels(null);
		}
	});

	document.addEventListener('DOMContentLoaded', function () {
		if (activateRequestedTicketTab(true)) {
			scrollToTicketsFromHash();
		}
	});

	window.addEventListener('popstate', function () {
		if (activateRequestedTicketTab(false)) {
			scrollToTicketsFromHash();
		}
	});
}());
