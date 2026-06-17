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
  var frame = window.wp.media({
    title: pick.getAttribute('data-title') || 'Select media',
    multiple: multiple,
    library: { type: 'image' }
  });

  frame.on('select', function () {
    var ids = [];
    var html = '';
    frame.state().get('selection').each(function (item) {
      var attachment = item.toJSON();
      var url = (attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url) || '';
      if (attachment.id) {
        ids.push(attachment.id);
      }
      if (url) {
        html += '<img src="' + url + '" style="max-width:110px;height:auto;margin:4px;vertical-align:middle;" alt="">';
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
