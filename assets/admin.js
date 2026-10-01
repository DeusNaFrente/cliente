(function () {
  'use strict';
  var content = document.getElementById('adminContent');
  var modal = document.getElementById('modal');
  var modalBody = document.getElementById('modalBody');
  var current = 'cadastros';

  /* ---------- Utilidades ---------- */
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function digits(s) { return String(s || '').replace(/\D/g, ''); }
  function fmtDoc(d) {
    d = digits(d);
    if (d.length === 14) return d.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
    if (d.length === 11) return d.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
    return d;
  }
  var DATE_FIELDS = { dataAbertura: true, dataNascimento: true, dataEmissao: true };
  function fmtDateStr(v) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v);
    return m ? m[3] + '/' + m[2] + '/' + m[1] : v;
  }
  function fmtDate(sec) {
    return sec ? new Date(sec * 1000).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';
  }
  function ago(sec) {
    if (!sec) return 'nunca';
    var s = Math.max(0, Math.round(Date.now() / 1000 - sec));
    if (s < 90) return 'agora';
    if (s < 3600) return 'há ' + Math.round(s / 60) + ' min';
    if (s < 86400) return 'há ' + Math.round(s / 3600) + ' h';
    return 'há ' + Math.round(s / 86400) + ' d';
  }
  function initials(n) {
    return String(n || '').split(' ').filter(Boolean).slice(0, 2).map(function (w) { return w.charAt(0); }).join('').toUpperCase();
  }
  function browserOf(ua) {
    ua = ua || '';
    var b = /Edg\//.test(ua) ? 'Edge' : /OPR\//.test(ua) ? 'Opera' : /Chrome\//.test(ua) ? 'Chrome' : /Firefox\//.test(ua) ? 'Firefox'
      : /Safari\//.test(ua) ? 'Safari' : /curl/i.test(ua) ? 'curl' : 'Outro';
    var o = /Android/.test(ua) ? 'Android' : /iPhone|iPad/.test(ua) ? 'iOS' : /Windows/.test(ua) ? 'Windows' : /Mac OS/.test(ua) ? 'macOS' : /Linux/.test(ua) ? 'Linux' : '';
    return o ? b + ' · ' + o : b;
  }
  function api(action, params, body) {
    var url = 'admin/api.php?action=' + action + (params ? '&' + new URLSearchParams(params).toString() : '');
    return fetch(url, { method: body ? 'POST' : 'GET', body: body || undefined, headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) {
        return r.json().catch(function () { throw new Error('Resposta inesperada do servidor.'); }).then(function (d) {
          if (r.status === 401) window.location.href = 'login.php';
          if (!d.ok) throw new Error(d.error || 'Não foi possível concluir.');
          return d;
        });
      });
  }
  function fd(obj) {
    var f = new FormData();
    Object.keys(obj).forEach(function (k) { f.append(k, obj[k]); });
    return f;
  }
  function fail(err) {
    content.innerHTML = '<div class="notice notice-warn">' + esc(err.message) + '</div>';
  }
  function copyText(text, btn) {
    var done = function () {
      var old = btn.textContent;
      btn.textContent = '✓ Copiado';
      setTimeout(function () { btn.textContent = old; }, 1600);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done, done);
    } else {
      var t = document.createElement('textarea');
      t.value = text;
      document.body.appendChild(t);
      t.select();
      document.execCommand('copy');
      document.body.removeChild(t);
      done();
    }
  }
  function openModal(html) { modalBody.innerHTML = html; modal.hidden = false; }
  function closeModal() { modal.hidden = true; modalBody.innerHTML = ''; }
  function modalError(err) { var el = document.getElementById('mErro'); el.textContent = err.message; el.hidden = false; }
  modal.addEventListener('click', function (e) { if (e.target === modal || e.target.closest('[data-close]')) closeModal(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) closeModal(); });

  var PROFILE = { coletor: 'Coletor', emissor: 'Emissor' };
  var EVENT = { login: 'Login', login_link: 'Login pelo e-mail', logout: 'Saiu', login_failed: 'Falha no login' };
  var EVENTO = {
    criado: 'Pedido criado', reenviado: 'Novo link gerado', email_enviado: 'Cliente pediu o acesso por e-mail',
    aberto: 'Cliente abriu o pedido', enviado: 'Cadastro recebido', reenviado_emissor: 'Cliente reenviou o cadastro',
    editado: 'Cadastro editado pelo coletor', excluido_admin: 'Cadastro excluído pelo admin',
    excluido_emissor: 'Cliente apagou o cadastro', redirecionado_login: 'Já tinha conta, foi para o login'
  };
  var LABELS = {
    empresa: ['Empresa', { cnpj: 'CNPJ', razaoSocial: 'Razão social', nomeFantasia: 'Nome fantasia', dataAbertura: 'Data de abertura', naturezaJuridica: 'Natureza jurídica', faturamentoAnual: 'Faturamento anual (centavos)', cnae: 'CNAE', inscricaoEstadual: 'Inscrição estadual' }],
    contatoEmpresa: ['Contato da empresa', { email: 'E-mail', ddi: 'DDI', telefone: 'Telefone' }],
    endereco: ['Endereço', { cep: 'CEP', logradouro: 'Logradouro', numero: 'Número', bairro: 'Bairro', cidade: 'Cidade', estado: 'Estado', pais: 'País', complemento: 'Complemento' }],
    responsavel: ['Responsável', { cpf: 'CPF', nomeCompleto: 'Nome completo', nomeMae: 'Nome da mãe', dataNascimento: 'Data de nascimento', sexo: 'Sexo', estadoCivil: 'Estado civil', email: 'E-mail', ddi: 'DDI', telefone: 'Telefone', tipoDocumento: 'Tipo de documento', numeroDocumento: 'Número do documento', orgaoEmissor: 'Órgão emissor', ufEmissora: 'UF emissora', dataEmissao: 'Data de emissão', cargo: 'Cargo', rendaMensal: 'Renda mensal (centavos)', pep: 'Pessoa exposta politicamente' }]
  };
  var FILES = {
    evidenciasEmpresa: { comprovanteEndereco: 'Comprovante de endereço da empresa', documentoPrincipal: 'Documento da empresa', contratoSocial: 'Contrato social' },
    evidenciasResponsavel: { selfie: 'Selfie', docFrente: 'Documento (frente)', docVerso: 'Documento (verso)', comprovanteResidencia: 'Comprovante de residência', procuracao: 'Procuração' }
  };

  /* ---------- Cadastros ---------- */
  var cad = { items: [], open: null, cache: {} };

  function renderCadastros() {
    content.innerHTML = '<div class="admin-head"><h1>Cadastros</h1><span class="muted" id="cadCount"></span></div>'
      + '<div class="filters filters-3"><label class="f-q">Buscar<input id="cQ" type="search" placeholder="Nome, documento, cidade, coletor ou emissor"></label>'
      + '<label>Tipo<select id="cTipo"><option value="">Todos</option><option value="pj">PJ</option><option value="pf">PF</option></select></label>'
      + '<button type="button" class="ghost" id="cLimpar">Limpar</button></div>'
      + '<div id="cadList"><div class="empty-state"><p>Carregando…</p></div></div>';
    document.getElementById('cQ').addEventListener('input', drawCadastros);
    document.getElementById('cTipo').addEventListener('input', drawCadastros);
    document.getElementById('cLimpar').addEventListener('click', function () {
      document.getElementById('cQ').value = '';
      document.getElementById('cTipo').value = '';
      drawCadastros();
    });
    document.getElementById('cadList').addEventListener('click', onCadClick);
    api('cadastros').then(function (d) { cad.items = d.items; drawCadastros(); }).catch(fail);
  }

  function drawCadastros() {
    var list = document.getElementById('cadList');
    if (!list) return;
    var q = document.getElementById('cQ').value.trim().toLocaleLowerCase('pt-BR'), qd = digits(q);
    var tipo = document.getElementById('cTipo').value;
    var rows = cad.items.filter(function (i) {
      var hay = [i.nome, i.coletor, i.emissor, i.local].join(' ').toLocaleLowerCase('pt-BR');
      return (!q || hay.indexOf(q) !== -1 || (qd.length >= 3 && i.documento.indexOf(qd) !== -1)) && (!tipo || i.tipo === tipo);
    });
    document.getElementById('cadCount').textContent = rows.length === cad.items.length ? cad.items.length + ' no total' : rows.length + ' de ' + cad.items.length;
    if (!cad.items.length) { list.innerHTML = '<div class="empty-state"><p><strong>Nenhum cadastro recebido ainda.</strong></p></div>'; return; }
    if (!rows.length) { list.innerHTML = '<div class="empty-state"><p>Nenhum cadastro com esses filtros.</p></div>'; return; }
    list.innerHTML = rows.map(function (i) {
      var open = cad.open === i.id;
      var origem = i.coleta_id ? esc(i.coletor || '—') + ' → ' + esc(i.emissor || '—') : 'Cadastro antigo, sem pedido';
      return '<article class="pedido' + (open ? ' is-open' : '') + '" data-id="' + esc(i.id) + '">'
        + '<button type="button" class="pedido-head">'
        + '<span class="record-avatar">' + esc(initials(i.nome)) + '</span>'
        + '<span class="pedido-main"><strong>' + esc(i.nome) + '</strong><span class="record-meta">' + i.tipo.toUpperCase() + ' · ' + esc(fmtDoc(i.documento)) + (i.local ? ' · ' + esc(i.local) : '') + '</span></span>'
        + '<span class="pedido-origem" title="Coletor → emissor">' + origem + '</span>'
        + '<span class="pedido-date">' + fmtDate(i.updated) + '</span></button>'
        + (open ? '<div class="pedido-body" id="cadDetail">' + (cad.cache[i.id] ? cadDetailHtml(cad.cache[i.id]) : '<p class="muted">Carregando…</p>') + '</div>' : '')
        + '</article>';
    }).join('');
  }

  function onCadClick(e) {
    var btnExcluir = e.target.closest('[data-excluir]');
    if (btnExcluir) { excluirCadastro(btnExcluir.getAttribute('data-excluir')); return; }
    var head = e.target.closest('.pedido-head');
    if (!head) return;
    var id = head.closest('.pedido').getAttribute('data-id');
    cad.open = cad.open === id ? null : id;
    drawCadastros();
    if (cad.open && !cad.cache[id]) {
      api('cadastro', { id: id }).then(function (d) {
        cad.cache[id] = d;
        if (cad.open === id) drawCadastros();
      }).catch(function (err) {
        var el = document.getElementById('cadDetail');
        if (el) el.innerHTML = '<p class="error">' + esc(err.message) + '</p>';
      });
    }
  }

  function cadDetailHtml(d) {
    var data = d.data || {}, html = '';
    Object.keys(LABELS).forEach(function (g) {
      var vals = data[g] || {}, items = '';
      Object.keys(LABELS[g][1]).forEach(function (f) {
        var v = vals[f];
        if (v === undefined || v === null || v === '') return;
        if (f === 'cnpj' || f === 'cpf') v = fmtDoc(v);
        if (DATE_FIELDS[f]) v = fmtDateStr(v);
        items += '<div class="detail-item"><div class="detail-label">' + esc(LABELS[g][1][f]) + '</div><div class="detail-value">' + esc(v) + '</div></div>';
      });
      if (items) html += '<h3 class="pedido-sub">' + esc(LABELS[g][0]) + '</h3><div class="detail-grid">' + items + '</div>';
    });
    var files = '';
    Object.keys(FILES).forEach(function (g) {
      Object.keys(FILES[g]).forEach(function (f) {
        var info = (data[g] || {})[f];
        if (!info || !info.storedName) return;
        var url = 'download.php?id=' + encodeURIComponent(d.id) + '&group=' + g + '&field=' + f;
        files += '<li><span>' + esc(FILES[g][f]) + '</span><span><a href="' + url + '" target="_blank" rel="noopener">Ver</a> · <a href="' + url + '&download=1">Baixar</a></span></li>';
      });
    });
    if (files) html += '<h3 class="pedido-sub">Arquivos</h3><ul class="file-list">' + files + '</ul>';
    if (d.eventos && d.eventos.length) {
      html += '<h3 class="pedido-sub">Histórico do pedido</h3><ol class="timeline">' + d.eventos.slice().reverse().map(function (ev) {
        return '<li><span>' + esc(EVENTO[ev.evento] || ev.evento) + (ev.por ? ' <span class="muted">· ' + esc(ev.por) + '</span>' : '') + '</span><time>' + fmtDate(ev.at) + '</time></li>';
      }).join('') + '</ol>';
    }
    return html + '<div class="pedido-actions"><button type="button" class="danger" data-excluir="' + esc(d.id) + '">Excluir cadastro</button>'
      + '<span class="muted">Apaga os dados e os arquivos. Não dá para desfazer.</span></div>';
  }

  function excluirCadastro(id) {
    if (!confirm('Excluir este cadastro e os arquivos dele? Não dá para desfazer.')) return;
    api('excluir_cadastro', null, fd({ id: id })).then(function () {
      cad.items = cad.items.filter(function (i) { return i.id !== id; });
      cad.cache[id] = null;
      cad.open = null;
      drawCadastros();
    }).catch(function (err) { alert(err.message); });
  }

  /* ---------- Contas ---------- */
  var contas = { items: [] };

  function renderContas() {
    content.innerHTML = '<div class="admin-head"><h1>Contas</h1><button type="button" class="primary" id="btnNova">Nova conta</button></div>'
      + '<div class="filters filters-4"><label class="f-q">Buscar<input id="kQ" type="search" placeholder="Usuário ou e-mail"></label>'
      + '<label>Perfil<select id="kPerfil"><option value="">Todos</option><option value="coletor">Coletor</option><option value="emissor">Emissor</option><option value="none">Sem perfil</option><option value="admin">Admin</option></select></label>'
      + '<label>Situação<select id="kStatus"><option value="">Todas</option><option value="1">Ativas</option><option value="0">Bloqueadas</option></select></label>'
      + '<button type="button" class="ghost" id="kLimpar">Limpar</button></div>'
      + '<div id="contasList"><div class="empty-state"><p>Carregando…</p></div></div>';
    ['kQ', 'kPerfil', 'kStatus'].forEach(function (id) { document.getElementById(id).addEventListener('input', drawContas); });
    document.getElementById('kLimpar').addEventListener('click', function () {
      ['kQ', 'kPerfil', 'kStatus'].forEach(function (id) { document.getElementById(id).value = ''; });
      drawContas();
    });
    document.getElementById('btnNova').addEventListener('click', novaConta);
    document.getElementById('contasList').addEventListener('click', onContaClick);
    loadContas();
  }

  function loadContas() {
    return api('contas').then(function (d) { contas.items = d.items; drawContas(); }).catch(fail);
  }

  function perfilLabel(u) { return u.role === 'admin' ? 'Admin' : (PROFILE[u.profile] || 'Sem perfil'); }

  function drawContas() {
    var list = document.getElementById('contasList');
    if (!list) return;
    var q = document.getElementById('kQ').value.trim().toLocaleLowerCase('pt-BR');
    var perfil = document.getElementById('kPerfil').value, status = document.getElementById('kStatus').value;
    var rows = contas.items.filter(function (u) {
      var p = u.role === 'admin' ? 'admin' : (u.profile || 'none');
      return (!q || (u.username + ' ' + (u.email || '')).toLocaleLowerCase('pt-BR').indexOf(q) !== -1)
        && (!perfil || p === perfil) && (status === '' || String(u.active) === status);
    });
    if (!rows.length) { list.innerHTML = '<div class="empty-state"><p>Nenhuma conta encontrada.</p></div>'; return; }
    list.innerHTML = '<div class="table-wrap"><table class="table"><thead><tr><th>Conta</th><th>Perfil</th><th>Situação</th><th>Pedidos</th><th>Cadastros</th><th>Último acesso</th><th>Criada em</th><th></th></tr></thead><tbody>'
      + rows.map(function (u) {
        var acoes = u.role === 'seller'
          ? '<button type="button" data-editar="' + u.id + '">Editar</button>'
            + '<button type="button" data-reset="' + u.id + '">Nova senha</button>'
            + '<button type="button" class="' + (u.active ? 'ghost' : 'primary') + '" data-status="' + u.id + '" data-active="' + (u.active ? 0 : 1) + '">' + (u.active ? 'Bloquear' : 'Desbloquear') + '</button>'
            + '<button type="button" class="danger" data-excluir="' + u.id + '">Apagar</button>'
          : (u.me ? '<span class="muted">você</span>' : '');
        return '<tr' + (u.active ? '' : ' class="is-blocked"') + '>'
          + '<td><div class="cell-main"><strong>' + esc(u.username) + '</strong><span class="muted">' + esc(u.email || 'sem e-mail') + '</span></div></td>'
          + '<td><span class="badge">' + perfilLabel(u) + '</span></td>'
          + '<td><span class="badge ' + (u.active ? 'badge-ok' : 'badge-bad') + '">' + (u.active ? 'Ativa' : 'Bloqueada') + '</span></td>'
          + '<td class="num">' + u.pedidos + '</td><td class="num">' + u.cadastros + '</td>'
          + '<td>' + ago(u.seen) + '</td><td>' + fmtDate(u.created) + '</td>'
          + '<td><div class="actions">' + acoes + '</div></td></tr>';
      }).join('') + '</tbody></table></div>';
  }

  function contaById(id) {
    return contas.items.filter(function (u) { return String(u.id) === String(id); })[0];
  }

  function onContaClick(e) {
    var b = e.target.closest('[data-status]');
    if (b) {
      var u = contaById(b.getAttribute('data-status')), ativar = b.getAttribute('data-active') === '1';
      if (!confirm((ativar ? 'Desbloquear ' : 'Bloquear ') + u.username + '?' + (ativar ? '' : ' A pessoa perde o acesso em até 1 minuto.'))) return;
      b.disabled = true;
      api('conta_status', null, fd({ id: u.id, active: ativar ? 1 : 0 })).then(loadContas).catch(function (err) { alert(err.message); b.disabled = false; });
      return;
    }
    var r = e.target.closest('[data-reset]');
    if (r) { resetSenha(contaById(r.getAttribute('data-reset'))); return; }
    var ed = e.target.closest('[data-editar]');
    if (ed) { editarConta(contaById(ed.getAttribute('data-editar'))); return; }
    var ex = e.target.closest('[data-excluir]');
    if (ex) apagarConta(contaById(ex.getAttribute('data-excluir')));
  }

  function editarConta(u) {
    openModal('<h2>Editar conta</h2><form id="mForm" novalidate>'
      + '<label>Usuário<input id="mUser" autocomplete="off" value="' + esc(u.username) + '" required></label>'
      + '<label>E-mail<input id="mEmail" type="email" autocomplete="off" placeholder="opcional" value="' + esc(u.email || '') + '"></label>'
      + '<label>CPF ou CNPJ<input id="mDoc" class="mono" inputmode="numeric" autocomplete="off" placeholder="opcional" value="' + esc(fmtDoc(u.documento || '')) + '"></label>'
      + '<p class="error" id="mErro" hidden></p><div class="modal-actions"><button type="button" class="ghost" data-close>Cancelar</button><button type="submit" class="primary">Salvar</button></div></form>');
    document.getElementById('mUser').focus();
    document.getElementById('mDoc').addEventListener('input', function () { mascaraDocAuto(this); });
    document.getElementById('mForm').addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = this.querySelector('[type=submit]');
      btn.disabled = true;
      api('conta_editar', null, fd({
        id: u.id,
        username: document.getElementById('mUser').value,
        email: document.getElementById('mEmail').value,
        documento: digits(document.getElementById('mDoc').value)
      })).then(function () {
        closeModal();
        loadContas();
      }).catch(function (err) { modalError(err); btn.disabled = false; });
    });
  }

  function apagarConta(u) {
    var aviso = 'Apagar a conta de ' + u.username + '?';
    if (u.profile === 'coletor') aviso += ' Os pedidos que ela criou como coletora também serão apagados.';
    if (u.profile === 'emissor') aviso += ' Os cadastros que ela enviou como emissora também serão apagados.';
    aviso += ' Não dá para desfazer.';
    if (!confirm(aviso)) return;
    api('conta_excluir', null, fd({ id: u.id })).then(loadContas).catch(function (err) { alert(err.message); });
  }

  function emailCheck(email) {
    return email
      ? '<label class="check"><input type="checkbox" id="mEnviar" checked> Enviar por e-mail para ' + esc(email) + '</label>'
      : '<p class="muted">Esta conta não tem e-mail: anote a senha e passe para a pessoa.</p>';
  }

  function resetSenha(u) {
    openModal('<h2>Nova senha</h2><p class="muted">Define a senha de <strong>' + esc(u.username) + '</strong> como <strong>123456</strong>. A senha atual deixa de funcionar.</p>'
      + '<p class="error" id="mErro" hidden></p><div class="modal-actions"><button type="button" class="ghost" data-close>Cancelar</button><button type="button" class="primary" id="mOk">Redefinir senha</button></div>');
    document.getElementById('mOk').addEventListener('click', function () {
      var btn = this;
      btn.disabled = true;
      api('conta_reset', null, fd({ id: u.id })).then(showSenha).catch(function (err) { modalError(err); btn.disabled = false; });
    });
  }

  function novaConta() {
    openModal('<h2>Nova conta</h2><form id="mForm" novalidate>'
      + '<label>Usuário<input id="mUser" autocomplete="off" required></label>'
      + '<label>E-mail<input id="mEmail" type="email" autocomplete="off" placeholder="opcional"></label>'
      + '<label>CPF ou CNPJ<input id="mDoc" class="mono" inputmode="numeric" autocomplete="off" placeholder="opcional"></label>'
      + '<label class="check"><input type="checkbox" id="mEnviar" checked> Enviar usuário e senha por e-mail</label>'
      + '<p class="error" id="mErro" hidden></p><div class="modal-actions"><button type="button" class="ghost" data-close>Cancelar</button><button type="submit" class="primary">Criar conta</button></div></form>');
    document.getElementById('mUser').focus();
    document.getElementById('mDoc').addEventListener('input', function () { mascaraDocAuto(this); });
    document.getElementById('mForm').addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = this.querySelector('[type=submit]');
      btn.disabled = true;
      api('conta_criar', null, fd({
        username: document.getElementById('mUser').value,
        email: document.getElementById('mEmail').value,
        documento: digits(document.getElementById('mDoc').value),
        enviar: document.getElementById('mEnviar').checked ? 1 : 0
      })).then(showSenha).catch(function (err) { modalError(err); btn.disabled = false; });
    });
  }

  function showSenha(d) {
    openModal('<h2>Senha gerada</h2><p class="muted">Conta <strong>' + esc(d.username) + '</strong>. '
      + (d.emailed ? 'A senha também foi enviada por e-mail.' : 'Passe esta senha para a pessoa; ela pode trocar depois em "Mudar senha".') + '</p>'
      + '<div class="secret"><span>' + esc(d.password) + '</span><button type="button" id="mCopiar">Copiar</button></div>'
      + '<div class="modal-actions"><button type="button" class="primary" data-close>Fechar</button></div>');
    document.getElementById('mCopiar').addEventListener('click', function () { copyText(d.password, this); });
    if (current === 'contas') loadContas();
  }

  /* ---------- Online agora ---------- */
  function fetchOnline() {
    return fetch('admin/online.php', { cache: 'no-store', credentials: 'same-origin' }).then(function (r) {
      if (r.status === 401) { window.location.href = 'login.php'; return null; }
      return r.json();
    }).then(function (d) {
      if (!d) return null;
      if (!d.ok) throw new Error(d.error || 'Erro ao carregar usuários online');
      document.getElementById('onlineCount').textContent = d.users.length;
      return d;
    });
  }

  function renderOnline() {
    content.innerHTML = '<div class="admin-head"><h1>Online agora</h1><span class="muted">Ativos nos últimos 5 min · atualiza a cada 30 s</span></div><div id="onlineList"><div class="empty-state"><p>Carregando…</p></div></div>';
    fetchOnline().then(function (d) { if (d) drawOnline(d); }).catch(fail);
  }

  function drawOnline(d) {
    var list = document.getElementById('onlineList');
    if (!list) return;
    if (!d.users.length) { list.innerHTML = '<div class="empty-state"><p>Ninguém online no momento.</p></div>'; return; }
    var now = Math.round(Date.now() / 1000);
    list.innerHTML = '<div class="online-list">' + d.users.map(function (u) {
      var idle = Number(u.idle_seconds), active = idle < 90;
      var since = u.login_seconds_ago !== null ? 'entrou ' + fmtDate(now - u.login_seconds_ago) : '';
      return '<div class="online-row"><span class="online-dot' + (active ? '' : ' is-idle') + '"></span>'
        + '<div class="online-main"><strong>' + esc(u.username) + (u.is_me ? ' <span class="muted">(você)</span>' : '') + '</strong><span class="online-meta">' + esc(u.email || '—') + '</span></div>'
        + '<span class="online-badge">' + perfilLabel(u) + '</span>'
        + '<span class="online-meta mono" title="Endereço IP">' + esc(u.last_ip || '—') + '</span>'
        + '<span class="online-meta online-when">' + (active ? 'ativo agora' : 'visto ' + ago(now - idle)) + (since ? '<br>' + since : '') + '</span></div>';
    }).join('') + '</div>';
  }

  /* ---------- Métricas ---------- */
  var periodo = '7', acessos = [];
  var PERIODOS = [['1', '24 horas'], ['7', '7 dias'], ['30', '30 dias'], ['all', 'Tudo']];
  var CARDS = [['logins', 'Logins'], ['usuarios', 'Pessoas que entraram'], ['ips', 'IPs diferentes'], ['falhas', 'Tentativas com senha errada'],
    ['contas', 'Contas criadas'], ['pedidos', 'Pedidos criados'], ['cadastros', 'Cadastros recebidos'], ['online', 'Online agora']];

  function renderMetricas() {
    content.innerHTML = '<div class="admin-head"><h1>Métricas</h1><div class="admin-tools"><div class="chips" id="mPeriodo">'
      + PERIODOS.map(function (p) { return '<button type="button" class="chip' + (p[0] === periodo ? ' is-active' : '') + '" data-p="' + p[0] + '">' + p[1] + '</button>'; }).join('')
      + '</div><button type="button" class="ghost" id="mZerar">Zerar métricas</button></div></div>'
      + '<div id="mBody"><div class="empty-state"><p>Carregando…</p></div></div>';
    document.getElementById('mPeriodo').addEventListener('click', function (e) {
      var b = e.target.closest('[data-p]');
      if (!b) return;
      periodo = b.getAttribute('data-p');
      renderMetricas();
    });
    document.getElementById('mZerar').addEventListener('click', zerarMetricas);
    api('metricas', { periodo: periodo }).then(drawMetricas).catch(fail);
  }

  function dayLabel(s) { return s ? s.dia.split('-').reverse().slice(0, 2).join('/') : ''; }

  function drawMetricas(d) {
    var body = document.getElementById('mBody');
    if (!body) return;
    var max = Math.max.apply(null, d.serie.map(function (s) { return s.logins; }).concat([1]));
    var bars = d.serie.map(function (s) {
      return '<div class="bar-col" title="' + dayLabel(s) + ': ' + s.logins + ' logins, ' + s.ips + ' IPs"><div class="bar" style="height:' + Math.round(s.logins / max * 100) + '%"></div></div>';
    }).join('');
    var ips = d.top_ips.length
      ? '<div class="table-wrap"><table class="table"><thead><tr><th>IP</th><th>Acessos</th><th>Contas</th><th>Último</th></tr></thead><tbody>'
        + d.top_ips.map(function (r) { return '<tr><td class="num">' + esc(r.ip || '—') + '</td><td class="num">' + r.acessos + '</td><td class="num">' + r.contas + '</td><td>' + fmtDate(r.ultimo) + '</td></tr>'; }).join('')
        + '</tbody></table></div>'
      : '<p class="muted">Sem acessos no período.</p>';
    body.innerHTML = (d.reset_at ? '<div class="notice">Contando desde ' + fmtDate(d.reset_at) + ', quando as métricas foram zeradas.</div>' : '')
      + '<div class="metric-grid">' + CARDS.map(function (k) {
        return '<div class="metric"><strong>' + Number(d.cards[k[0]] || 0).toLocaleString('pt-BR') + '</strong><span>' + k[1] + '</span></div>';
      }).join('') + '</div>'
      + '<div class="card"><h2 class="card-title">Logins por dia</h2><div class="bars">' + bars + '</div>'
      + '<div class="bars-labels"><span>' + dayLabel(d.serie[0]) + '</span><span>' + dayLabel(d.serie[d.serie.length - 1]) + '</span></div></div>'
      + '<div class="card"><h2 class="card-title">IPs com mais acessos</h2>' + ips + '</div>'
      + '<div class="card"><div class="card-head"><h2 class="card-title">Últimos acessos</h2><input id="aQ" type="search" placeholder="Filtrar por usuário, IP ou evento"></div><div id="aList"></div></div>';
    acessos = d.ultimos;
    document.getElementById('aQ').addEventListener('input', drawAcessos);
    drawAcessos();
  }

  function drawAcessos() {
    var list = document.getElementById('aList');
    if (!list) return;
    var q = document.getElementById('aQ').value.trim().toLocaleLowerCase('pt-BR');
    var rows = acessos.filter(function (r) {
      return !q || [r.username, r.ip, EVENT[r.event] || r.event, browserOf(r.user_agent)].join(' ').toLocaleLowerCase('pt-BR').indexOf(q) !== -1;
    });
    if (!rows.length) { list.innerHTML = '<p class="muted">Nenhum acesso encontrado.</p>'; return; }
    list.innerHTML = '<div class="table-wrap"><table class="table"><thead><tr><th>Data e hora</th><th>Usuário</th><th>Evento</th><th>IP</th><th>Navegador</th></tr></thead><tbody>'
      + rows.map(function (r) {
        var cls = r.event === 'login_failed' ? 'badge-bad' : r.event === 'logout' ? '' : 'badge-ok';
        return '<tr><td>' + fmtDate(r.at) + '</td><td>' + esc(r.username || '—') + '</td><td><span class="badge ' + cls + '">' + esc(EVENT[r.event] || r.event) + '</span></td>'
          + '<td class="num">' + esc(r.ip || '—') + '</td><td>' + esc(browserOf(r.user_agent)) + '</td></tr>';
      }).join('') + '</tbody></table></div>';
  }

  function zerarMetricas() {
    if (!confirm('Zerar as métricas? O histórico de acessos é apagado e a contagem recomeça agora. Contas, pedidos e cadastros não são apagados.')) return;
    api('zerar_metricas', null, fd({})).then(renderMetricas).catch(function (err) { alert(err.message); });
  }

  /* ---------- Mudar senha ---------- */
  function renderSenha() {
    content.innerHTML = '<div class="admin-head"><h1>Mudar senha</h1></div>'
      + '<form class="card senha-card" id="senhaForm" novalidate>'
      + '<label class="field"><span class="field-label">Senha atual</span><input type="password" id="senhaAtual" autocomplete="current-password" required></label>'
      + '<label class="field"><span class="field-label">Nova senha</span><input type="password" id="senhaNova" autocomplete="new-password" minlength="6" required></label>'
      + '<label class="field"><span class="field-label">Confirmar nova senha</span><input type="password" id="senhaConfirm" autocomplete="new-password" minlength="6" required></label>'
      + '<div id="senhaMsg"></div><button type="submit" class="primary">Alterar senha</button></form>';
    document.getElementById('senhaForm').addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = document.getElementById('senhaMsg'), form = this;
      var nova = document.getElementById('senhaNova').value;
      if (nova !== document.getElementById('senhaConfirm').value) { msg.innerHTML = '<div class="notice notice-warn">As senhas novas não conferem.</div>'; return; }
      fetch('mudar-senha.php', { method: 'POST', credentials: 'same-origin', body: fd({ senha_atual: document.getElementById('senhaAtual').value, senha_nova: nova, senha_confirma: nova }) })
        .then(function (r) { return r.text(); })
        .then(function (text) {
          if (/data-status="ok"/.test(text)) {
            msg.innerHTML = '<div class="notice notice-ok">Senha alterada com sucesso!</div>';
            form.reset();
          } else if (/data-status="erro_atual"/.test(text)) {
            msg.innerHTML = '<div class="notice notice-warn">Senha atual incorreta.</div>';
          } else {
            msg.innerHTML = '<div class="notice notice-warn">Não foi possível alterar. A nova senha precisa ter pelo menos 6 caracteres.</div>';
          }
        })
        .catch(function (err) { msg.innerHTML = '<div class="notice notice-warn">' + esc(err.message) + '</div>'; });
    });
  }

  /* ---------- Navegação ---------- */
  var SECTIONS = { cadastros: renderCadastros, contas: renderContas, online: renderOnline, metricas: renderMetricas, senha: renderSenha };

  function route() {
    var name = (window.location.hash || '#cadastros').slice(1);
    if (!SECTIONS[name]) name = 'cadastros';
    current = name;
    document.querySelectorAll('#adminNav a').forEach(function (a) { a.classList.toggle('active', a.getAttribute('data-section') === name); });
    closeModal();
    SECTIONS[name]();
  }

  window.addEventListener('hashchange', route);
  setInterval(function () {
    fetchOnline().then(function (d) { if (d && current === 'online') drawOnline(d); }).catch(function () {});
  }, 30000);
  fetchOnline().catch(function () {});
  route();
})();
