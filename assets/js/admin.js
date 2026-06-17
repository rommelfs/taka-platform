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
