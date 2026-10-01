<?php
declare(strict_types=1);
require_once __DIR__ . '/coletas_lib.php';
requireProfile('coletor');
?>
<!DOCTYPE html>
<html lang="<?= htmlLang() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= t('Coletor') ?> - <?= t('Cadastro de Cliente') ?></title>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="stylesheet" href="<?= av('assets/app.css') ?>">
</head>
<body>
<section class="view">
  <div class="app-shell">
    <?php renderUserRail([
        ['href' => '#novo', 'idx' => '01', 'label' => 'Novo pedido', 'active' => true],
        ['href' => '#pedidos', 'idx' => '02', 'label' => 'Pedidos enviados'],
        ['href' => 'mudar-senha.php', 'idx' => '03', 'label' => 'Mudar senha'],
    ]); ?>
    <div class="app-main">
      <div class="app-main-inner">
        <div class="form-head">
          <p class="eyebrow"><?= t('Perfil coletor') ?></p>
          <h1><?= t('Pedidos de cadastro') ?></h1>
          <p class="muted"><?= t('Informe o cliente, gere o link e envie por onde preferir. O cadastro preenchido chega aqui.') ?></p>
        </div>
        <?php renderFlash(); ?>

        <form class="card" id="novo" novalidate>
          <div class="type-card">
            <div>
              <h2><?= t('Novo pedido') ?></h2>
              <p class="muted"><?= t('Quem você vai cadastrar?') ?></p>
            </div>
            <div class="seg" id="tipoSeg" role="radiogroup" aria-label="<?= esc(t('Tipo de pessoa')) ?>" data-value="pj">
              <span class="seg-thumb"></span>
              <button type="button" class="seg-btn is-active" role="radio" aria-checked="true" data-value="pj"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v16"/><path d="M14 10h5a1 1 0 0 1 1 1v10"/><path d="M8 8h2M8 12h2M8 16h2M3 21h18"/></svg> <?= t('Pessoa Jurídica') ?></button>
              <button type="button" class="seg-btn" role="radio" aria-checked="false" data-value="pf"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg> <?= t('Pessoa Física') ?></button>
            </div>
          </div>
          <div class="novo-grid">
            <label class="field"><span class="field-label" id="nomeLabel"><?= t('Razão social') ?></span>
              <input id="nomeInput" name="nome" autocomplete="off" maxlength="200" required list="clientesList">
              <datalist id="clientesList"></datalist>
              <small class="hint" id="nomeHint"></small></label>
            <label class="field"><span class="field-label" id="docLabel">CNPJ</span>
              <input id="docInput" class="mono" name="documento" inputmode="numeric" autocomplete="off" required>
              <small class="hint" id="docHint"></small></label>
            <label class="field field-full"><span class="field-label"><?= t('E-mail de quem vai preencher') ?></span>
              <input id="emailInput" type="email" name="email" autocomplete="off" maxlength="150" required>
              <small class="hint"><?= t('Avisamos essa pessoa assim que ela abrir o link, sem precisar digitar nada.') ?></small></label>
          </div>
          <div class="novo-actions">
            <button type="submit" class="primary" id="btnGerar"><?= t('Gerar link') ?></button>
            <p class="error" id="formMsg" hidden></p>
          </div>
        </form>

        <section class="card share-card" id="sharePanel" hidden aria-live="polite">
          <div class="share-head">
            <div><p class="eyebrow"><?= t('Pronto para enviar') ?></p><h2 id="shareTitle"></h2></div>
            <button type="button" class="ghost icon-btn" id="shareClose" aria-label="<?= esc(t('Fechar')) ?>">✕</button>
          </div>
          <pre class="share-text" id="shareText"></pre>
          <div class="share-actions">
            <button type="button" class="primary" id="copyMsg"><?= t('Copiar mensagem') ?></button>
            <button type="button" id="copyLink"><?= t('Copiar só o link') ?></button>
            <a class="button whats" id="shareWhats" href="#" target="_blank" rel="noopener">WhatsApp</a>
          </div>
          <p class="muted share-note"><?= t('O link vale para um único acesso. Para enviar de novo, use "Reenviar" no pedido: um link novo é gerado e o anterior deixa de funcionar.') ?></p>
        </section>

        <section id="pedidos">
          <div class="list-head"><h2><?= t('Pedidos enviados') ?></h2><span class="muted" id="listaCount"></span></div>
          <div class="filters">
            <label class="f-q"><?= t('Buscar') ?><input id="fQ" type="search" placeholder="<?= esc(t('Nome ou documento')) ?>"></label>
            <label><?= t('De') ?><input id="fDe" type="datetime-local"></label>
            <label><?= t('Até') ?><input id="fAte" type="datetime-local"></label>
            <label><?= t('Situação') ?><select id="fStatus"><option value=""><?= t('Todas') ?></option><option value="aguardando"><?= t('Aguardando') ?></option><option value="aberto"><?= t('Aberto') ?></option><option value="recebido"><?= t('Recebido') ?></option></select></label>
            <button type="button" class="ghost" id="fLimpar"><?= t('Limpar') ?></button>
          </div>
          <div id="lista"><div class="empty-state"><p><?= t('Carregando…') ?></p></div></div>
        </section>
      </div>
    </div>
  </div>
</section>
<?= jsI18n() ?>
<script src="<?= av('assets/mask.js') ?>"></script>
<script src="<?= av('assets/coletor.js') ?>"></script>
<script src="<?= av('assets/heartbeat.js') ?>"></script>
</body>
</html>
