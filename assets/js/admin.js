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
        ? (i18n.editableSourcePanelHelp || 'This tab is the source text for this item.')
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
