(function () {
  'use strict';
  var cepInput = document.getElementById('cepInput');
  if (!cepInput) return;

  function digits(s) { return String(s || '').replace(/\D/g, ''); }

  function lookup() {
    var d = digits(cepInput.value);
    if (d.length !== 8) return;
    fetch('https://viacep.com.br/ws/' + d + '/json/')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || data.erro) return;
        var map = {
          logradouro: data.logradouro,
          bairro: data.bairro,
          cidade: data.localidade,
          estado: data.uf,
          complemento: data.complemento
        };
        Object.keys(map).forEach(function (campo) {
          var el = document.querySelector('[name="endereco[' + campo + ']"]');
          if (!el || el.disabled) return;
          if (campo === 'complemento' && el.value) return;
          if (map[campo]) el.value = map[campo];
        });
        var numero = document.querySelector('[name="endereco[numero]"]');
        if (numero && !numero.disabled && !numero.value) numero.focus();
      })
      .catch(function () {});
  }

  cepInput.addEventListener('blur', lookup);
  cepInput.addEventListener('input', function () {
    if (digits(cepInput.value).length === 8) lookup();
  });
})();
