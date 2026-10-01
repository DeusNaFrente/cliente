<?php
declare(strict_types=1);
require_once __DIR__ . '/coletas_lib.php';

if (isset($_GET['t'])) {
    rememberConvite(is_string($_GET['t']) ? $_GET['t'] : '');
    header('Location: convite.php');
    exit;
}
if (isset($_GET['outro'])) {
    unset($_SESSION['convite_sent_to']);
    header('Location: convite.php');
    exit;
}

touchSession();
$raw = pendingConviteToken();
$coleta = $raw !== null ? findColetaByToken($raw) : null;
$postedEmail = is_string($_POST['email'] ?? null) ? $_POST['email'] : '';
$state = 'form';
$error = '';
$coletaEmail = $coleta !== null ? (string) ($coleta['email'] ?? '') : '';

if ($coleta === null) {
    forgetConvite();
    $state = 'invalid';
} elseif (in_array(currentRole(), ['admin', 'seller'], true)) {
    $me = (int) currentUserId();
    if ((int) $coleta['coletor_id'] === $me) {
        forgetConvite();
        $state = 'own';
    } elseif (claimColeta((int) $coleta['id'], hash('sha256', (string) $raw), $me)) {
        openColetaAsEmissor((int) $coleta['id']);
    } else {
        forgetConvite();
        $state = 'invalid';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailParaUsar = $coletaEmail !== '' ? $coletaEmail : $postedEmail;
    [$state, $extra] = requestMagicLink($coleta, (string) $raw, $emailParaUsar);
    if ($state === 'sent') {
        header('Location: convite.php');
        exit;
    }
    if ($state === 'existing') {
        header('Location: login.php?usuario=' . rawurlencode($extra));
        exit;
    }
    $error = $extra;
} elseif ($coletaEmail !== '') {
    $st = getDB()->prepare('SELECT COUNT(*) FROM login_tokens WHERE coleta_id = ?');
    $st->execute([(int) $coleta['id']]);
    if ((int) $st->fetchColumn() > 0) {
        $state = 'sent';
        $_SESSION['convite_sent_to'] = $coletaEmail;
    } else {
        [$state, $extra] = requestMagicLink($coleta, (string) $raw, $coletaEmail);
        if ($state === 'existing') {
            header('Location: login.php?usuario=' . rawurlencode($extra));
            exit;
        }
        if ($state === 'form') {
            $error = $extra;
        }
    }
} elseif (!empty($_SESSION['convite_sent_to'])) {
    $state = 'sent';
}

$isPf = $coleta !== null && $coleta['type_pessoa'] === 'pf';
?>
<!DOCTYPE html>
<html lang="<?= htmlLang() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="referrer" content="no-referrer">
<title><?= t('Pedido de cadastro') ?></title>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="stylesheet" href="<?= av('assets/app.css') ?>">
</head>
<body>
<section class="view">
  <?php renderLangSelector(); ?>
  <div class="login-shell">
    <div class="login-hero">
      <div class="seal">CC</div>
      <?php if ($coleta !== null): ?>
        <p class="eyebrow"><?= t('Pedido de cadastro') ?></p>
        <h1><?= $isPf ? t('{nome} pediu seu cadastro.', ['nome' => esc($coleta['coletor_nome'])]) : t('{nome} pediu o cadastro da sua empresa.', ['nome' => esc($coleta['coletor_nome'])]) ?></h1>
        <p class="lede"><?= $isPf ? t('Para') : t('Empresa') ?>: <strong><?= esc($coleta['nome']) ?></strong><br><?= t('Leva poucos minutos. Seus dados chegam apenas a quem pediu.') ?></p>
      <?php else: ?>
        <p class="eyebrow"><?= t('Coleta de Dados') ?></p>
        <h1><?= t('Cadastro simples,<br>direto para quem pediu.') ?></h1>
      <?php endif; ?>
    </div>
    <div class="login-panel">
      <div class="login-card">
        <?php if ($state === 'form'): ?>
          <p class="eyebrow"><?= t('Acesso rápido') ?></p>
          <h2><?= t('Informe seu e-mail') ?></h2>
          <p class="subtitle"><?= t('Enviamos um botão de acesso para você entrar e preencher. Sem senha para lembrar.') ?></p>
          <form method="post" novalidate>
            <label><?= t('E-mail') ?>
              <input type="email" name="email" autocomplete="email" inputmode="email" placeholder="<?= esc(t('voce@email.com')) ?>" value="<?= esc($postedEmail) ?>" required autofocus>
            </label>
            <button type="submit" class="primary"><?= t('Receber link de acesso') ?></button>
            <?php if ($error): ?><p class="error"><?= esc($error) ?></p><?php endif; ?>
          </form>
          <p class="alt-link"><?= t('Já tem conta?') ?> <a href="login.php"><?= t('Entrar com senha') ?></a></p>
        <?php elseif ($state === 'sent'): ?>
          <p class="eyebrow"><?= t('Quase lá') ?></p>
          <h2><?= t('Confira seu e-mail') ?></h2>
          <div class="notice notice-ok"><?= t('Enviamos o link de acesso para {email}. Abra o e-mail e clique em {botao}.', ['email' => '<strong>' . esc($_SESSION['convite_sent_to']) . '</strong>', 'botao' => '<strong>' . esc(t('Entrar e preencher')) . '</strong>']) ?></div>
          <p class="subtitle"><?= t('Não chegou? Veja a caixa de spam. Dá para pedir de novo depois de 1 minuto.') ?></p>
          <form method="post">
            <input type="hidden" name="email" value="<?= esc($_SESSION['convite_sent_to']) ?>">
            <button type="submit"><?= t('Enviar de novo') ?></button>
          </form>
          <?php if ($coletaEmail === ''): ?><p class="alt-link"><a href="convite.php?outro=1"><?= t('Usar outro e-mail') ?></a></p><?php endif; ?>
        <?php elseif ($state === 'own'): ?>
          <p class="eyebrow"><?= t('Link gerado por você') ?></p>
          <h2><?= t('Este é o seu próprio pedido') ?></h2>
          <p class="subtitle"><?= t('Envie este link para {nome}. Ele continua valendo.', ['nome' => esc($coleta['nome'])]) ?></p>
          <a class="button primary" href="coletor.php"><?= t('Voltar aos pedidos') ?></a>
        <?php else: ?>
          <p class="eyebrow"><?= t('Link indisponível') ?></p>
          <h2><?= t('Este link não vale mais') ?></h2>
          <p class="subtitle"><?= t('Ele já foi usado ou foi trocado por um link novo. Peça a quem te enviou um link atualizado.') ?></p>
          <a class="button primary" href="<?= esc(currentRole() !== null ? homeUrl() : 'login.php') ?>"><?= currentRole() !== null ? t('Ir para a plataforma') : t('Entrar com senha') ?></a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
</body>
</html>
