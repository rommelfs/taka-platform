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
