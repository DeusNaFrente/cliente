<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
touchSession();
if (!in_array(currentRole(), ['admin', 'seller'], true)) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profile = (string) ($_POST['profile'] ?? '');
    if (currentRole() === 'admin' && $profile === 'admin') {
        setProfile(null);
    } elseif (in_array($profile, PROFILES, true)) {
        setProfile($profile);
    }
    header('Location: ' . homeUrl());
    exit;
}

if (currentRole() === 'admin') {
    header('Location: ' . homeUrl());
    exit;
}

$current = currentProfile();
?>
<!DOCTYPE html>
<html lang="<?= htmlLang() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= t('Escolha seu perfil') ?></title>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="stylesheet" href="<?= av('assets/app.css') ?>">
</head>
<body>
<?php renderLangSelector(); ?>
<main class="choice-page">
  <div class="choice-wrap">
    <span class="rail-mark choice-mark">CC</span>
    <p class="eyebrow"><?= $current === null ? t('Primeiro acesso') : t('Trocar de perfil') ?></p>
    <h1><?= t('Como você quer usar a plataforma?') ?></h1>
    <p class="muted choice-sub"><?= t('Você pode trocar de perfil a qualquer momento pelo menu lateral.') ?></p>

    <form method="post" class="choice-grid">
      <button type="submit" name="profile" value="coletor" class="choice-card<?= $current === 'coletor' ? ' is-current' : '' ?>">
        <?php if ($current === 'coletor'): ?><em class="choice-tag"><?= t('Perfil atual') ?></em><?php endif; ?>
        <span class="choice-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 3v2h6V3"/><path d="M9 11h6M9 15h4"/></svg>
        </span>
        <strong><?= t('Coletor') ?></strong>
        <span><?= t('Peça cadastros aos seus clientes. Gere um link, envie para quem quiser e acompanhe tudo o que receber.') ?></span>
      </button>
      <button type="submit" name="profile" value="emissor" class="choice-card choice-card--gold<?= $current === 'emissor' ? ' is-current' : '' ?>">
        <?php if ($current === 'emissor'): ?><em class="choice-tag"><?= t('Perfil atual') ?></em><?php endif; ?>
        <span class="choice-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/></svg>
        </span>
        <strong><?= t('Emissor') ?></strong>
        <span><?= t('Recebeu um link de cadastro? Preencha seus dados e envie para quem pediu.') ?></span>
      </button>
    </form>

    <p class="choice-foot">
      <?= t('Conectado como') ?> <strong><?= esc(currentUsername()) ?></strong>
      <?php if ($current !== null): ?> · <a href="<?= esc(homeUrl()) ?>"><?= t('Voltar') ?></a><?php endif; ?>
      · <a href="logout.php"><?= t('Sair') ?></a>
    </p>
  </div>
</main>
<script src="<?= av('assets/heartbeat.js') ?>"></script>
</body>
</html>
