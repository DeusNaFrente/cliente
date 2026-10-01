(function () {
  'use strict';
  document.querySelectorAll('[data-apagar]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!confirm(t('Apagar este cadastro enviado? Esta ação não pode ser desfeita.'))) return;
      btn.disabled = true;
      var fd = new FormData();
      fd.append('coleta_id', btn.getAttribute('data-apagar'));
      fd.append('delete', '1');
      fetch('cadastro_api.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.ok) throw new Error(d.error || t('Não foi possível concluir.'));
          window.location.reload();
        })
        .catch(function (err) {
          alert(err.message);
          btn.disabled = false;
        });
    });
  });
})();
