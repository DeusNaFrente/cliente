(function () {
  'use strict';
  function ping() {
    if (document.visibilityState !== 'visible') return;
    fetch('ping.php', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { if (r.status === 401) window.location.href = 'login.php'; })
      .catch(function () {});
  }
  setInterval(ping, 60000);
  document.addEventListener('visibilitychange', ping);
})();
