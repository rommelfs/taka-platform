function takaEscapeMediaPreviewHtml(value) {
  return String(value || '').replace(/[&<>"']/g, function (char) {
    return {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[char];
  });
}

document.addEventListener('click', function (event) {
  var pick = event.target.closest('[data-taka-media-pick]');
  var remove = event.target.closest('[data-taka-media-remove]');

  if (remove) {
    event.preventDefault();
    var removeTarget = document.getElementById(remove.getAttribute('data-target'));
    var removePreview = document.getElementById(remove.getAttribute('data-preview'));
    if (removeTarget) {
      removeTarget.value = '';
    }
    if (removePreview) {
      removePreview.innerHTML = '';
    }
    return;
  }

  if (!pick || !window.wp || !window.wp.media) {
    return;
  }

  event.preventDefault();
  var target = document.getElementById(pick.getAttribute('data-target'));
  var preview = document.getElementById(pick.getAttribute('data-preview'));
  var multiple = pick.getAttribute('data-multiple') === '1';
  var mediaType = pick.getAttribute('data-media-type') || 'image';
  var frameOptions = {
    title: pick.getAttribute('data-title') || 'Select media',
    multiple: multiple
  };
  if (mediaType) {
    frameOptions.library = { type: mediaType };
  }
  var frame = window.wp.media(frameOptions);

  frame.on('select', function () {
    var ids = [];
    var html = '';
    frame.state().get('selection').each(function (item) {
      var attachment = item.toJSON();
      var url = '';
      if (attachment.id) {
        ids.push(attachment.id);
      }
      if (mediaType === 'video') {
        url = attachment.url || '';
        if (url) {
          html += '<span class="taka-admin-media-preview taka-admin-media-preview--video"><a href="' + takaEscapeMediaPreviewHtml(url) + '" target="_blank" rel="noopener noreferrer">' + takaEscapeMediaPreviewHtml(attachment.filename || attachment.title || url) + '</a></span>';
        }
      } else {
        url = (attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url) || '';
      }
      if (url && mediaType !== 'video') {
        html += '<img src="' + takaEscapeMediaPreviewHtml(url) + '" style="max-width:110px;height:auto;margin:4px;vertical-align:middle;" alt="">';
      }
    });
    if (target) {
      target.value = ids.join(',');
    }
    if (preview) {
      preview.innerHTML = html;
    }
  });

  frame.open();
});
