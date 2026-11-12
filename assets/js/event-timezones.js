(function () {
	'use strict';

	function initialize() {
		if (typeof Intl === 'undefined' || !Intl.DateTimeFormat) { return; }
		document.querySelectorAll('[data-taka-timezone-schedule]').forEach(function (schedule) {
			var select = schedule.querySelector('[data-taka-view-timezone]');
			var localZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
			var eventZone = schedule.getAttribute('data-timezone');
			var zones = [localZone, eventZone, 'UTC'];
			if (Intl.supportedValuesOf) { zones = zones.concat(Intl.supportedValuesOf('timeZone')); }
			zones.filter(function (zone, index) { return zone && zones.indexOf(zone) === index; }).forEach(function (zone) {
				var option = document.createElement('option');
				option.value = zone;
				option.textContent = zone;
				select.appendChild(option);
			});
			function update() {
				var zone = select.value;
				var formatter;
				try {
					if (zone) {
						formatter = new Intl.DateTimeFormat(schedule.getAttribute('data-locale') || undefined, {
							timeZone: zone, year: 'numeric', month: '2-digit', day: '2-digit',
							hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZoneName: 'short'
						});
					}
				} catch (error) { zone = ''; }
				schedule.querySelectorAll('[data-taka-converted-time]').forEach(function (output) {
					output.hidden = !zone;
					if (!zone) { output.textContent = ''; return; }
					var times = [output.getAttribute('data-start'), output.getAttribute('data-end')].filter(Boolean);
					output.textContent = times.map(function (instant) { return formatter.format(new Date(instant)); }).join(' – ') + ' (' + zone + ')';
				});
			}
			select.addEventListener('change', update);
			// Show the visitor's local time alongside the original by default.
			if (localZone && localZone !== eventZone) { select.value = localZone; }
			select.parentElement.hidden = false;
			update();
		});
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initialize); }
	else { initialize(); }
}());
