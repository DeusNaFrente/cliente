(function () {
  'use strict';
  var $ = function (s) { return document.querySelector(s); };

  function api(action, body) {
    return fetch('coletor_api.php?action=' + action, {
      method: body ? 'POST' : 'GET',
      body: body || undefined,
      headers: { 'X-Requested-With': 'fetch' },
      credentials: 'same-origin',
      cache: 'no-store'
    }).then(function (r) {
      return r.json().catch(function () { throw new Error(t('Resposta inesperada do servidor.')); }).then(function (data) {
        if (r.status === 401) { window.location.href = 'login.php'; }
        if (!data.ok) throw new Error(data.error || t('Não foi possível concluir.'));
        return data;
      });
    });
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function digits(s) { return String(s || '').replace(/\D/g, ''); }
  function fmtCpf(d) {
    d = d.slice(0, 11);
    if (d.length > 9) return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6, 9) + '-' + d.slice(9);
    if (d.length > 6) return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6);
    if (d.length > 3) return d.slice(0, 3) + '.' + d.slice(3);
    return d;
  }
  function fmtCnpj(d) {
    d = d.slice(0, 14);
    if (d.length > 12) return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5, 8) + '/' + d.slice(8, 12) + '-' + d.slice(12);
    if (d.length > 8) return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5, 8) + '/' + d.slice(8);
    if (d.length > 5) return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5);
    if (d.length > 2) return d.slice(0, 2) + '.' + d.slice(2);
    return d;
  }
  function fmtAny(d) { return d.length === 14 ? fmtCnpj(d) : fmtCpf(d); }

  var LOWER = ['de', 'da', 'do', 'das', 'dos', 'e', 'di', 'du'];
  function titleCase(s) {
    return s.trim().replace(/\s+/g, ' ').toLocaleLowerCase('pt-BR').split(' ').map(function (w, i) {
      return i > 0 && LOWER.indexOf(w) !== -1 ? w : w.charAt(0).toLocaleUpperCase('pt-BR') + w.slice(1);
    }).join(' ');
  }

  /* ---------- Novo pedido ---------- */
  var form = $('#novo'), seg = $('#tipoSeg'), nome = $('#nomeInput'), doc = $('#docInput'), email = $('#emailInput'), clientesList = $('#clientesList');
  var nomeLabel = $('#nomeLabel'), nomeHint = $('#nomeHint'), docLabel = $('#docLabel'), docHint = $('#docHint');
  var formMsg = $('#formMsg'), btnGerar = $('#btnGerar');
  var tipo = 'pj';

  function normalizeNome(v) {
    return tipo === 'pf' ? titleCase(v) : v.trim().replace(/\s+/g, ' ').toLocaleUpperCase('pt-BR');
  }

  function setTipo(tp) {
    tipo = tp;
    seg.setAttribute('data-value', tp);
    seg.querySelectorAll('.seg-btn').forEach(function (b) {
      var on = b.getAttribute('data-value') === tp;
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-checked', on ? 'true' : 'false');
    });
    nomeLabel.textContent = tp === 'pf' ? t('Nome completo') : t('Razão social');
    nome.placeholder = tp === 'pf' ? t('Ex.: Maria da Silva') : t('Ex.: ACME COMÉRCIO LTDA');
    nomeHint.textContent = tp === 'pf' ? t('Maiúsculas ajustadas automaticamente.') : t('Convertida para maiúsculas automaticamente.');
    docLabel.textContent = tp === 'pf' ? 'CPF' : 'CNPJ';
    doc.placeholder = tp === 'pf' ? '000.000.000-00' : '00.000.000/0000-00';
    if (nome.value.trim()) nome.value = normalizeNome(nome.value);
    applyDoc();
  }

  function validateDoc(strict) {
    var d = digits(doc.value), need = tipo === 'pf' ? 11 : 14, label = tipo === 'pf' ? 'CPF' : 'CNPJ';
    var state = '', msg = t('Digite os {n} números do {doc}.', { n: need, doc: label });
    if (d.length === need) {
      var ok = tipo === 'pf' ? validarCPF(d) : validarCNPJ(d);
      state = ok ? 'ok' : 'bad';
      msg = ok ? t('{doc} válido.', { doc: label }) : t('{doc} inválido. Confira os números.', { doc: label });
    } else if (strict) {
      state = 'bad';
    }
    doc.closest('.field').setAttribute('data-state', state);
    docHint.textContent = msg;
    return state === 'ok';
  }

  function applyDoc() {
    var d = digits(doc.value).slice(0, tipo === 'pf' ? 11 : 14);
    doc.value = tipo === 'pf' ? fmtCpf(d) : fmtCnpj(d);
    validateDoc(false);
  }

  seg.addEventListener('click', function (e) {
    var b = e.target.closest('.seg-btn');
    if (b) setTipo(b.getAttribute('data-value'));
  });
  doc.addEventListener('input', function () {
    if (tipo === 'pf' && digits(doc.value).length > 11) setTipo('pj');
    else applyDoc();
  });
  nome.addEventListener('blur', function () {
    if (nome.value.trim()) nome.value = normalizeNome(nome.value);
  });
  nome.addEventListener('input', function () {
    var match = clientes.filter(function (i) { return i.nome === nome.value; })[0];
    if (!match) return;
    if (match.type_pessoa) {
      setTipo(match.type_pessoa);
      doc.value = match.type_pessoa === 'pf' ? fmtCpf(match.documento) : fmtCnpj(match.documento);
      validateDoc(false);
    }
    email.value = match.email || '';
  });
  [nome, doc, email].forEach(function (el) {
    el.addEventListener('input', function () { formMsg.hidden = true; });
  });

  function showFormError(msg) {
    formMsg.textContent = msg;
    formMsg.hidden = false;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    formMsg.hidden = true;
    nome.value = normalizeNome(nome.value);
    if (nome.value.length < 3) {
      showFormError(tipo === 'pf' ? t('Informe o nome completo.') : t('Informe a razão social.'));
      nome.scrollIntoView({ behavior: 'smooth', block: 'center' });
      nome.focus();
      return;
    }
    if (!validateDoc(true)) {
      showFormError(docHint.textContent);
      doc.scrollIntoView({ behavior: 'smooth', block: 'center' });
      doc.focus();
      return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
      showFormError(t('Informe um e-mail válido de quem vai preencher o cadastro.'));
      email.scrollIntoView({ behavior: 'smooth', block: 'center' });
      email.focus();
      return;
    }
    var fd = new FormData();
    fd.append('type_pessoa', tipo);
    fd.append('nome', nome.value);
    fd.append('documento', digits(doc.value));
    fd.append('email', email.value.trim());
    btnGerar.disabled = true;
    btnGerar.textContent = t('Gerando…');
    api('create', fd).then(function (data) {
      showShare(data);
      nome.value = '';
      doc.value = '';
      email.value = '';
      setTipo(tipo);
      return loadList();
    }).catch(function (err) {
      showFormError(err.message);
    }).then(function () {
      btnGerar.disabled = false;
      btnGerar.textContent = t('Gerar link');
    });
  });

  /* ---------- Compartilhar ---------- */
  var share = $('#sharePanel');
  var current = { link: '', message: '' };

  function showShare(data) {
    current = { link: data.link, message: data.message };
    $('#shareTitle').textContent = t('Link para {nome}', { nome: data.item.nome });
    $('#shareText').textContent = data.message;
    $('#shareWhats').href = 'https://wa.me/?text=' + encodeURIComponent(data.message);
    share.hidden = false;
    share.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function copyText(text, btn) {
    var done = function () {
      var old = btn.textContent;
      btn.textContent = '✓ ' + t('Copiado');
      btn.classList.add('is-done');
      setTimeout(function () { btn.textContent = old; btn.classList.toggle('is-done', false); }, 1800);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text); done(); });
    } else {
      fallbackCopy(text);
      done();
    }
  }
  function fallbackCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
  }

  $('#copyMsg').addEventListener('click', function (e) { copyText(current.message, e.currentTarget); });
  $('#copyLink').addEventListener('click', function (e) { copyText(current.link, e.currentTarget); });
  $('#shareClose').addEventListener('click', function () { share.hidden = true; });

  /* ---------- Lista ---------- */
  var items = [], openId = null;
  var list = $('#lista'), fQ = $('#fQ'), fDe = $('#fDe'), fAte = $('#fAte'), fStatus = $('#fStatus'), count = $('#listaCount');
  var STATUS = { aguardando: t('Aguardando'), aberto: t('Aberto'), recebido: t('Recebido') };
  var EVENTO = {
    criado: t('Pedido criado'),
    reenviado: t('Novo link gerado'),
    email_enviado: t('Cliente pediu o acesso por e-mail'),
    aberto: t('Cliente abriu o pedido'),
    enviado: t('Cadastro recebido'),
    reenviado_emissor: t('Cliente reenviou o cadastro'),
    editado: t('Cadastro editado'),
    excluido_admin: t('Cadastro excluído pelo admin'),
    excluido_emissor: t('Cliente apagou o cadastro'),
    redirecionado_login: t('Já tinha conta, foi para o login')
  };
  function fmtDate(sec) {
    return new Date(sec * 1000).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  }
  function initials(n) {
    return n.split(' ').filter(Boolean).slice(0, 2).map(function (w) { return w.charAt(0); }).join('').toUpperCase();
  }

  var clientes = [];

  function loadClientes() {
    return api('clientes').then(function (data) {
      clientes = data.items;
      clientesList.innerHTML = clientes.map(function (i) {
        return '<option value="' + esc(i.nome) + '">';
      }).join('');
    }).catch(function () {});
  }

  function loadList() {
    return api('list').then(function (data) {
      items = data.items;
      render();
    }).catch(function (err) {
      list.innerHTML = '<div class="empty-state"><p>' + esc(err.message) + '</p></div>';
    });
  }

  function render() {
    var q = fQ.value.trim().toLocaleLowerCase('pt-BR'), qd = digits(fQ.value);
    var de = fDe.value ? new Date(fDe.value).getTime() / 1000 : null;
    var ate = fAte.value ? new Date(fAte.value).getTime() / 1000 + 59 : null;
    var st = fStatus.value;
    var rows = items.filter(function (i) {
      var matchQ = !q || i.nome.toLocaleLowerCase('pt-BR').indexOf(q) !== -1 || (qd.length >= 3 && i.documento.indexOf(qd) !== -1);
      return matchQ && (de === null || i.created >= de) && (ate === null || i.created <= ate) && (!st || i.status === st);
    });
    count.textContent = rows.length === items.length
      ? (items.length === 1 ? t('1 pedido') : t('{n} pedidos', { n: items.length }))
      : t('{a} de {b}', { a: rows.length, b: items.length });
    if (!items.length) {
      list.innerHTML = '<div class="empty-state"><p><strong>' + esc(t('Nenhum pedido ainda.')) + '</strong><br>' + esc(t('Crie o primeiro no formulário acima.')) + '</p></div>';
      return;
    }
    if (!rows.length) {
      list.innerHTML = '<div class="empty-state"><p>' + esc(t('Nenhum pedido com esses filtros.')) + '</p></div>';
      return;
    }
    list.innerHTML = rows.map(rowHtml).join('');
  }

  function rowHtml(i) {
    var open = String(i.id) === openId;
    return '<article class="pedido' + (open ? ' is-open' : '') + '" data-id="' + i.id + '">'
      + '<div class="pedido-row">'
      + '<div class="pedido-head" role="button" tabindex="0" aria-expanded="' + open + '">'
      + '<span class="record-avatar">' + esc(initials(i.nome)) + '</span>'
      + '<span class="pedido-main"><strong>' + esc(i.nome) + '</strong><span class="record-meta">' + i.type_pessoa.toUpperCase() + ' · ' + esc(fmtAny(i.documento)) + '</span></span>'
      + '<span class="status-badge status-' + i.status + '">' + esc(STATUS[i.status]) + '</span>'
      + '<span class="pedido-date">' + fmtDate(i.created) + '</span>'
      + '</div>'
      + '<button type="button" class="ghost icon-btn pedido-quick-del" data-excluir="' + i.id + '" title="' + esc(t('Excluir pedido')) + '" aria-label="' + esc(t('Excluir pedido')) + '">✕</button>'
      + '</div>'
      + (open ? detailHtml(i) : '') + '</article>';
  }

  function detailHtml(i) {
    var ev = (i.eventos || []).slice().reverse().map(function (e) {
      return '<li><span>' + esc(EVENTO[e.evento] || e.evento) + '</span><time>' + fmtDate(e.at) + '</time></li>';
    }).join('');
    var recebido = i.status === 'recebido';
    return '<div class="pedido-body"><div class="detail-grid">'
      + '<div class="detail-item"><div class="detail-label">' + esc(t('Tipo')) + '</div><div class="detail-value">' + esc(i.type_pessoa === 'pf' ? t('Pessoa Física') : t('Pessoa Jurídica')) + '</div></div>'
      + '<div class="detail-item"><div class="detail-label">' + (i.type_pessoa === 'pf' ? 'CPF' : 'CNPJ') + '</div><div class="detail-value mono">' + esc(fmtAny(i.documento)) + '</div></div>'
      + '<div class="detail-item"><div class="detail-label">' + esc(t('E-mail')) + '</div><div class="detail-value">' + esc(i.email || '—') + '</div></div>'
      + '<div class="detail-item"><div class="detail-label">' + esc(t('Emissor')) + '</div><div class="detail-value">' + (i.emissor ? esc(i.emissor) : '<span class="muted">' + esc(t('ainda não abriu')) + '</span>') + '</div></div>'
      + '<div class="detail-item"><div class="detail-label">' + esc(t('Última atividade')) + '</div><div class="detail-value">' + fmtDate(i.updated) + '</div></div>'
      + '</div><h3 class="pedido-sub">' + esc(t('Histórico')) + '</h3><ol class="timeline">' + ev + '</ol>'
      + '<div class="pedido-actions">'
      + (recebido ? '<a class="button primary" href="cadastro.php?coleta=' + i.id + '">' + esc(t('Ver e editar cadastro')) + '</a>' : '')
      + '<button type="button"' + (recebido ? '' : ' class="primary"') + ' data-reenviar="' + i.id + '">' + esc(t('Reenviar com novo link')) + '</button>'
      + '<span class="muted">' + esc(t('O link anterior deixa de funcionar.')) + '</span></div></div>';
  }

  list.addEventListener('click', function (e) {
    var re = e.target.closest('[data-reenviar]');
    if (re) {
      if (!confirm(t('Gerar um novo link para este pedido? O link anterior deixa de funcionar.'))) return;
      re.disabled = true;
      var fd = new FormData();
      fd.append('id', re.getAttribute('data-reenviar'));
      api('reenviar', fd).then(function (data) {
        showShare(data);
        return loadList();
      }).catch(function (err) {
        alert(err.message);
        re.disabled = false;
      });
      return;
    }
    var ex = e.target.closest('[data-excluir]');
    if (ex) {
      if (!confirm(t('Excluir este pedido? Isso também apaga o cadastro recebido, se houver, e os arquivos enviados. Não dá para desfazer.'))) return;
      ex.disabled = true;
      var fdx = new FormData();
      fdx.append('id', ex.getAttribute('data-excluir'));
      api('excluir', fdx).then(function () {
        openId = null;
        return loadList();
      }).catch(function (err) {
        alert(err.message);
        ex.disabled = false;
      });
      return;
    }
    var head = e.target.closest('.pedido-head');
    if (head) {
      var id = head.closest('.pedido').getAttribute('data-id');
      openId = openId === id ? null : id;
      render();
    }
  });

  list.addEventListener('keydown', function (e) {
    if ((e.key === 'Enter' || e.key === ' ') && e.target.classList.contains('pedido-head')) {
      e.preventDefault();
      e.target.click();
    }
  });

  [fQ, fDe, fAte, fStatus].forEach(function (el) { el.addEventListener('input', render); });
  $('#fLimpar').addEventListener('click', function () {
    fQ.value = ''; fDe.value = ''; fAte.value = ''; fStatus.value = '';
    render();
  });

  setTipo('pj');
  loadList();
  loadClientes();
})();
