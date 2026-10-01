<?php
declare(strict_types=1);
require_once __DIR__ . '/coletas_lib.php';

$showRegister = isset($_GET['register']);

touchSession();
if (currentRole() !== null) {
    header('Location: ' . postLoginUrl());
    exit;
}

$error = '';
$field = function (string $name): string {
    return is_string($_POST[$name] ?? null) ? $_POST[$name] : '';
};
$prefillUsuario = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? $field('usuario')
    : (is_string($_GET['usuario'] ?? null) ? $_GET['usuario'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$showRegister) {
    if (attemptLogin(trim($field('usuario')), $field('senha')) !== null) {
        header('Location: ' . postLoginUrl());
        exit;
    }
    $error = t('Usuário ou senha inválidos.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $showRegister) {
    $username = trim($field('username'));
    $email = strtolower(trim($field('email')));
    $documento = onlyDigits($field('documento'));
    $password = $field('password');

    if ($username === '' || $email === '' || $documento === '' || $password === '') {
        $error = t('Preencha todos os campos.');
    } elseif (!preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username)) {
        $error = t('O usuário deve ter de 3 a 50 caracteres: letras, números, - ou _.');
    } elseif (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('E-mail inválido.');
    } elseif (!validDocAuto($documento)) {
        $error = t('Informe um CPF ou CNPJ válido.');
    } elseif (strlen($password) < 6) {
        $error = t('A senha deve ter pelo menos 6 caracteres.');
    } elseif ($password !== $field('password_confirm')) {
        $error = t('As senhas não conferem.');
    } else {
        try {
            $db = getDB();
            $db->prepare("INSERT INTO users (username, email, documento, password_hash, role, active) VALUES (?, ?, ?, ?, 'seller', 1)")
                ->execute([$username, $email, $documento, password_hash($password, PASSWORD_BCRYPT)]);
            loginUser(['id' => (int) $db->lastInsertId(), 'username' => $username, 'role' => 'seller', 'profile' => null], 'login');
            header('Location: ' . postLoginUrl());
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = t('Usuário ou e-mail já cadastrado.');
            } else {
                error_log('registro: ' . $e->getMessage());
                $error = t('Não foi possível criar a conta agora. Tente de novo.');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlLang() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= t('Cadastro de Cliente') ?></title>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="stylesheet" href="<?= av('assets/app.css') ?>">
</head>
<body>
<section class="view">
  <?php renderLangSelector(); ?>
  <div class="login-shell">
    <div class="login-hero">
      <div class="seal">CC</div>
      <p class="eyebrow"><?= t('Portal de Cadastro') ?></p>
      <h1><?= t('Seu cadastro,<br>documentado com precisão.') ?></h1>
      <p class="lede"><?= t('Preencha uma única vez. Seus dados ficam organizados e prontos para consulta quando forem solicitados.') ?></p>
    </div>
    <div class="login-panel">
      <div class="login-card">
        <?php if (!$showRegister): ?>
          <p class="eyebrow"><?= t('Acesso') ?></p>
          <h2><?= t('Entrar') ?></h2>
          <p class="subtitle"><?= t('Informe suas credenciais para continuar.') ?></p>
          <form method="post" novalidate>
            <label><?= t('Usuário ou e-mail') ?>
              <input name="usuario" autocomplete="username" value="<?= esc(trim($prefillUsuario)) ?>" autofocus required>
            </label>
            <label><?= t('Senha') ?>
              <input name="senha" type="password" autocomplete="current-password" required>
            </label>
            <button type="submit" class="primary"><?= t('Entrar') ?></button>
            <?php if ($error): ?><p class="error"><?= esc($error) ?></p><?php endif; ?>
          </form>
          <p class="alt-link"><?= t('Não tem conta?') ?> <a href="login.php?register=1"><?= t('Criar conta') ?></a></p>
        <?php else: ?>
          <p class="eyebrow"><?= t('Nova conta') ?></p>
          <h2><?= t('Criar conta') ?></h2>
          <p class="subtitle"><?= t('Leva menos de um minuto.') ?></p>
          <form method="post" novalidate>
            <label><?= t('Usuário') ?>
              <input name="username" autocomplete="username" placeholder="<?= esc(t('seu_usuario')) ?>" value="<?= esc($field('username')) ?>" required>
            </label>
            <label><?= t('E-mail') ?>
              <input name="email" type="email" autocomplete="email" placeholder="<?= esc(t('voce@email.com')) ?>" value="<?= esc($field('email')) ?>" required>
            </label>
            <label><?= t('CPF ou CNPJ') ?>
              <input id="docRegInput" name="documento" class="mono" inputmode="numeric" autocomplete="off" placeholder="000.000.000-00" value="<?= esc($field('documento')) ?>" required>
            </label>
            <label><?= t('Senha') ?>
              <input name="password" type="password" autocomplete="new-password" minlength="6" required>
            </label>
            <label><?= t('Confirmar senha') ?>
              <input name="password_confirm" type="password" autocomplete="new-password" minlength="6" required>
            </label>
            <button type="submit" class="primary"><?= t('Criar conta') ?></button>
            <?php if ($error): ?><p class="error"><?= esc($error) ?></p><?php endif; ?>
          </form>
          <p class="alt-link"><?= t('Já tem conta?') ?> <a href="login.php"><?= t('Entrar') ?></a></p>
          <script src="<?= av('assets/mask.js') ?>"></script>
          <script>mascaraDocAuto(document.getElementById('docRegInput'));
document.getElementById('docRegInput').addEventListener('input', function () { mascaraDocAuto(this); });</script>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
</body>
</html>
