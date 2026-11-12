'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const callbacks = {};
let scrolled = 0;
let replaced = '';
const ticketSection = { querySelector() { return null; }, scrollIntoView() { scrolled++; } };
const document = {
 addEventListener(name, callback) { callbacks['document:' + name] = callback; },
 getElementById(id) { return id === 'tickets' ? ticketSection : null; },
 querySelector(selector) { return selector === '[data-taka-single-event="only-event"]' ? {} : null; },
 querySelectorAll() { return []; }
};
const window = {
 location: { hash: '#tickets/only-event', search: '', href: 'https://example.test/#tickets/only-event' },
 addEventListener(name, callback) { callbacks[name] = callback; },
 setTimeout(callback) { callback(); },
 history: { pushState() {}, replaceState(state, title, url) { replaced = url; } }
};
vm.runInNewContext(fs.readFileSync(__dirname + '/../assets/js/frontend.js', 'utf8'), { window, document, URL, URLSearchParams });
callbacks['document:DOMContentLoaded']();
assert.equal(scrolled, 1, 'Initial single-event deep link must scroll to its visible details.');
assert.ok(replaced.endsWith('#tickets/only-event'));
callbacks.hashchange(); callbacks.popstate();
assert.equal(scrolled, 3, 'Hash changes and browser history must still work without a tab button.');
window.location.hash = '#tickets/unknown-event';
callbacks.hashchange();
assert.equal(scrolled, 3, 'Unknown event links must not activate an unrelated event.');
console.log('Single-event deep-link regressions passed.');
