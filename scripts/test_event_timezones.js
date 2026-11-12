/* Run the actual browser enhancement against a minimal DOM; no external dependencies. */
'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(__dirname + '/../assets/js/event-timezones.js', 'utf8');

function fixture(locale, legacyIntl) {
	const rows = [
		{ 'data-start': '2026-11-12T21:00:00+09:00', 'data-end': '2026-11-12T22:30:00+09:00' },
		{ 'data-start': '2026-11-12T01:00:00+09:00', 'data-end': '' },
		{ 'data-start': '2026-07-12T21:00:00+09:00', 'data-end': '' }
	].map(attrs => ({ hidden: true, textContent: '', getAttribute: key => attrs[key] }));
	const select = {
		value: '', options: [], parentElement: { hidden: true },
		appendChild(option) { this.options.push(option); },
		addEventListener(name, callback) { this.change = callback; }
	};
	const schedule = {
		getAttribute: name => name === 'data-timezone' ? 'Asia/Tokyo' : locale,
		querySelector: () => select, querySelectorAll: () => rows
	};
	const document = {
		readyState: 'complete', querySelectorAll: () => [schedule], createElement: () => ({})
	};
	const testIntl = { DateTimeFormat: Intl.DateTimeFormat };
	if (!legacyIntl) { testIntl.supportedValuesOf = Intl.supportedValuesOf; }
	vm.runInNewContext(source, { document, Intl: testIntl, Date });
	function choose(zone) { select.value = zone; select.change(); }
	assert.equal(select.parentElement.hidden, false);
	assert.ok(select.options.some(option => option.value === 'Asia/Tokyo'));
	assert.ok(select.options.some(option => option.value === 'UTC'));
	choose('Europe/Berlin');
	assert.ok(/13[:.]00/.test(rows[0].textContent), locale + ': winter conversion');
	assert.ok(/14[:.]30/.test(rows[0].textContent), locale + ': end conversion');
	assert.ok(/14[:.]00/.test(rows[2].textContent), locale + ': summer conversion');
	choose('America/New_York');
	const expected = new Intl.DateTimeFormat(locale, {
		timeZone: 'America/New_York', year: 'numeric', month: '2-digit', day: '2-digit',
		hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZoneName: 'short'
	}).format(new Date('2026-11-11T16:00:00Z'));
	assert.equal(rows[1].textContent, expected + ' (America/New_York)');
	choose('');
	assert.ok(rows.every(row => row.hidden && row.textContent === ''));
	choose('invalid/timezone');
	assert.ok(rows.every(row => row.hidden));
}
for (const locale of ['de', 'en', 'fi', 'fr', 'it', 'ja', 'lb', 'nl']) { fixture(locale, false); }
fixture('de', true);
console.log('Browser timezone conversion regressions passed for all eight languages and legacy Intl.');
