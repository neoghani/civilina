/* Civilina day/night mode for the public site.
   Loaded in the document head (blocking, tiny) so the right mode is applied before the
   first paint. Choice stored in the civilina_theme cookie, shared with the
   member area and back-office (PHP); without a choice, the device decides. */
(function () {
  var root = document.documentElement;
  var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  function saved() {
    var match = document.cookie.match(/(?:^|;\s*)civilina_theme=(light|dark)/);
    return match ? match[1] : '';
  }
  function device() {
    return media && media.matches ? 'dark' : 'light';
  }
  function apply(theme) {
    root.setAttribute('data-theme', theme);
    var toggles = document.querySelectorAll('[data-theme-toggle]');
    for (var i = 0; i < toggles.length; i++) {
      toggles[i].setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
      toggles[i].setAttribute('title', theme === 'dark' ? 'Passer en mode jour' : 'Passer en mode nuit');
    }
  }

  apply(saved() || device());

  if (media && media.addEventListener) {
    media.addEventListener('change', function () {
      if (!saved()) apply(device());
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    apply(root.getAttribute('data-theme') || 'light');
  });

  document.addEventListener('click', function (event) {
    var toggle = event.target.closest ? event.target.closest('[data-theme-toggle]') : null;
    if (!toggle) return;
    var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.cookie = 'civilina_theme=' + next + '; path=/; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    apply(next);
  });
})();
