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

  function recoverProtectedPostboxes() {
    protectedPostboxes().forEach(openProtectedPostbox);
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
      sectionStorageKeys.forEach(removeStoredState);
      sections.forEach(applyDefaultState);
      recoverProtectedPostboxes();
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

    recoverProtectedPostboxes();
    observeProtectedPostboxes();
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
