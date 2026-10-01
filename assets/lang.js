(function () {
  document.querySelectorAll('.lang-selector').forEach(function (box) {
    var btn = box.querySelector('.lang-button');
    var menu = box.querySelector('.lang-dropdown');
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = menu.classList.toggle('active');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
      if (!box.contains(e.target)) {
        menu.classList.toggle('active', false);
        btn.setAttribute('aria-expanded', 'false');
      }
    });
  });
})();
