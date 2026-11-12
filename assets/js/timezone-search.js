(function () {
	'use strict';
	function initialize(select) {
		if (select.dataset.timezoneSearchReady || select.options.length < 2) { return; }
		select.dataset.timezoneSearchReady = '1';
		var search = document.createElement('input');
		search.type = 'search';
		search.className = 'taka-timezone-search';
		search.placeholder = select.getAttribute('data-search-label');
		search.setAttribute('aria-label', search.placeholder);
		search.autocomplete = 'off';
		var label = select.closest('label');
		(label || select).before(search);
		var options = Array.from(select.options).map(function (option) { return { value: option.value, text: option.textContent }; });
		search.addEventListener('input', function () {
			var query = search.value.trim().toLowerCase().replace(/\s+/g, '_');
			var selected = select.value;
			select.replaceChildren();
			options.forEach(function (option) {
				// Keep the current choice and the reset option available while searching.
				if (option.value && option.value !== selected && (option.value + ' ' + option.text).toLowerCase().indexOf(query) === -1) { return; }
				var node = document.createElement('option');
				node.value = option.value;
				node.textContent = option.text;
				select.appendChild(node);
			});
			select.value = selected;
		});
	}
	window.TakaTimezoneSearch = { initialize: initialize };
	function scan() { document.querySelectorAll('[data-taka-timezone-search]').forEach(initialize); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', scan); }
	else { scan(); }
}());
