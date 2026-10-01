<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

const ONLINE_WINDOW_SECONDS = 300;
const TOUCH_INTERVAL_SECONDS = 60;
const PROFILES = ['coletor', 'emissor'];
const CONVITE_COOKIE = 'cc_convite';

function currentRole(): ?string
{
    return $_SESSION['role'] ?? null;
}

function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function currentUsername(): ?string
{
    return $_SESSION['username'] ?? null;
}

function currentProfile(): ?string
{
    return $_SESSION['profile'] ?? null;
}

function clientIp(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function logAccess(?int $userId, string $username, string $event): void
{
    try {
        $stmt = getDB()->prepare('INSERT INTO access_log (user_id, username, event, ip, user_agent) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $userId,
            substr($username, 0, 100),
            $event,
            clientIp(),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    } catch (Exception $e) {
        error_log('access_log: ' . $e->getMessage());
    }
}

function endSession(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Marca o usuario como ativo agora; encerra a sessao se a conta foi bloqueada. */
function touchSession(bool $force = false): void
{
    $id = currentUserId();
    if ($id === null) {
        return;
    }
    $now = time();
    if (!$force && isset($_SESSION['touched_at']) && $now - (int) $_SESSION['touched_at'] < TOUCH_INTERVAL_SECONDS) {
        return;
    }
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT active FROM users WHERE id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() !== 1) {
            endSession();
            return;
        }
        $db->prepare('UPDATE users SET last_seen_at = NOW(), last_ip = ? WHERE id = ?')->execute([clientIp(), $id]);
        $_SESSION['touched_at'] = $now;
    } catch (Exception $e) {
        error_log('touchSession: ' . $e->getMessage());
    }
}

function homeUrl(): string
{
    $role = currentRole();
    if ($role !== 'admin' && $role !== 'seller') {
        return 'login.php';
    }
    if (currentProfile() === 'coletor') {
        return 'coletor.php';
    }
    if (currentProfile() === 'emissor') {
        return 'emissor.php';
    }
    return $role === 'admin' ? 'admin.php' : 'perfil.php';
}

/** Depois do login: se a pessoa chegou por um link de pedido, volta para ele. */
function postLoginUrl(): string
{
    return currentRole() === 'seller' && pendingConviteToken() !== null ? 'convite.php' : homeUrl();
}

function requireRole(string $role): void
{
    touchSession();
    if (currentRole() !== $role) {
        header('Location: login.php');
        exit;
    }
}

function requireProfile(string $profile): void
{
    touchSession();
    if (!in_array(currentRole(), ['admin', 'seller'], true)) {
        header('Location: login.php');
        exit;
    }
    if (currentProfile() !== $profile) {
        header('Location: ' . homeUrl());
        exit;
    }
}

function setProfile(?string $profile): void
{
    getDB()->prepare('UPDATE users SET profile = ? WHERE id = ?')->execute([$profile, currentUserId()]);
    $_SESSION['profile'] = $profile;
}

/** Abre a sessao para o usuario (linha de users com id, username, role, profile). */
function loginUser(array $user, string $event): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = (string) $user['username'];
    $_SESSION['role'] = (string) $user['role'];
    $_SESSION['profile'] = $user['profile'] ?? null;
    unset($_SESSION['touched_at']);
    touchSession(true);
    logAccess((int) $user['id'], (string) $user['username'], $event);
}

/** Tenta fazer login por usuario ou e-mail. Retorna o papel (admin/seller) ou null. */
function attemptLogin(string $login, string $password): ?string
{
    try {
        $stmt = getDB()->prepare('SELECT id, username, password_hash, role, profile, active FROM users WHERE username = ? OR email = ? ORDER BY (username = ?) DESC LIMIT 1');
        $stmt->execute([$login, $login, $login]);
        $user = $stmt->fetch();

        if (!$user || (int) $user['active'] !== 1 || !password_verify($password, $user['password_hash'])) {
            logAccess($user ? (int) $user['id'] : null, $login, 'login_failed');
            return null;
        }

        loginUser($user, 'login');
        return $user['role'];
    } catch (Exception $e) {
        error_log('Login error: ' . $e->getMessage());
        return null;
    }
}

/** Guarda o token do link do pedido (sessao + cookie) para sobreviver ao cadastro/login. */
function rememberConvite(string $token): void
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return;
    }
    $_SESSION['convite_token'] = $token;
    unset($_SESSION['convite_sent_to'], $_SESSION['convite_last_send']);
    setcookie(CONVITE_COOKIE, $token, ['expires' => time() + 7 * 86400, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
}

function pendingConviteToken(): ?string
{
    $t = $_SESSION['convite_token'] ?? ($_COOKIE[CONVITE_COOKIE] ?? null);
    return is_string($t) && preg_match('/^[a-f0-9]{32}$/', $t) ? $t : null;
}

function forgetConvite(): void
{
    unset($_SESSION['convite_token'], $_SESSION['convite_sent_to'], $_SESSION['convite_last_send']);
    if (isset($_COOKIE[CONVITE_COOKIE])) {
        setcookie(CONVITE_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
        unset($_COOKIE[CONVITE_COOKIE]);
    }
}

function doLogout(): void
{
    $id = currentUserId();
    if ($id !== null) {
        try {
            getDB()->prepare('UPDATE users SET last_seen_at = NULL WHERE id = ?')->execute([$id]);
        } catch (Exception $e) {
            error_log('logout: ' . $e->getMessage());
        }
        logAccess($id, (string) currentUsername(), 'logout');
    }
    endSession();
    header('Location: login.php');
    exit;
}
