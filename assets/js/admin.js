document.addEventListener('click', function (event) {
  var addButton = event.target.closest('[data-taka-co-organizer-add]');
  var removeButton = event.target.closest('[data-taka-co-organizer-remove]');

  if (addButton) {
    event.preventDefault();
    var root = addButton.closest('[data-taka-co-organizers]');
    if (!root) {
      return;
    }
    var list = root.querySelector('[data-taka-co-organizer-list]');
    var template = root.querySelector('[data-taka-co-organizer-template]');
    if (!list || !template) {
      return;
    }
    var index = Date.now().toString();
    var html = template.innerHTML.replace(/__index__/g, index);
    var wrapper = document.createElement('div');
    wrapper.innerHTML = html.trim();
    while (wrapper.firstChild) {
      list.appendChild(wrapper.firstChild);
    }
    return;
  }

  if (removeButton) {
    event.preventDefault();
    var item = removeButton.closest('[data-taka-co-organizer-item]');
    if (item) {
      item.remove();
    }
  }
});

(function () {
  var i18n = window.takaPlatformAdminI18n || {};
  var storagePrefix = 'taka-platform:admin-layout:v2';
  var legacyStoragePrefixes = ['taka-platform:admin-layout:v1'];
  var suppressedStorageSections = [];
  var sectionStorageKeys = [];
  var protectedPostboxSelector = '#taka_event_details';

  function storageAvailable() {
    try {
      return !!window.localStorage;
    } catch (error) {
      return false;
    }
  }

  function screenKey() {
    var bodyClass = document.body ? document.body.className : '';
    var postType = bodyClass.match(/\bpost-type-([a-z0-9_-]+)/);
    var adminPage = bodyClass.match(/\b(?:toplevel_page|taka-platform_page|settings_page|admin_page)[_-]([a-z0-9_-]+)/);
    var pageParam = new window.URLSearchParams(window.location.search).get('page');

    if (postType) {
      return 'post-type-' + postType[1];
    }

    if (adminPage) {
      return adminPage[0];
    }

    if (pageParam) {
      return 'admin-page-' + pageParam;
    }

    return window.location.pathname + window.location.search;
  }

  function storageKey(section, index) {
    var key = section.getAttribute('data-taka-admin-section-key') || 'section';
    return storagePrefix + ':' + screenKey() + ':' + key + ':' + index;
  }

  function validStoredState(value) {
    return value === 'open' || value === 'closed';
  }

  function rememberPreference(section) {
    return section.getAttribute('data-taka-admin-section-remember-preference') !== '0';
  }

  function defaultOpen(section) {
    return section.getAttribute('data-taka-admin-section-default-state') === 'expanded';
  }

  function autoExpandOnError(section) {
    return section.getAttribute('data-taka-admin-section-auto-expand-error') !== '0';
  }

  function readStoredState(key) {
    if (!storageAvailable()) {
      return null;
    }

    try {
      var value = window.localStorage.getItem(key);
      if (value === null || validStoredState(value)) {
        return value;
      }
      window.localStorage.removeItem(key);
      return null;
    } catch (error) {
      return null;
    }
  }

  function writeStoredState(key, value) {
    if (!storageAvailable()) {
      return;
    }

    try {
      window.localStorage.setItem(key, value);
    } catch (error) {
      return;
    }
  }

  function removeStoredState(key) {
    if (!storageAvailable()) {
      return;
    }

    try {
      window.localStorage.removeItem(key);
    } catch (error) {
      return;
    }
  }

  function removeCurrentScreenStoredStates() {
    var prefixes = [storagePrefix].concat(legacyStoragePrefixes);
    var currentScreen = screenKey();
    var index;
    var key;

    if (!storageAvailable()) {
      return;
    }

    try {
      for (index = window.localStorage.length - 1; index >= 0; index--) {
        key = window.localStorage.key(index);
        if (!key) {
          continue;
        }
        prefixes.forEach(function (prefix) {
          if (key.indexOf(prefix + ':' + currentScreen + ':') === 0) {
            window.localStorage.removeItem(key);
          }
        });
      }
    } catch (error) {
      return;
    }
  }

  function resetWordPressLayoutPreferences() {
    var body;

    if (!i18n.ajaxUrl || !i18n.resetAdminLayoutNonce || typeof window.fetch === 'undefined') {
      return;
    }

    body = new window.URLSearchParams();
    body.append('action', i18n.resetAdminLayoutAction || 'taka_platform_reset_admin_layout');
    body.append('nonce', i18n.resetAdminLayoutNonce);
    body.append('screen', screenKey());

    window.fetch(i18n.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: body.toString()
    }).catch(function () {});
  }

  function shouldSkipField(field) {
    return field.disabled
      || field.type === 'hidden'
      || field.type === 'button'
      || field.type === 'submit'
      || field.type === 'reset';
  }

  function sectionNeedsAttention(section) {
    var attentionSelector = '.notice-error, .error, .form-invalid, .is-error, [aria-invalid="true"]';
    var fields = section.querySelectorAll('input, select, textarea');
    var index;

    if (!autoExpandOnError(section)) {
      return false;
    }

    if (section.querySelector(attentionSelector)) {
      return true;
    }

    for (index = 0; index < fields.length; index++) {
      if (shouldSkipField(fields[index])) {
        continue;
      }

      if (fields[index].willValidate && fields[index].validity && !fields[index].validity.valid) {
        return true;
      }
    }

    return false;
  }

  function suppressNextStoredToggle(section) {
    suppressedStorageSections.push(section);
    window.setTimeout(function () {
      var index = suppressedStorageSections.indexOf(section);
      if (index !== -1) {
        suppressedStorageSections.splice(index, 1);
      }
    }, 100);
  }

  function setSectionOpen(section, isOpen) {
    if (section.open === isOpen) {
      return;
    }

    suppressNextStoredToggle(section);
    section.open = isOpen;
  }

  function openWithoutStoring(section) {
    setSectionOpen(section, true);
  }

  function isEventEditorScreen() {
    return !!(document.body && document.body.classList.contains('post-type-taka_event'));
  }

  function isTopLevelSection(section) {
    return !section.parentElement || !section.parentElement.closest('[data-taka-admin-section]');
  }

  function sectionHasEditableFields(section) {
    return !!section.querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])');
  }

  function ensureOneEditableSectionVisible(sections) {
    var candidates;
    var fallback;

    if (!isEventEditorScreen()) {
      return;
    }

    candidates = sections.filter(function (section) {
      return isTopLevelSection(section) && sectionHasEditableFields(section);
    });

    if (!candidates.length || candidates.some(function (section) { return section.open; })) {
      return;
    }

    fallback = candidates.find(defaultOpen) || candidates[0];
    openWithoutStoring(fallback);
    if (fallback.takaAdminLayoutStorageKey) {
      writeStoredState(fallback.takaAdminLayoutStorageKey, 'open');
    }
  }

  function protectedPostboxes() {
    return Array.prototype.slice.call(document.querySelectorAll(protectedPostboxSelector));
  }

  function preferredPostboxContainer() {
    return document.getElementById('normal-sortables')
      || document.querySelector('#postbox-container-2 .meta-box-sortables');
  }

  function openProtectedPostbox(postbox) {
    var inside;
    var toggle;
    var childIndex;

    if (!postbox) {
      return;
    }

    postbox.classList.remove('closed');
    for (childIndex = 0; childIndex < postbox.children.length; childIndex++) {
      if (postbox.children[childIndex].classList.contains('inside')) {
        inside = postbox.children[childIndex];
        break;
      }
    }

    if (inside) {
      inside.removeAttribute('hidden');
      inside.style.display = '';
    }

    toggle = postbox.querySelector('.handlediv[aria-expanded]');
    if (toggle) {
      toggle.setAttribute('aria-expanded', 'true');
    }
  }

  function ensureProtectedPostboxesInMainColumn() {
    var container;

    if (!isEventEditorScreen()) {
      return;
    }

    container = preferredPostboxContainer();
    if (!container) {
      recoverProtectedPostboxes();
      return;
    }

    protectedPostboxes().forEach(function (postbox) {
      if (postbox.parentNode !== container) {
        container.insertBefore(postbox, container.firstChild);
      }
      openProtectedPostbox(postbox);
    });
  }

  function recoverProtectedPostboxes() {
    protectedPostboxes().forEach(openProtectedPostbox);
  }

  function observePostboxColumns() {
    var containers;

    if (!isEventEditorScreen() || typeof window.MutationObserver === 'undefined') {
      return;
    }

    containers = Array.prototype.slice.call(document.querySelectorAll('#normal-sortables, #side-sortables, #advanced-sortables'));
    containers.forEach(function (container) {
      var observer = new window.MutationObserver(function () {
        window.setTimeout(ensureProtectedPostboxesInMainColumn, 0);
      });
      observer.observe(container, { childList: true });
    });
  }

  function observeProtectedPostboxes() {
    if (typeof window.MutationObserver === 'undefined') {
      return;
    }

    protectedPostboxes().forEach(function (postbox) {
      var observer = new window.MutationObserver(function () {
        if (postbox.classList.contains('closed')) {
          openProtectedPostbox(postbox);
        }
      });
      observer.observe(postbox, { attributes: true, attributeFilter: ['class'] });
    });
  }

  function protectPostboxSorting() {
    var jq = window.jQuery;

    if (!isEventEditorScreen() || !jq || !jq.fn || !jq.fn.sortable) {
      return;
    }

    jq(function ($) {
      $('.meta-box-sortables').each(function () {
        if (!$(this).data('ui-sortable') && !$(this).data('sortable')) {
          return;
        }

        var currentCancel = $(this).sortable('option', 'cancel') || 'input,textarea,button,select,option';
        if (currentCancel.indexOf(protectedPostboxSelector) === -1) {
          $(this).sortable('option', 'cancel', currentCancel + ', ' + protectedPostboxSelector + ', ' + protectedPostboxSelector + ' *');
        }
      });
      window.setTimeout(ensureProtectedPostboxesInMainColumn, 0);
    });
  }

  function isStorageSuppressed(section) {
    return suppressedStorageSections.indexOf(section) !== -1;
  }

  function applyDefaultState(section) {
    setSectionOpen(section, sectionNeedsAttention(section) || defaultOpen(section));
  }

  function addResetControl(sections) {
    var heading = document.querySelector('.wrap > h1');
    var fallbackAnchor = document.querySelector('#poststuff') || document.querySelector('[data-taka-admin-section]');
    var resetWrap;
    var button;

    if ((!heading && !fallbackAnchor) || !sections.length) {
      return;
    }

    resetWrap = document.createElement('p');
    resetWrap.className = 'taka-admin-layout-reset';

    button = document.createElement('button');
    button.type = 'button';
    button.className = 'button';
    button.textContent = i18n.resetAdminLayout || 'Reset layout';
    button.title = i18n.resetAdminLayoutDescription || 'Restore the default expanded and collapsed admin sections on this screen.';

    button.addEventListener('click', function () {
      removeCurrentScreenStoredStates();
      resetWordPressLayoutPreferences();
      sectionStorageKeys.forEach(removeStoredState);
      sections.forEach(applyDefaultState);
      ensureProtectedPostboxesInMainColumn();
      ensureOneEditableSectionVisible(sections);
    });

    resetWrap.appendChild(button);
    if (heading) {
      heading.insertAdjacentElement('afterend', resetWrap);
    } else if (fallbackAnchor.parentNode) {
      fallbackAnchor.parentNode.insertBefore(resetWrap, fallbackAnchor);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var sections = Array.prototype.slice.call(document.querySelectorAll('[data-taka-admin-section]'));

    sections.forEach(function (section, index) {
      var key = storageKey(section, index);
      var storedState = rememberPreference(section) ? readStoredState(key) : null;

      section.takaAdminLayoutStorageKey = key;
      sectionStorageKeys.push(key);

      if (sectionNeedsAttention(section)) {
        openWithoutStoring(section);
      } else if (storedState === 'open') {
        setSectionOpen(section, true);
      } else if (storedState === 'closed') {
        setSectionOpen(section, false);
      }

      section.addEventListener('toggle', function () {
        if (!rememberPreference(section) || isStorageSuppressed(section)) {
          return;
        }

        writeStoredState(key, section.open ? 'open' : 'closed');
        window.setTimeout(function () {
          ensureOneEditableSectionVisible(sections);
        }, 0);
      });
    });

    ensureProtectedPostboxesInMainColumn();
    observeProtectedPostboxes();
    observePostboxColumns();
    protectPostboxSorting();
    ensureOneEditableSectionVisible(sections);
    addResetControl(sections);
  });

  document.addEventListener('click', function (event) {
    var postbox = event.target.closest(protectedPostboxSelector);
    var toggle;

    if (!postbox) {
      return;
    }

    toggle = event.target.closest('.handlediv, .hndle, .postbox-header');
    if (!toggle || event.target.closest('[data-taka-admin-section]')) {
      return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
    openProtectedPostbox(postbox);
    ensureProtectedPostboxesInMainColumn();
  }, true);

  document.addEventListener('invalid', function (event) {
    var section = event.target.closest('[data-taka-admin-section]');

    while (section) {
      openWithoutStoring(section);
      section = section.parentElement ? section.parentElement.closest('[data-taka-admin-section]') : null;
    }
  }, true);
})();

(function () {
  function openSectionFromHash(hash) {
    var target;
    var section;

    if (!hash || hash.length < 2) {
      return;
    }

    try {
      target = document.querySelector(hash);
    } catch (error) {
      return;
    }

    if (!target) {
      return;
    }

    section = target.matches('[data-taka-admin-section]') ? target : target.closest('[data-taka-admin-section]');
    if (section) {
      section.open = true;
    }
  }

  document.addEventListener('click', function (event) {
    var link = event.target.closest('a[href^="#taka-event-assistant-section-"]');
    if (!link) {
      return;
    }
    openSectionFromHash(link.getAttribute('href'));
  });

  window.addEventListener('hashchange', function () {
    openSectionFromHash(window.location.hash);
  });

  document.addEventListener('DOMContentLoaded', function () {
    openSectionFromHash(window.location.hash);
  });
})();

(function () {
  function updateInlineCreatePanel(name) {
    var checked = document.querySelector('[data-taka-inline-create-toggle="' + name + '"] input[type="radio"]:checked');
    var panels = document.querySelectorAll('[data-taka-inline-create-panel="' + name + '"]');
    var showCreate = checked && checked.value === 'create';

    panels.forEach(function (panel) {
      panel.hidden = !showCreate;
    });
  }

  document.addEventListener('change', function (event) {
    var group = event.target.closest('[data-taka-inline-create-toggle]');
    if (!group) {
      return;
    }
    updateInlineCreatePanel(group.getAttribute('data-taka-inline-create-toggle'));
  });

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-taka-inline-create-toggle]').forEach(function (group) {
      updateInlineCreatePanel(group.getAttribute('data-taka-inline-create-toggle'));
    });
  });
})();

document.addEventListener('click', function (event) {
  var addProgram = event.target.closest('[data-taka-program-add]');
  var removeProgram = event.target.closest('[data-taka-program-remove]');

  if (addProgram) {
    event.preventDefault();
    var root = addProgram.closest('[data-taka-program-items]');
    var list = root ? root.querySelector('[data-taka-program-list]') : null;
    var template = root ? root.querySelector('[data-taka-program-template]') : null;
    if (!list || !template) {
      return;
    }
    var index = Date.now().toString();
    var wrapper = document.createElement('div');
    wrapper.innerHTML = template.innerHTML.replace(/__index__/g, index).trim();
    while (wrapper.firstChild) {
      list.appendChild(wrapper.firstChild);
    }
    return;
  }

  if (removeProgram) {
    event.preventDefault();
    var item = removeProgram.closest('[data-taka-program-item]');
    if (item) {
      item.remove();
    }
  }
});


document.addEventListener('click', function (event) {
  var addEventOrganizer = event.target.closest('[data-taka-event-organizer-add]');
  var removeEventOrganizer = event.target.closest('[data-taka-event-organizer-remove]');

  if (addEventOrganizer) {
    event.preventDefault();
    var root = addEventOrganizer.closest('[data-taka-event-organizers]');
    var list = root ? root.querySelector('[data-taka-event-organizer-list]') : null;
    var template = root ? root.querySelector('[data-taka-event-organizer-template]') : null;
    if (!list || !template) {
      return;
    }
    var index = Date.now().toString();
    var wrapper = document.createElement('div');
    wrapper.innerHTML = template.innerHTML.replace(/__index__/g, index).trim();
    while (wrapper.firstChild) {
      list.appendChild(wrapper.firstChild);
    }
    return;
  }

  if (removeEventOrganizer) {
    event.preventDefault();
    var item = removeEventOrganizer.closest('[data-taka-event-organizer-item]');
    if (item) {
      item.remove();
    }
  }
});

(function () {
  function normalize(value) {
    return (value || '').toString().toLowerCase();
  }

  function filterOperationsList(input) {
    var root = input.closest('.taka-operations-admin');
    var rows = root ? root.querySelectorAll('[data-taka-operations-search-row]') : [];
    var query = normalize(input.value);

    rows.forEach(function (row) {
      var index = normalize(row.getAttribute('data-taka-operations-search-index'));
      row.hidden = query && index.indexOf(query) === -1;
    });
  }

  document.addEventListener('input', function (event) {
    if (!event.target.matches('[data-taka-operations-live-search]')) {
      return;
    }
    filterOperationsList(event.target);
  });

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-taka-operations-live-search]').forEach(filterOperationsList);
  });
})();

(function () {
  var dbPromise = null;
  var checkinLogPrefix = '[TAKA Event Operations] ';

  if (window.console && window.console.log) {
    window.console.log('TAKA check-in JS loaded');
  }

  function closestElement(target, selector) {
    return target && target.closest ? target.closest(selector) : null;
  }

  function getDeviceId() {
    var key = 'taka_operations_device_id';
    var existing = window.localStorage ? window.localStorage.getItem(key) : '';
    if (existing) {
      return existing;
    }
    var value = 'dev-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
    if (window.localStorage) {
      window.localStorage.setItem(key, value);
    }
    return value;
  }

  function openDb() {
    if (dbPromise) {
      return dbPromise;
    }
    dbPromise = new Promise(function (resolve, reject) {
      if (!window.indexedDB) {
        reject(new Error('IndexedDB is not available.'));
        return;
      }
      var request = window.indexedDB.open('taka-operations-checkin', 1);
      request.onupgradeneeded = function () {
        var db = request.result;
        if (!db.objectStoreNames.contains('manifests')) {
          db.createObjectStore('manifests', { keyPath: 'event_id' });
        }
        if (!db.objectStoreNames.contains('checkins')) {
          var store = db.createObjectStore('checkins', { keyPath: 'local_id' });
          store.createIndex('event_id', 'event_id', { unique: false });
          store.createIndex('ticket_token', 'ticket_token', { unique: false });
        }
      };
      request.onerror = function () {
        reject(request.error || new Error('IndexedDB could not be opened.'));
      };
      request.onsuccess = function () {
        resolve(request.result);
      };
    });
    return dbPromise;
  }

  function dbGet(storeName, key) {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(storeName, 'readonly');
        var request = tx.objectStore(storeName).get(key);
        request.onerror = function () { reject(request.error); };
        request.onsuccess = function () { resolve(request.result || null); };
      });
    });
  }

  function dbPut(storeName, value) {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(storeName, 'readwrite');
        tx.objectStore(storeName).put(value);
        tx.onerror = function () { reject(tx.error); };
        tx.oncomplete = function () { resolve(value); };
      });
    });
  }

  function dbAllByEvent(storeName, eventId) {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(storeName, 'readonly');
        var index = tx.objectStore(storeName).index('event_id');
        var request = index.getAll(parseInt(eventId, 10));
        request.onerror = function () { reject(request.error); };
        request.onsuccess = function () { resolve(request.result || []); };
      });
    });
  }

  function extractToken(payload) {
    payload = (payload || '').toString().trim();
    var match = payload.match(/\/checkin\/t\/([A-Za-z0-9_-]+)/);
    if (match) {
      return match[1];
    }
    return /^[A-Za-z0-9_-]{24,80}$/.test(payload) ? payload : '';
  }

  function label(root, key, fallback) {
    return root.getAttribute('data-label-' + key) || fallback;
  }

  function labelCount(root, key, count, fallback) {
    return label(root, key, fallback).replace('%d', count);
  }

  function logInfo(message, data) {
    if (window.console && window.console.log) {
      window.console.log(checkinLogPrefix + message, data || '');
    }
  }

  function logWarn(message, data) {
    if (window.console && window.console.warn) {
      window.console.warn(checkinLogPrefix + message, data || '');
    }
  }

  function logError(context, error) {
    if (window.console && window.console.error) {
      window.console.error(checkinLogPrefix + context, error);
    }
  }

  function isLocalhost() {
    return ['localhost', '127.0.0.1', '::1'].indexOf(window.location.hostname) !== -1;
  }

  function cameraSupportError(root) {
    if (!window.isSecureContext && !isLocalhost()) {
      return label(root, 'camera-insecure', 'Camera access requires HTTPS or localhost. Please open this check-in page over HTTPS.');
    }
    if (!window.navigator.mediaDevices || !window.navigator.mediaDevices.getUserMedia) {
      return label(root, 'camera-unsupported', 'Camera access is not supported in this browser/context.');
    }
    return '';
  }

  function cameraErrorMessage(root, error) {
    var name = error && error.name ? error.name : '';
    var raw = 'string' === typeof error ? error : (error && error.message ? error.message : '');
    var lower = raw.toLowerCase();
    if (lower.indexOf('permission') !== -1 || lower.indexOf('notallowed') !== -1 || lower.indexOf('not allowed') !== -1 || lower.indexOf('denied') !== -1) {
      return label(root, 'camera-permission', 'Camera permission denied. Please allow camera access in your browser.');
    }
    if (lower.indexOf('notfound') !== -1 || lower.indexOf('not found') !== -1 || lower.indexOf('no camera') !== -1 || lower.indexOf('no video') !== -1) {
      return label(root, 'camera-not-found', 'No camera device found.');
    }
    if (lower.indexOf('notreadable') !== -1 || lower.indexOf('in use') !== -1 || lower.indexOf('could not start') !== -1) {
      return label(root, 'camera-in-use', 'Camera is already in use by another app.');
    }
    if ('NotAllowedError' === name || 'SecurityError' === name || 'PermissionDeniedError' === name) {
      return label(root, 'camera-permission', 'Camera permission denied. Please allow camera access in your browser.');
    }
    if ('NotFoundError' === name || 'DevicesNotFoundError' === name) {
      return label(root, 'camera-not-found', 'No camera device found.');
    }
    if ('NotReadableError' === name || 'TrackStartError' === name) {
      return label(root, 'camera-in-use', 'Camera is already in use by another app.');
    }
    if ('OverconstrainedError' === name || 'ConstraintNotSatisfiedError' === name) {
      return error && error.message ? error.message : label(root, 'camera-failed', 'Camera could not be opened.');
    }
    return error && error.message ? error.message : label(root, 'camera-failed', 'Camera could not be opened.');
  }

  function cameraConstraints() {
    return [
      { video: { facingMode: { ideal: 'environment' } } },
      { video: { facingMode: 'environment' } },
      { video: true }
    ];
  }

  function canRetryCameraError(error) {
    var name = error && error.name ? error.name : '';
    var raw = 'string' === typeof error ? error : (error && error.message ? error.message : '');
    return ['OverconstrainedError', 'ConstraintNotSatisfiedError', 'TypeError'].indexOf(name) !== -1 || raw.toLowerCase().indexOf('constraint') !== -1;
  }

  function normalizeCameraError(root, error) {
    var normalized = error instanceof Error ? error : new Error(String(error || label(root, 'camera-failed', 'Camera could not be opened.')));
    normalized._takaCameraMessage = cameraErrorMessage(root, error);
    return normalized;
  }

  function getCameraStream(root, index) {
    var supportError = cameraSupportError(root);
    var constraints = cameraConstraints();
    index = index || 0;

    if (supportError) {
      return Promise.reject(new Error(supportError));
    }

    return window.navigator.mediaDevices.getUserMedia(constraints[index]).catch(function (error) {
      if (index + 1 < constraints.length && canRetryCameraError(error)) {
        logError('Camera constraint failed, trying fallback ' + (index + 2), error);
        return getCameraStream(root, index + 1);
      }
      throw normalizeCameraError(root, error);
    });
  }

  function showCameraStream(root, stream, mode) {
    var video = root.querySelector('[data-taka-scan-video]');
    var stop = root.querySelector('[data-taka-scan-stop]');

    if (!video) {
      stream.getTracks().forEach(function (track) { track.stop(); });
      throw new Error(label(root, 'camera-unsupported', 'Camera access is not supported in this browser/context.'));
    }

    video.setAttribute('autoplay', 'autoplay');
    video.setAttribute('playsinline', 'playsinline');
    video.muted = true;
    video.srcObject = stream;
    video.hidden = false;
    root._takaCameraActive = true;
    if (stop) {
      stop.hidden = false;
    }
    root._takaStopScanner = function () {
      stream.getTracks().forEach(function (track) { track.stop(); });
      video.srcObject = null;
      video.hidden = true;
      root._takaCameraActive = false;
      if (stop) {
        stop.hidden = true;
      }
    };
    return video.play().catch(function (error) {
      logError(mode + ' video playback failed', error);
    });
  }

  function ajax(root, action, data) {
    var form = new window.FormData();
    var ajaxUrl = root.getAttribute('data-ajax-url') || '';
    var nonce = root.getAttribute('data-nonce') || '';

    if (!ajaxUrl || !nonce || !action) {
      return Promise.reject(new Error(label(root, 'config-missing', 'Check-in configuration missing.')));
    }

    form.append('action', action);
    form.append('nonce', nonce);
    Object.keys(data || {}).forEach(function (key) {
      form.append(key, data[key]);
    });
    return window.fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: form
    }).then(function (response) {
      return response.json();
    }).then(function (json) {
      if (!json || !json.success) {
        throw new Error(json && json.data && json.data.message ? json.data.message : label(root, 'request-failed', 'Request failed.'));
      }
      return json.data;
    });
  }

  function configMissing(root, missing) {
    var message = label(root, 'config-missing', 'Check-in configuration missing.');
    if (missing && missing.length) {
      message += ' (' + missing.join(', ') + ')';
    }
    setResult(root, 'invalid', message);
    setOfflineStatus(root, message);
    logError('Check-in configuration missing', missing || []);
  }

  function validateConfig(root, mode) {
    var required = ['ajax-url', 'nonce', 'event-id'];
    var missing = [];

    if ('scan' === mode) {
      required.push('scan-action');
    } else if ('offline' === mode) {
      required.push('manifest-action');
    } else if ('sync' === mode) {
      required.push('sync-action');
    } else {
      required.push('scan-action', 'manifest-action', 'sync-action');
    }

    required.forEach(function (key) {
      if (!root.getAttribute('data-' + key)) {
        missing.push('data-' + key);
      }
    });

    if (missing.length) {
      configMissing(root, missing);
      return false;
    }
    return true;
  }

  function setResult(root, status, message, data) {
    var target = root.querySelector('[data-taka-scan-result]');
    if (!target) {
      return;
    }
    var parts = [message || status];
    if (data && data.participantName) {
      parts.push(data.participantName);
    }
    if (data && data.ticketType) {
      parts.push(data.ticketType);
    }
    if (data && data.paymentStatus) {
      parts.push(label(root, 'payment', 'Payment') + ': ' + data.paymentStatus);
    }
    if (data && data.checkedInAt) {
      parts.push(data.checkedInAt);
    }
    target.className = 'taka-operations-scan-result is-' + (status || 'info');
    target.textContent = parts.filter(Boolean).join(' / ');
  }

  function setOfflineStatus(root, text) {
    var target = root.querySelector('[data-taka-offline-status]');
    if (target) {
      target.textContent = text;
    }
  }

  function refreshPending(root) {
    var eventId = parseInt(root.getAttribute('data-event-id'), 10);
    return dbAllByEvent('checkins', eventId).then(function (items) {
      var count = items.filter(function (item) { return 'pending' === item.sync_status; }).length;
      var target = root.querySelector('[data-taka-offline-pending]');
      if (target) {
        target.textContent = count.toString();
      }
      return count;
    }).catch(function () {
      return 0;
    });
  }

  function loadOfflineData(root) {
    var eventId = parseInt(root.getAttribute('data-event-id'), 10);
    if (!validateConfig(root, 'offline')) {
      return Promise.resolve();
    }
    return ajax(root, root.getAttribute('data-manifest-action'), { event_id: eventId }).then(function (data) {
      return dbPut('manifests', {
        event_id: eventId,
        event_name: data.eventName || '',
        generated_at: data.generatedAt || '',
        expires_at: data.expiresAt || '',
        tickets: data.tickets || []
      }).then(function () {
        setOfflineStatus(root, labelCount(root, 'offline-ready', (data.tickets || []).length, 'Offline ready: %d tickets loaded.'));
        return refreshPending(root);
      });
    }).catch(function (error) {
      logError('Offline manifest failed', error);
      setOfflineStatus(root, error.message || label(root, 'offline-init-failed', 'Offline mode is not available in this browser.'));
    });
  }

  function findOfflineTicket(manifest, payload) {
    var token = extractToken(payload);
    return (manifest && manifest.tickets ? manifest.tickets : []).find(function (ticket) {
      return ticket.payload === payload || ticket.legacy_payload === payload || ticket.ticket_token === token;
    }) || null;
  }

  function offlineScan(root, payload) {
    var eventId = parseInt(root.getAttribute('data-event-id'), 10);
    var token = extractToken(payload);
    return dbGet('manifests', eventId).then(function (manifest) {
      if (!manifest || !manifest.tickets || !manifest.tickets.length) {
        setResult(root, 'not_found', label(root, 'offline-not-loaded', 'Offline data is not loaded for this event.'));
        return;
      }
      if (manifest.expires_at && Date.parse(manifest.expires_at.replace(' ', 'T') + 'Z') < Date.now()) {
        setResult(root, 'invalid', label(root, 'offline-expired', 'Offline data has expired. Load offline data again.'));
        return;
      }
      var ticket = findOfflineTicket(manifest, payload);
      if (!ticket) {
        setResult(root, 'not_found', label(root, 'ticket-not-found', 'Ticket not found in offline data.'));
        return;
      }
      if ('cancelled' === ticket.order_status || 'cancelled' === ticket.checkin_status) {
        setResult(root, 'cancelled', label(root, 'ticket-cancelled', 'Ticket cancelled.'), ticket);
        return;
      }
      if ('paid' !== ticket.payment_status) {
        setResult(root, 'payment_pending', label(root, 'payment-pending', 'Payment pending.'), ticket);
        return;
      }
      return dbAllByEvent('checkins', eventId).then(function (items) {
        var duplicate = items.some(function (item) {
          return item.ticket_token === (ticket.ticket_token || token) && 'pending' === item.sync_status;
        }) || 'checked_in' === ticket.checkin_status;
        if (duplicate) {
          setResult(root, 'already_checked_in', label(root, 'already-local', 'Already checked in locally.'), ticket);
          return refreshPending(root);
        }
        return dbPut('checkins', {
          local_id: 'local-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8),
          event_id: eventId,
          payload: payload,
          ticket_token: ticket.ticket_token || token,
          device_id: getDeviceId(),
          scanned_at: new Date().toISOString(),
          sync_status: 'pending'
        }).then(function () {
          ticket.checkin_status = 'checked_in';
          setResult(root, 'checked_in', label(root, 'offline-stored', 'Offline check-in stored.'), ticket);
          return dbPut('manifests', manifest).then(function () {
            return refreshPending(root);
          });
        });
      });
    }).catch(function (error) {
      logError('Offline scan failed', error);
      setResult(root, 'invalid', error.message || label(root, 'offline-unavailable', 'Offline data is not available.'));
    });
  }

  function onlineScan(root, payload) {
    if (!validateConfig(root, 'scan')) {
      return Promise.resolve();
    }
    return ajax(root, root.getAttribute('data-scan-action'), {
      event_id: root.getAttribute('data-event-id'),
      payload: payload,
      device_id: getDeviceId()
    }).then(function (data) {
      setResult(root, data.status, data.message, data);
    }).catch(function (error) {
      logError('Online scan failed', error);
      setResult(root, 'invalid', error.message);
    });
  }

  function handlePayload(root, payload) {
    if (!payload) {
      return;
    }
    if (!window.navigator.onLine) {
      offlineScan(root, payload);
      return;
    }
    onlineScan(root, payload);
  }

  function syncOffline(root) {
    var eventId = parseInt(root.getAttribute('data-event-id'), 10);
    if (!validateConfig(root, 'sync')) {
      return Promise.resolve();
    }
    dbAllByEvent('checkins', eventId).then(function (items) {
      var pending = items.filter(function (item) { return 'pending' === item.sync_status; });
      if (!pending.length) {
        setOfflineStatus(root, label(root, 'no-unsynced', 'No unsynchronized check-ins.'));
        return;
      }
      return ajax(root, root.getAttribute('data-sync-action'), {
        event_id: eventId,
        checkins: JSON.stringify(pending)
      }).then(function (data) {
        var updates = (data.results || []).map(function (item) {
          var local = pending.find(function (candidate) { return candidate.local_id === item.localId; });
          if (!local) {
            return Promise.resolve();
          }
          var status = item.result && item.result.status;
          local.sync_status = ('checked_in' === status || 'already_checked_in' === status) ? 'synced' : 'conflict';
          local.sync_result = item.result || {};
          return dbPut('checkins', local);
        });
        return Promise.all(updates).then(function () {
          setOfflineStatus(root, labelCount(root, 'sync-finished', updates.length, 'Synchronization finished: %d check-ins processed.'));
          return refreshPending(root);
        });
      });
    }).catch(function (error) {
      logError('Offline sync failed', error);
      setOfflineStatus(root, error.message || label(root, 'sync-failed', 'Synchronization failed.'));
    });
  }

  function stopScanner(root, showStatus) {
    var video = root.querySelector('[data-taka-scan-video]');
    var stop = root.querySelector('[data-taka-scan-stop]');
    var reader = root.querySelector('[data-taka-html5-reader]');
    var html5 = root._takaHtml5Scanner;
    var hadActiveCamera = !!root._takaCameraActive || !!root._takaStopScanner || !!html5 || !!(video && video.srcObject);

    if (root._takaStopScanner) {
      root._takaStopScanner();
      root._takaStopScanner = null;
    }

    if (html5 && html5.stop) {
      html5.stop().then(function () {
        if (html5.clear) {
          html5.clear();
        }
      }).catch(function (error) {
        logError('Stopping html5-qrcode failed', error);
      });
      root._takaHtml5Scanner = null;
    }
    root._takaCameraActive = false;

    if (video) {
      if (video.srcObject) {
        video.srcObject.getTracks().forEach(function (track) { track.stop(); });
      }
      video.srcObject = null;
      video.hidden = true;
    }
    if (reader) {
      reader.hidden = true;
      reader.innerHTML = '';
    }
    if (stop) {
      stop.hidden = true;
    }
    if (showStatus) {
      setResult(root, 'info', hadActiveCamera ? label(root, 'scanner-stopped', 'Camera stopped.') : label(root, 'no-active-camera', 'No active camera.'));
    }
  }

  function html5CameraConfigs() {
    return [
      { facingMode: 'environment' },
      { facingMode: { exact: 'environment' } },
      null
    ];
  }

  function startHtml5Scanner(root, Html5Qrcode, reader, index) {
    var configs = html5CameraConfigs();
    var config = configs[index || 0];
    var scanner = root._takaHtml5Scanner;

    if (!scanner) {
      scanner = new Html5Qrcode(reader.id, false);
      root._takaHtml5Scanner = scanner;
    }

    function startWith(cameraConfig) {
      return scanner.start(
        cameraConfig,
        { fps: 10, qrbox: { width: 260, height: 260 } },
        function (decodedText) {
          handlePayload(root, decodedText);
        },
        function () {}
      );
    }

    if (config) {
      return startWith(config).catch(function (error) {
        if (index + 1 < configs.length && canRetryCameraError(error)) {
          logError('html5-qrcode camera constraint failed, trying fallback ' + (index + 2), error);
          return startHtml5Scanner(root, Html5Qrcode, reader, index + 1);
        }
        throw normalizeCameraError(root, error);
      });
    }

    return Html5Qrcode.getCameras().then(function (cameras) {
      if (!cameras || !cameras.length) {
        throw new Error(label(root, 'camera-not-found', 'No camera device found.'));
      }
      return startWith(cameras[0].id);
    });
  }

  function startHtml5ScannerUi(root, Html5Qrcode, reader, stop) {
    if (!Html5Qrcode || !reader) {
      setResult(root, 'invalid', label(root, 'scanner-library-missing', 'QR scanner library could not be loaded. Please reload the page.'));
      return false;
    }
    if (!reader.id) {
      reader.id = 'taka-html5-qrcode-' + (root.getAttribute('data-event-id') || 'event');
    }
    reader.innerHTML = '';
    reader.hidden = false;
    if (stop) {
      stop.hidden = false;
    }
    try {
      startHtml5Scanner(root, Html5Qrcode, reader, 0).then(function () {
        root._takaCameraActive = true;
        setResult(root, 'info', label(root, 'scanner-running', 'Camera scanner is running.'));
      }).catch(function (error) {
        logError('Starting html5-qrcode failed', error);
        setResult(root, 'invalid', error._takaCameraMessage || (error && error.message ? error.message : label(root, 'camera-failed', 'Camera could not be opened.')));
      });
    } catch (error) {
      logError('html5-qrcode initialization failed', error);
      setResult(root, 'invalid', error.message || label(root, 'scanner-unavailable', 'QR scanner is not available in this browser.'));
    }
    return true;
  }

  function testCamera(root) {
    setResult(root, 'info', label(root, 'camera-test-starting', 'Starting camera test...'));
    stopScanner(root, false);
    getCameraStream(root, 0).then(function (stream) {
      return showCameraStream(root, stream, 'Camera test').then(function () {
        setResult(root, 'info', label(root, 'camera-test-running', 'Camera test is running.'));
      });
    }).catch(function (error) {
      logError('Camera test failed', error);
      setResult(root, 'invalid', error._takaCameraMessage || error.message || label(root, 'camera-failed', 'Camera could not be opened.'));
    });
  }

  function startScanner(root) {
    var video = root.querySelector('[data-taka-scan-video]');
    var stop = root.querySelector('[data-taka-scan-stop]');
    var reader = root.querySelector('[data-taka-html5-reader]');
    var Html5Qrcode = window.Html5Qrcode || (window.__Html5QrcodeLibrary__ && window.__Html5QrcodeLibrary__.Html5Qrcode);
    var supportError = cameraSupportError(root);

    setResult(root, 'info', label(root, 'scanner-starting', 'Starting camera scanner...'));
    if (!validateConfig(root, 'scan')) {
      return;
    }
    stopScanner(root, false);

    if (supportError) {
      setResult(root, 'invalid', supportError);
      return;
    }

    if (!('BarcodeDetector' in window)) {
      startHtml5ScannerUi(root, Html5Qrcode, reader, stop);
      return;
    }

    var detector;
    try {
      detector = new window.BarcodeDetector({ formats: ['qr_code'] });
    } catch (error) {
      logError('BarcodeDetector initialization failed', error);
      if (startHtml5ScannerUi(root, Html5Qrcode, reader, stop)) {
        return;
      }
      setResult(root, 'invalid', label(root, 'barcode-unavailable', 'BarcodeDetector is not available. Paste the QR payload below or use a browser with QR scanning support.'));
      return;
    }
    var active = true;
    var last = '';
    getCameraStream(root, 0).then(function (stream) {
      return showCameraStream(root, stream, 'Camera scanner').then(function () {
        root._takaStopScanner = function () {
          active = false;
          stream.getTracks().forEach(function (track) { track.stop(); });
          video.srcObject = null;
          video.hidden = true;
          if (stop) {
            stop.hidden = true;
          }
        };
      });
    }).then(function () {
      root._takaStopScanner = (function (originalStop) {
        return function () {
          active = false;
          if (originalStop) {
            originalStop();
          }
        };
      })(root._takaStopScanner);
      setResult(root, 'info', label(root, 'scanner-running', 'Camera scanner is running.'));
      function tick() {
        if (!active) {
          return;
        }
        detector.detect(video).then(function (codes) {
          if (codes && codes[0] && codes[0].rawValue && codes[0].rawValue !== last) {
            last = codes[0].rawValue;
            handlePayload(root, last);
          }
        }).catch(function (error) {
          logError('BarcodeDetector frame failed', error);
        }).finally(function () {
          window.requestAnimationFrame(tick);
        });
      }
      tick();
    }).catch(function (error) {
      logError('Camera scanner failed', error);
      setResult(root, 'invalid', error._takaCameraMessage || error.message || label(root, 'camera-failed', 'Camera could not be opened.'));
    });
  }

  function scannerRootForTrigger(trigger) {
    var root = closestElement(trigger, '[data-taka-operations-scanner]');
    var target;
    if (root) {
      return root;
    }
    target = trigger.getAttribute('data-taka-scan-target');
    if (target) {
      return document.querySelector(target);
    }
    return document.querySelector('[data-taka-operations-scanner]');
  }

  function handleCheckinAction(root, action) {
    if (!root) {
      logError('Scanner root missing', action);
      return;
    }

    try {
      if ('start' === action) {
        root.scrollIntoView({ block: 'start', behavior: 'smooth' });
        setResult(root, 'info', label(root, 'scan-clicked', 'Scan button clicked. Starting camera scanner...'));
        window.setTimeout(function () { startScanner(root); }, 50);
      } else if ('test' === action) {
        root.scrollIntoView({ block: 'start', behavior: 'smooth' });
        setResult(root, 'info', label(root, 'test-clicked', 'Test camera clicked. Starting camera test...'));
        window.setTimeout(function () { testCamera(root); }, 50);
      } else if ('stop' === action) {
        setResult(root, 'info', label(root, 'stop-clicked', 'Stop camera clicked.'));
        stopScanner(root, true);
      } else if ('load' === action) {
        setOfflineStatus(root, label(root, 'offline-clicked', 'Load offline clicked.'));
        window.setTimeout(function () { loadOfflineData(root); }, 50);
      } else if ('sync' === action) {
        setOfflineStatus(root, label(root, 'sync-clicked', 'Synchronize clicked.'));
        window.setTimeout(function () { syncOffline(root); }, 50);
      }
    } catch (error) {
      logError('Check-in button action failed', error);
      setResult(root, 'invalid', error && error.message ? error.message : String(error));
    }
  }

  function bindButton(root, selector, action) {
    var button = root.querySelector(selector);
    if (!button) {
      logWarn('Check-in button missing: ' + selector);
      return;
    }
    if (button._takaCheckinBound) {
      return;
    }
    button._takaCheckinBound = true;
    button.addEventListener('click', function (event) {
      event._takaCheckinHandled = true;
      event.preventDefault();
      handleCheckinAction(root, action);
    });
  }

  function bindCheckinButtons(root) {
    if (!root) {
      return;
    }
    setResult(root, 'info', label(root, 'js-loaded', 'Check-in JavaScript loaded.'));
    validateConfig(root, 'all');
    bindButton(root, '#taka-scan-qr', 'start');
    bindButton(root, '#taka-test-camera', 'test');
    bindButton(root, '#taka-stop-camera', 'stop');
    bindButton(root, '#taka-load-offline', 'load');
    bindButton(root, '#taka-sync-offline', 'sync');
    refreshPending(root);
  }

  function bindAllCheckinButtons() {
    logInfo('Binding check-in buttons');
    document.querySelectorAll('[data-taka-operations-scanner]').forEach(bindCheckinButtons);
  }

  function onReady(callback) {
    if ('loading' === document.readyState) {
      document.addEventListener('DOMContentLoaded', callback);
      return;
    }
    callback();
  }

  document.addEventListener('click', function (event) {
    var start = closestElement(event.target, '[data-taka-scan-start]');
    var test = closestElement(event.target, '[data-taka-camera-test]');
    var stop = closestElement(event.target, '[data-taka-scan-stop]');
    var load = closestElement(event.target, '[data-taka-offline-load]');
    var sync = closestElement(event.target, '[data-taka-offline-sync]');
    var trigger = start || test || stop || load || sync;
    var root = trigger ? scannerRootForTrigger(trigger) : null;

    if (!trigger || event._takaCheckinHandled) {
      return;
    }
    event.preventDefault();
    if (!root) {
      logError('Scanner root missing', trigger);
      return;
    }

    if (start) {
      handleCheckinAction(root, 'start');
    } else if (test) {
      handleCheckinAction(root, 'test');
    } else if (stop) {
      handleCheckinAction(root, 'stop');
    } else if (load) {
      handleCheckinAction(root, 'load');
    } else if (sync) {
      handleCheckinAction(root, 'sync');
    }
  });

  onReady(bindAllCheckinButtons);
})();

document.addEventListener('click', function (event) {
  var addEventVideo = event.target.closest('[data-taka-event-video-add]');
  var removeEventVideo = event.target.closest('[data-taka-event-video-remove]');

  if (addEventVideo) {
    event.preventDefault();
    var root = addEventVideo.closest('[data-taka-event-videos]');
    var list = root ? root.querySelector('[data-taka-event-video-list]') : null;
    var template = root ? root.querySelector('[data-taka-event-video-template]') : null;
    if (!list || !template) {
      return;
    }
    var index = Date.now().toString();
    var wrapper = document.createElement('div');
    wrapper.innerHTML = template.innerHTML.replace(/__index__/g, index).trim();
    while (wrapper.firstChild) {
      list.appendChild(wrapper.firstChild);
    }
    return;
  }

  if (removeEventVideo) {
    event.preventDefault();
    var item = removeEventVideo.closest('[data-taka-event-video-item]');
    if (item) {
      item.remove();
    }
  }
});

document.addEventListener('click', function (event) {
  var copyButton = event.target.closest('[data-taka-copy-default-translations]');

  if (!copyButton) {
    return;
  }

  event.preventDefault();
  var root = copyButton.closest('[data-taka-content-section-translations]');
  if (!root) {
    return;
  }

  var defaultLang = root.getAttribute('data-default-lang') || 'de';
  var sourceFields = root.querySelectorAll('[data-taka-i18n-lang="' + defaultLang + '"][data-taka-i18n-field]');
  sourceFields.forEach(function (source) {
    var field = source.getAttribute('data-taka-i18n-field');
    var value = source.value || '';

    if (!field || !value.trim()) {
      return;
    }

    var targets = root.querySelectorAll('[data-taka-i18n-field="' + field + '"]');
    targets.forEach(function (target) {
      if (target === source || target.getAttribute('data-taka-i18n-lang') === defaultLang) {
        return;
      }
      if (!(target.value || '').trim()) {
        target.value = value;
      }
    });
  });
});

(function () {
  var i18n = window.takaPlatformAdminI18n || {};

  function format(template, value) {
    return String(template || '%s').replace('%s', value);
  }

  function selectedSourceLanguage(select) {
    return (select && select.value) || 'de';
  }

  function sourceScope(select) {
    return select.closest('[data-taka-source-language-scope]') || select.closest('form') || document;
  }

  function sourceAwareRoots(scope) {
    if (scope.matches && scope.matches('[data-taka-source-aware]')) {
      return [scope];
    }
    return Array.prototype.slice.call(scope.querySelectorAll('[data-taka-source-aware]'));
  }

  function updateSourceHelp(scope, sourceLang) {
    var help = format(i18n.sourceLanguageHelp, String(sourceLang).toUpperCase());
    scope.querySelectorAll('[data-taka-source-help]').forEach(function (node) {
      node.textContent = help;
    });
  }

  function updateTab(tab, sourceLang) {
    var lang = tab.getAttribute('data-taka-i18n-lang') || '';
    var label = tab.getAttribute('data-language-label') || lang.toUpperCase();
    var isSource = lang === sourceLang;
    tab.textContent = isSource ? format(i18n.sourceTabLabel, label) : label;
    tab.classList.toggle('is-source-language', isSource);
    if (isSource && tab.getAttribute('for')) {
      var radio = document.getElementById(tab.getAttribute('for'));
      if (radio) {
        radio.checked = true;
      }
    }
  }

  function updatePanel(panel, sourceLang, mode) {
    var lang = panel.getAttribute('data-taka-i18n-lang') || '';
    var isSource = lang === sourceLang;
    var help = panel.querySelector('[data-taka-source-panel-help]');
    panel.classList.toggle('is-source-language', isSource);
    if (help) {
      help.textContent = isSource && mode === 'editable'
        ? (i18n.editableSourcePanelHelp || 'This tab contains the original text for this item.')
        : (isSource ? (i18n.sourcePanelHelp || '') : (i18n.translationPanelHelp || ''));
    }
    updateFieldLabels(panel, isSource);
  }

  function updateFieldLabels(root, isSource) {
    root.querySelectorAll('[data-taka-language-field-label]').forEach(function (label) {
      label.textContent = isSource
        ? (label.getAttribute('data-source-label') || format(i18n.sourceTextLabel, label.textContent))
        : (label.getAttribute('data-translation-label') || format(i18n.translationTextLabel, label.textContent));
    });
  }

  function setInlineSourceNote(row, isSource, mode) {
    var note = row.querySelector('[data-taka-source-inline-note]');
    if (!isSource) {
      if (note) {
        note.remove();
      }
      return;
    }

    if (!note) {
      note = document.createElement('span');
      note.className = 'description';
      note.setAttribute('data-taka-source-inline-note', '1');
      row.appendChild(note);
    }

    note.textContent = mode === 'disabled-source'
      ? ' ' + (i18n.editSourceColumn || '')
      : ' ' + (i18n.thisIsSourceLanguage || '');
  }

  function updateLanguageRows(root, sourceLang, mode) {
    root.querySelectorAll('[data-taka-language-field-row]').forEach(function (row) {
      var lang = row.getAttribute('data-taka-i18n-lang') || '';
      var isSource = lang === sourceLang;
      row.classList.toggle('is-source-language', isSource);
      updateFieldLabels(row, isSource);
      setInlineSourceNote(row, isSource, mode);
    });
  }

  function hiddenForField(field) {
    var parent = field.parentNode;
    if (!parent || !field.name) {
      return null;
    }
    return parent.querySelector('input[type="hidden"][data-taka-source-hidden][name="' + field.name.replace(/"/g, '\\"') + '"]');
  }

  function ensureHiddenForDisabledSource(field) {
    var hidden = hiddenForField(field);
    if (hidden) {
      return;
    }
    hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = field.name;
    hidden.value = field.value || '';
    hidden.setAttribute('data-taka-source-hidden', '1');
    field.parentNode.insertBefore(hidden, field);
  }

  function removeHiddenForEnabledTranslation(field) {
    var hidden = hiddenForField(field);
    if (hidden) {
      hidden.remove();
    }
  }

  function updateDisabledSourceFields(root, sourceLang) {
    root.querySelectorAll('[data-taka-source-disable-when-source]').forEach(function (field) {
      var lang = field.getAttribute('data-taka-i18n-lang') || '';
      var isSource = lang === sourceLang;
      if (isSource) {
        ensureHiddenForDisabledSource(field);
        field.disabled = true;
      } else {
        field.disabled = false;
        removeHiddenForEnabledTranslation(field);
      }
    });
  }

  function updateRoot(root, sourceLang) {
    var mode = root.getAttribute('data-source-mode') || 'editable';
    root.setAttribute('data-source-language', sourceLang);
    root.setAttribute('data-default-lang', sourceLang);
    root.querySelectorAll('[data-taka-language-tab]').forEach(function (tab) {
      updateTab(tab, sourceLang);
    });
    root.querySelectorAll('[data-taka-language-panel]').forEach(function (panel) {
      updatePanel(panel, sourceLang, mode);
    });
    updateLanguageRows(root, sourceLang, mode);
    updateDisabledSourceFields(root, sourceLang);
  }

  function syncSourceLanguageSelect(select) {
    var sourceLang = selectedSourceLanguage(select);
    var scope = sourceScope(select);
    updateSourceHelp(scope, sourceLang);
    sourceAwareRoots(scope).forEach(function (root) {
      updateRoot(root, sourceLang);
    });
  }

  document.addEventListener('change', function (event) {
    var select = event.target.closest('[data-taka-source-language-select]');
    if (select) {
      syncSourceLanguageSelect(select);
    }
  });

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-taka-source-language-select]').forEach(syncSourceLanguageSelect);
  });
})();
