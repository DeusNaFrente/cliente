<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

touchSession();
if (currentRole() === null) {
    header('Location: login.php');
    exit;
}

$status = '';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $field = function (string $n): string {
        return is_string($_POST[$n] ?? null) ? $_POST[$n] : '';
    };
    $atual = $field('senha_atual');
    $nova = $field('senha_nova');
    if ($atual === '' || $nova === '' || $field('senha_confirma') === '') {
        [$status, $msg] = ['erro', t('Preencha todos os campos.')];
    } elseif ($nova !== $field('senha_confirma')) {
        [$status, $msg] = ['erro', t('As senhas novas não conferem.')];
    } elseif (strlen($nova) < 6) {
        [$status, $msg] = ['erro', t('A nova senha deve ter pelo menos 6 caracteres.')];
    } else {
        $st = getDB()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $st->execute([currentUserId()]);
        $hash = $st->fetchColumn();
        if (!$hash || !password_verify($atual, (string) $hash)) {
            [$status, $msg] = ['erro_atual', t('Senha atual incorreta.')];
        } else {
            getDB()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($nova, PASSWORD_BCRYPT), currentUserId()]);
            [$status, $msg] = ['ok', t('Senha alterada com sucesso!')];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlLang() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= t('Mudar senha') ?></title>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="stylesheet" href="<?= av('assets/app.css') ?>">
</head>
<body>
<section class="view">
  <?php renderLangSelector(); ?>
  <div class="login-shell">
    <div class="login-hero">
      <div class="seal">CC</div>
      <p class="eyebrow"><?= t('Segurança') ?></p>
      <h1><?= t('Alterar sua senha') ?></h1>
      <p class="lede"><?= t('Mantenha sua conta segura com uma senha forte e única.') ?></p>
    </div>
    <div class="login-panel">
      <div class="login-card">
        <p class="eyebrow"><?= t('Conta') ?></p>
        <h2><?= t('Mudar senha') ?></h2>
        <p class="subtitle"><?= esc(currentUsername()) ?></p>
        <div id="resultado" data-status="<?= esc($status) ?>">
          <?php if ($msg !== ''): ?><div class="notice <?= $status === 'ok' ? 'notice-ok' : 'notice-warn' ?>"><?= esc($msg) ?></div><?php endif; ?>
        </div>
        <form method="post" novalidate>
          <label><?= t('Senha atual') ?>
            <input type="password" name="senha_atual" autocomplete="current-password" required autofocus>
          </label>
          <label><?= t('Nova senha') ?>
            <input type="password" name="senha_nova" autocomplete="new-password" minlength="6" required>
          </label>
          <label><?= t('Confirmar nova senha') ?>
            <input type="password" name="senha_confirma" autocomplete="new-password" minlength="6" required>
          </label>
          <button type="submit" class="primary"><?= t('Alterar senha') ?></button>
        </form>
        <p class="alt-link"><a href="<?= esc(homeUrl()) ?>">← <?= t('Voltar') ?></a></p>
      </div>
    </div>
  </div>
</section>
<script src="<?= av('assets/heartbeat.js') ?>"></script>
</body>
</html>
