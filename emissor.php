<?php
declare(strict_types=1);
require_once __DIR__ . '/coletas_lib.php';
requireProfile('emissor');

$aberto = (int) ($_GET['aberto'] ?? 0);
$st = getDB()->prepare(
    'SELECT c.id, c.type_pessoa, c.nome, c.documento, c.status, UNIX_TIMESTAMP(c.updated_at) AS updated, u.username AS coletor
       FROM coletas c JOIN users u ON u.id = c.coletor_id
      WHERE c.emissor_id = ?
      ORDER BY c.updated_at DESC, c.id DESC'
);
$st->execute([(int) currentUserId()]);
$pedidos = $st->fetchAll();
$status = ['aguardando' => t('Aguardando'), 'aberto' => t('Aberto'), 'recebido' => t('Recebido')];
?>
<!DOCTYPE html>
<html lang="<?= htmlLang() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= t('Emissor') ?> - <?= t('Cadastro de Cliente') ?></title>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="stylesheet" href="<?= av('assets/app.css') ?>">
</head>
<body>
<section class="view">
  <div class="app-shell">
    <?php renderUserRail([
        ['href' => 'emissor.php', 'idx' => '01', 'label' => 'Meus pedidos', 'active' => true],
        ['href' => 'mudar-senha.php', 'idx' => '02', 'label' => 'Mudar senha'],
    ]); ?>
    <div class="app-main">
      <div class="app-main-inner">
        <div class="form-head">
          <p class="eyebrow"><?= t('Perfil emissor') ?></p>
          <h1><?= t('Meus pedidos') ?></h1>
          <p class="muted"><?= t('Pedidos de cadastro que chegaram para você. Quando alguém te enviar um link, é só abrir: o pedido aparece aqui.') ?></p>
        </div>
        <?php renderFlash(); ?>

        <?php if (!$pedidos): ?>
          <div class="empty-state">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/></svg>
            <p><strong><?= t('Nenhum pedido ainda.') ?></strong><br><?= t('Abra o link que te enviaram e o pedido aparece aqui.') ?></p>
          </div>
        <?php else: ?>
          <?php foreach ($pedidos as $p): ?>
            <article class="pedido<?= (int) $p['id'] === $aberto ? ' is-open' : '' ?>">
              <div class="pedido-head pedido-static">
                <span class="record-avatar"><?= esc(initials($p['nome'])) ?></span>
                <span class="pedido-main">
                  <strong><?= esc($p['nome']) ?></strong>
                  <span class="record-meta"><?= t('Pedido de {nome}', ['nome' => esc($p['coletor'])]) ?> · <?= strtoupper($p['type_pessoa']) ?> · <?= esc(formatDoc($p['documento'])) ?></span>
                </span>
                <span class="status-badge status-<?= esc($p['status']) ?>"><?= esc($status[$p['status']] ?? $p['status']) ?></span>
                <span class="pedido-date"><?= esc(fmtTs((int) $p['updated'])) ?></span>
              </div>
              <div class="pedido-body pedido-foot">
                <?php if ($p['status'] === 'recebido'): ?>
                  <a class="button" href="cadastro.php?coleta=<?= (int) $p['id'] ?>"><?= t('Ver e reenviar') ?></a>
                  <button type="button" class="ghost" data-apagar="<?= (int) $p['id'] ?>"><?= t('Apagar envio') ?></button>
                  <span class="muted"><?= t('Cadastro enviado. A cópia fica aqui.') ?></span>
                <?php else: ?>
                  <a class="button primary" href="cadastro.php?coleta=<?= (int) $p['id'] ?>"><?= t('Preencher cadastro') ?></a>
                  <span class="muted"><?= t('Os dados que {nome} informou já vêm preenchidos.', ['nome' => esc($p['coletor'])]) ?></span>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?= jsI18n() ?>
<script src="<?= av('assets/emissor.js') ?>"></script>
<script src="<?= av('assets/heartbeat.js') ?>"></script>
</body>
</html>
