<?php
declare(strict_types=1);
require_once __DIR__ . '/coletas_lib.php';

$raw = is_string($_GET['k'] ?? null) ? $_GET['k'] : '';
$row = null;
if (preg_match('/^[a-f0-9]{64}$/', $raw)) {
    $st = getDB()->prepare(
        'SELECT lt.id, lt.user_id, lt.coleta_id, lt.coleta_token_hash, u.username, u.email, u.role, u.profile, u.active,
                c.nome, c.type_pessoa, cu.username AS coletor_nome
           FROM login_tokens lt
           JOIN users u ON u.id = lt.user_id
      LEFT JOIN coletas c ON c.id = lt.coleta_id
      LEFT JOIN users cu ON cu.id = c.coletor_id
          WHERE lt.token_hash = ? AND lt.used_at IS NULL AND lt.expires_at > NOW()'
    );
    $st->execute([hash('sha256', $raw)]);
    $row = $st->fetch() ?: null;
    if ($row !== null && ($row['role'] !== 'seller' || (int) $row['active'] !== 1)) {
        $row = null;
    }
}

// O login so acontece no clique (POST): leitores de e-mail que abrem links sozinhos nao gastam o acesso.
if ($row !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = getDB()->prepare('UPDATE login_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
    $st->execute([(int) $row['id']]);
    if ($st->rowCount() === 1) {
        $userId = (int) $row['user_id'];
        if (currentUserId() !== $userId) {
            $lang = $_SESSION['lang'] ?? null;
            $_SESSION = $lang === null ? [] : ['lang' => $lang];
        }
        loginUser(['id' => $userId, 'username' => $row['username'], 'role' => $row['role'], 'profile' => $row['profile']], 'login_link');
        if ($row['coleta_id'] !== null) {
            $coletaId = (int) $row['coleta_id'];
            if (claimColeta($coletaId, (string) $row['coleta_token_hash'], $userId) || coletaBelongsTo($coletaId, $userId)) {
                openColetaAsEmissor($coletaId);
            }
            $_SESSION['flash'] = 'Você entrou, mas este pedido recebeu um link novo. Peça o link atualizado a quem te enviou.';
        }
        header('Location: ' . homeUrl());
        exit;
    }
    $row = null;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlLang() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="referrer" content="no-referrer">
<title><?= t('Entrar') ?></title>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="stylesheet" href="<?= av('assets/app.css') ?>">
</head>
<body>
<section class="view">
  <?php renderLangSelector(); ?>
  <div class="login-shell">
    <div class="login-hero">
      <div class="seal">CC</div>
      <?php if ($row !== null && $row['nome'] !== null): ?>
        <p class="eyebrow"><?= t('Pedido de cadastro') ?></p>
        <h1><?= t('Tudo pronto<br>para você entrar.') ?></h1>
        <p class="lede"><?= t('Pedido de {coletor} para {nome}.', ['coletor' => '<strong>' . esc($row['coletor_nome']) . '</strong>', 'nome' => '<strong>' . esc($row['nome']) . '</strong>']) ?></p>
      <?php else: ?>
        <p class="eyebrow"><?= t('Coleta de Dados') ?></p>
        <h1><?= t('Cadastro simples,<br>direto para quem pediu.') ?></h1>
      <?php endif; ?>
    </div>
    <div class="login-panel">
      <div class="login-card">
        <?php if ($row !== null): ?>
          <p class="eyebrow"><?= t('Acesso') ?></p>
          <h2><?= t('Entrar e preencher') ?></h2>
          <p class="subtitle"><?= t('Você vai entrar como {email}.', ['email' => '<strong>' . esc($row['email'] ?: $row['username']) . '</strong>']) ?></p>
          <form method="post">
            <button type="submit" class="primary"><?= t('Entrar e preencher') ?></button>
          </form>
        <?php else: ?>
          <p class="eyebrow"><?= t('Link expirado') ?></p>
          <h2><?= t('Este acesso não vale mais') ?></h2>
          <p class="subtitle"><?= t('O botão do e-mail vale por 1 hora e funciona uma única vez. Abra de novo o link do pedido para receber outro e-mail, ou entre com usuário e senha.') ?></p>
          <a class="button primary" href="login.php"><?= t('Entrar com senha') ?></a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
</body>
</html>
