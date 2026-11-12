'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
let search;
const label = { before(input) { search = input; } };
const select = {
 dataset: {}, value: 'Asia/Tokyo', options: [
  { value: '', textContent: 'Automatic' },
  { value: 'Asia/Tokyo', textContent: 'Asia/Tokyo' },
  { value: 'Europe/Berlin', textContent: 'Europe/Berlin' },
  { value: 'America/New_York', textContent: 'America/New_York' }
 ],
 getAttribute() { return 'Search timezones'; }, closest() { return label; },
 replaceChildren() { this.options = []; }, appendChild(option) { this.options.push(option); }
};
const document = {
 readyState: 'complete', querySelectorAll() { return [select]; },
 createElement() { return { setAttribute() {}, addEventListener(name, callback) { this.input = callback; } }; }
};
const window = {};
vm.runInNewContext(fs.readFileSync(__dirname + '/../assets/js/timezone-search.js', 'utf8'), { window, document });
assert.equal(search.type, 'search');
assert.equal(search.placeholder, 'Search timezones');
search.value = 'BERLIN'; search.input();
assert.deepEqual(select.options.map(o => o.value), ['', 'Asia/Tokyo', 'Europe/Berlin']);
assert.equal(select.value, 'Asia/Tokyo', 'Typing must not change the selected timezone or event time.');
search.value = 'new york'; search.input();
assert.deepEqual(select.options.map(o => o.value), ['', 'Asia/Tokyo', 'America/New_York']);
search.value = ''; search.input();
assert.equal(select.options.length, 4, 'Clearing the query must restore the complete list.');
window.TakaTimezoneSearch.initialize(select);
assert.equal(select.options.length, 4, 'Repeated initialization must not duplicate options.');
console.log('Timezone search regressions passed.');
