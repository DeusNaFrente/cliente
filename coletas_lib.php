<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/mailer.php';

const CONVITE_EMAIL_LIMIT_PER_HOUR = 5;

function onlyDigits(string $s): string
{
    return (string) preg_replace('/\D+/', '', $s);
}

function validCpf(string $cpf): bool
{
    if (!preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $sum = 0;
        for ($i = 0; $i < $t; $i++) {
            $sum += (int) $cpf[$i] * ($t + 1 - $i);
        }
        if ((int) $cpf[$t] !== ((10 * $sum) % 11) % 10) {
            return false;
        }
    }
    return true;
}

function validCnpj(string $cnpj): bool
{
    if (!preg_match('/^\d{14}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
        return false;
    }
    $weights = [[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]];
    foreach ($weights as $k => $w) {
        $sum = 0;
        foreach ($w as $i => $m) {
            $sum += (int) $cnpj[$i] * $m;
        }
        $r = $sum % 11;
        if ((int) $cnpj[12 + $k] !== ($r < 2 ? 0 : 11 - $r)) {
            return false;
        }
    }
    return true;
}

function validDocAuto(string $doc): bool
{
    $doc = onlyDigits($doc);
    if (strlen($doc) === 11) {
        return validCpf($doc);
    }
    if (strlen($doc) === 14) {
        return validCnpj($doc);
    }
    return false;
}

function formatDoc(string $doc): string
{
    if (strlen($doc) === 11) {
        return (string) preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $doc);
    }
    if (strlen($doc) === 14) {
        return (string) preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $doc);
    }
    return $doc;
}

function newToken(int $bytes = 16): array
{
    $raw = bin2hex(random_bytes($bytes));
    return [$raw, hash('sha256', $raw)];
}

function randomPassword(int $length = 10): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

function appUrl(string $path): string
{
    $base = rtrim((string) (getenv('APP_URL') ?: 'https://cliente.j2tec.com.br'), '/');
    return $base . '/' . ltrim($path, '/');
}

function logColetaEvento(int $coletaId, ?int $userId, string $evento): void
{
    getDB()->prepare('INSERT INTO coleta_eventos (coleta_id, user_id, evento) VALUES (?, ?, ?)')->execute([$coletaId, $userId, $evento]);
}

/** Gera um novo link para a coleta; o link anterior deixa de funcionar. */
function renewColetaToken(int $coletaId): string
{
    [$raw, $hash] = newToken();
    getDB()->prepare("UPDATE coletas SET token_hash = ?, token_created_at = NOW(), status = IF(status = 'recebido', status, 'aguardando') WHERE id = ?")
        ->execute([$hash, $coletaId]);
    return appUrl('convite.php?t=' . $raw);
}

function coletaMessage(array $coleta, string $coletorNome, string $link): string
{
    $intro = $coleta['type_pessoa'] === 'pf'
        ? t('Olá, {nome}!', ['nome' => $coleta['nome']]) . "\n\n" . t('{coletor} solicitou seu cadastro na plataforma Coleta de Dados.', ['coletor' => $coletorNome])
        : t('Olá!') . "\n\n" . t('{coletor} solicitou o cadastro da empresa {nome} na plataforma Coleta de Dados.', ['coletor' => $coletorNome, 'nome' => $coleta['nome']]);
    return $intro . "\n\n" . t('Acesse o link abaixo para entrar e preencher os dados. O link é pessoal e vale para um único acesso:') . "\n" . $link;
}

function findColetaByToken(string $raw): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $raw)) {
        return null;
    }
    $st = getDB()->prepare('SELECT c.*, u.username AS coletor_nome FROM coletas c JOIN users u ON u.id = c.coletor_id WHERE c.token_hash = ?');
    $st->execute([hash('sha256', $raw)]);
    $row = $st->fetch();
    return $row ?: null;
}

/** Vincula a coleta ao emissor e consome o link (uso unico). */
function claimColeta(int $coletaId, string $tokenHash, int $userId): bool
{
    $st = getDB()->prepare("UPDATE coletas SET emissor_id = ?, token_hash = NULL, status = IF(status = 'recebido', status, 'aberto') WHERE id = ? AND token_hash = ? AND coletor_id <> ?");
    $st->execute([$userId, $coletaId, $tokenHash, $userId]);
    if ($st->rowCount() !== 1) {
        return false;
    }
    logColetaEvento($coletaId, $userId, 'aberto');
    return true;
}

function coletaBelongsTo(int $coletaId, int $userId): bool
{
    $st = getDB()->prepare('SELECT 1 FROM coletas WHERE id = ? AND emissor_id = ?');
    $st->execute([$coletaId, $userId]);
    return (bool) $st->fetchColumn();
}

function openColetaAsEmissor(int $coletaId): void
{
    forgetConvite();
    setProfile('emissor');
    header('Location: cadastro.php?coleta=' . $coletaId);
    exit;
}

/** Cria (se preciso) a conta do emissor e envia o link magico. Retorna [estado, erro]. */
function requestMagicLink(array $coleta, string $raw, string $email): array
{
    $email = strtolower(trim($email));
    if (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['form', t('Informe um e-mail válido.')];
    }

    $db = getDB();
    $coletaId = (int) $coleta['id'];
    $st = $db->prepare('SELECT id, username, role, active FROM users WHERE email = ? OR username = ? ORDER BY (email = ?) DESC LIMIT 1');
    $st->execute([$email, $email, $email]);
    $user = $st->fetch();
    if ($user && ($user['role'] !== 'seller' || (int) $user['active'] !== 1 || (int) $user['id'] === (int) $coleta['coletor_id'])) {
        return ['form', t('Não é possível usar este e-mail. Informe outro.')];
    }
    if ($user) {
        logColetaEvento($coletaId, (int) $user['id'], 'redirecionado_login');
        return ['existing', (string) $user['username']];
    }

    if (time() - (int) ($_SESSION['convite_last_send'] ?? 0) < 60) {
        return ['form', t('Aguarde um minuto antes de pedir outro e-mail.')];
    }
    $st = $db->prepare('SELECT COUNT(*) FROM login_tokens WHERE coleta_id = ? AND created_at > NOW() - INTERVAL 1 HOUR');
    $st->execute([$coletaId]);
    if ((int) $st->fetchColumn() >= CONVITE_EMAIL_LIMIT_PER_HOUR) {
        return ['form', t('Muitos e-mails enviados para este pedido. Tente de novo mais tarde.')];
    }

    $db->beginTransaction();
    try {
        $password = randomPassword();
        $db->prepare("INSERT INTO users (username, email, password_hash, role, profile, active) VALUES (?, ?, ?, 'seller', 'emissor', 1)")
            ->execute([$email, $email, password_hash($password, PASSWORD_BCRYPT)]);
        $userId = (int) $db->lastInsertId();
        $username = $email;
        [$magic, $magicHash] = newToken(32);
        $db->prepare('INSERT INTO login_tokens (user_id, token_hash, coleta_id, coleta_token_hash, expires_at) VALUES (?, ?, ?, ?, NOW() + INTERVAL 1 HOUR)')
            ->execute([$userId, $magicHash, $coletaId, hash('sha256', $raw)]);
        if (!sendConviteEmail($email, $coleta, appUrl('magico.php?k=' . $magic), $username, $password)) {
            $db->rollBack();
            return ['form', t('Não conseguimos enviar o e-mail agora. Tente de novo em instantes.')];
        }
        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('requestMagicLink: ' . $e->getMessage());
        return ['form', t('Não foi possível concluir agora. Tente de novo em instantes.')];
    }

    $_SESSION['convite_last_send'] = time();
    $_SESSION['convite_sent_to'] = $email;
    logColetaEvento($coletaId, $userId, 'email_enviado');
    return ['sent', ''];
}

function sendConviteEmail(string $to, array $coleta, string $link, string $username, ?string $password): bool
{
    $coletor = (string) $coleta['coletor_nome'];
    $isPf = $coleta['type_pessoa'] === 'pf';
    $saudacao = $isPf ? t('Olá, {nome}!', ['nome' => $coleta['nome']]) : t('Olá!');
    $pedido = $isPf ? t('{coletor} pediu seu cadastro.', ['coletor' => $coletor]) : t('{coletor} pediu o cadastro da empresa {nome}.', ['coletor' => $coletor, 'nome' => $coleta['nome']]);
    $subject = $isPf ? t('{coletor} pediu seu cadastro', ['coletor' => $coletor]) : t('{coletor} pediu o cadastro da sua empresa', ['coletor' => $coletor]);
    $automatico = t('Este é um e-mail automático. Não é preciso responder.');

    $acessoHtml = '';
    $acessoTxt = '';
    if ($password !== null) {
        $nota = t('Você pode trocar a senha em "Mudar senha", dentro da plataforma.');
        $acessoHtml = '<tr><td style="padding:0 32px 24px"><table width="100%" cellpadding="0" cellspacing="0" style="background:#f1efe6;border-radius:12px"><tr><td style="padding:18px 20px;font-size:14px;color:#16211c;line-height:1.7">'
            . '<strong>' . esc(t('Seu acesso para voltar depois')) . '</strong><br>' . esc(t('Usuário')) . ': <span style="font-family:monospace">' . esc($username) . '</span><br>'
            . esc(t('Senha provisória')) . ': <span style="font-family:monospace">' . esc($password) . '</span><br>'
            . '<span style="color:#5b6b5f;font-size:13px">' . esc($nota) . '</span></td></tr></table></td></tr>';
        $acessoTxt = "\n\n" . t('Seu acesso para voltar depois') . ":\n" . t('Usuário') . ': ' . $username . "\n" . t('Senha provisória') . ': ' . $password . "\n" . $nota;
    }

    $html = '<!DOCTYPE html><html><body style="margin:0;background:#eef1ec;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#eef1ec;padding:32px 12px"><tr><td align="center">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#fbfbf8;border:1px solid #dcded2;border-radius:16px">'
        . '<tr><td style="padding:28px 32px 4px"><div style="font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#a9793f">Coleta de Dados</div>'
        . '<h1 style="font-family:Georgia,serif;font-size:24px;font-weight:600;color:#16211c;margin:10px 0 0">' . esc($saudacao) . '</h1></td></tr>'
        . '<tr><td style="padding:12px 32px 24px;font-size:15px;line-height:1.6;color:#16211c">' . esc($pedido) . ' ' . esc(t('Clique no botão para entrar e preencher os dados.')) . '</td></tr>'
        . '<tr><td align="center" style="padding:0 32px 24px"><a href="' . esc($link) . '" style="display:inline-block;background:#1f6f54;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:14px 30px;border-radius:10px">' . esc(t('Entrar e preencher')) . '</a>'
        . '<div style="font-size:12px;color:#5b6b5f;margin-top:12px">' . esc(t('O botão vale por 1 hora e funciona uma única vez.')) . '</div></td></tr>'
        . $acessoHtml
        . '<tr><td style="padding:18px 32px 28px;font-size:12px;color:#5b6b5f;line-height:1.5;border-top:1px solid #dcded2">' . esc(t('Se o botão não funcionar, copie este endereço no navegador:')) . '<br><span style="word-break:break-all;color:#1f6f54">' . esc($link) . '</span><br><br>' . esc($automatico) . '</td></tr>'
        . '</table></td></tr></table></body></html>';

    $text = $saudacao . "\n\n" . $pedido . ' ' . t('Acesse o link abaixo para entrar e preencher os dados (vale por 1 hora e funciona uma única vez):') . "\n" . $link . $acessoTxt . "\n\n" . $automatico;

    return sendMail($to, $subject, $html, $text);
}

/** Acesso ao cadastro da coleta: emissor vinculado ou coletor dono. Retorna ['coleta' => linha, 'mode' => 'emissor'|'coletor'] ou null. */
function coletaAccess(int $coletaId): ?array
{
    $me = currentUserId();
    if ($coletaId <= 0 || $me === null || !in_array(currentRole(), ['admin', 'seller'], true)) {
        return null;
    }
    $st = getDB()->prepare('SELECT c.*, u.username AS coletor_nome, s.id AS submission_id, s.data_json FROM coletas c JOIN users u ON u.id = c.coletor_id LEFT JOIN submissions s ON s.coleta_id = c.id WHERE c.id = ?');
    $st->execute([$coletaId]);
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    if (currentProfile() === 'emissor' && (int) $row['emissor_id'] === $me) {
        return ['coleta' => $row, 'mode' => 'emissor'];
    }
    if (currentProfile() === 'coletor' && (int) $row['coletor_id'] === $me) {
        return ['coleta' => $row, 'mode' => 'coletor'];
    }
    return null;
}

function emailHtml(string $titulo, string $texto, string $botao, string $link, string $extra = ''): string
{
    return '<!DOCTYPE html><html><body style="margin:0;background:#eef1ec;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#eef1ec;padding:32px 12px"><tr><td align="center">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#fbfbf8;border:1px solid #dcded2;border-radius:16px">'
        . '<tr><td style="padding:28px 32px 4px"><div style="font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#a9793f">Coleta de Dados</div>'
        . '<h1 style="font-family:Georgia,serif;font-size:22px;font-weight:600;color:#16211c;margin:10px 0 0">' . esc($titulo) . '</h1></td></tr>'
        . '<tr><td style="padding:12px 32px 24px;font-size:15px;line-height:1.6;color:#16211c">' . $texto . '</td></tr>'
        . $extra
        . '<tr><td align="center" style="padding:0 32px 28px"><a href="' . esc($link) . '" style="display:inline-block;background:#1f6f54;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:14px 30px;border-radius:10px">' . esc($botao) . '</a></td></tr>'
        . '<tr><td style="padding:18px 32px 28px;font-size:12px;color:#5b6b5f;line-height:1.5;border-top:1px solid #dcded2">Este é um e-mail automático. Não é preciso responder.</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Avisa o coletor que o cadastro do pedido chegou ou foi atualizado pelo cliente. */
function sendCadastroRecebidoEmail(int $coletorId, string $nome, bool $primeiro): void
{
    $st = getDB()->prepare('SELECT email FROM users WHERE id = ?');
    $st->execute([$coletorId]);
    $email = (string) $st->fetchColumn();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $titulo = $primeiro ? 'Cadastro recebido' : 'Cadastro atualizado';
    $frase = $primeiro ? 'enviou o cadastro que você pediu.' : 'atualizou o cadastro que tinha enviado.';
    $link = appUrl('login.php');
    $html = emailHtml($titulo, '<strong>' . esc($nome) . '</strong> ' . $frase . ' Entre na plataforma para conferir.', 'Ver cadastro', $link);
    $text = $titulo . "\n\n" . $nome . ' ' . $frase . "\nEntre na plataforma para conferir: " . $link . "\n\nEste é um e-mail automático. Não é preciso responder.";
    if (!sendMail($email, $titulo . ': ' . $nome, $html, $text)) {
        error_log('aviso de cadastro nao enviado ao coletor ' . $coletorId);
    }
}

/** Envia ao usuario a senha provisoria gerada pelo admin. */
function sendSenhaEmail(string $to, string $username, string $password, bool $contaNova): bool
{
    $titulo = $contaNova ? 'Sua conta foi criada' : 'Sua senha foi redefinida';
    $link = appUrl('login.php');
    $extra = '<tr><td style="padding:0 32px 24px"><table width="100%" cellpadding="0" cellspacing="0" style="background:#f1efe6;border-radius:12px"><tr><td style="padding:18px 20px;font-size:14px;color:#16211c;line-height:1.7">'
        . 'Usuário: <span style="font-family:monospace">' . esc($username) . '</span><br>Senha provisória: <span style="font-family:monospace">' . esc($password) . '</span><br>'
        . '<span style="color:#5b6b5f;font-size:13px">Troque a senha em "Mudar senha" depois de entrar.</span></td></tr></table></td></tr>';
    $html = emailHtml($titulo, 'Use os dados abaixo para entrar na plataforma Coleta de Dados.', 'Entrar', $link, $extra);
    $text = $titulo . "\n\nUsuário: " . $username . "\nSenha provisória: " . $password . "\nEntre em: " . $link . "\nTroque a senha em \"Mudar senha\" depois de entrar.";
    return sendMail($to, $titulo, $html, $text);
}
