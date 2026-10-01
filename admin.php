<?php
declare(strict_types=1);
require_once __DIR__ . '/coletas_lib.php';
requireRole('admin');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - Coleta de Dados</title>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="stylesheet" href="<?= av('assets/app.css') ?>">
</head>
<body>
<section class="view">
  <div class="app-shell">
    <aside class="rail rail-admin no-print">
      <div class="rail-brand">
        <span class="rail-mark">CC</span>
        <div><strong>Cadastro</strong><span>Administração</span></div>
      </div>
      <?php renderProfileSwitch(); ?>
      <nav class="rail-nav" aria-label="Menu" id="adminNav">
        <a href="#cadastros" data-section="cadastros"><span class="idx">01</span>Cadastros</a>
        <a href="#contas" data-section="contas"><span class="idx">02</span>Contas</a>
        <a href="#online" data-section="online"><span class="idx">03</span>Online agora<span class="online-count" id="onlineCount"></span></a>
        <a href="#metricas" data-section="metricas"><span class="idx">04</span>Métricas</a>
        <a href="#senha" data-section="senha"><span class="idx">05</span>Mudar senha</a>
      </nav>
      <div class="rail-foot">
        <div class="rail-account">
          <span class="rail-avatar"><?= esc(initials(currentUsername() ?? '')) ?></span>
          <div class="rail-account-info">
            <span class="rail-username" title="<?= esc(currentUsername()) ?>"><?= esc(currentUsername()) ?></span>
            <span class="rail-profile-label">Administrador</span>
          </div>
        </div>
        <div class="rail-foot-row">
          <a class="button rail-logout" href="logout.php" style="margin-left:auto">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
            Sair
          </a>
        </div>
      </div>
    </aside>
    <div class="app-main">
      <div class="admin-main-inner" id="adminContent"></div>
    </div>
  </div>
</section>
<div class="modal" id="modal" hidden>
  <div class="modal-card" role="dialog" aria-modal="true" id="modalBody"></div>
</div>
<script src="<?= av('assets/mask.js') ?>"></script>
<script src="<?= av('assets/admin.js') ?>"></script>
<script src="<?= av('assets/heartbeat.js') ?>"></script>
</body>
</html>
