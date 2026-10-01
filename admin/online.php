<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

touchSession();
if (currentRole() !== 'admin') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Não autorizado']);
    exit;
}

try {
    $stmt = getDB()->prepare(
        "SELECT u.id, u.username, u.email, u.role, u.profile, u.last_ip,
                TIMESTAMPDIFF(SECOND, u.last_seen_at, NOW()) AS idle_seconds,
                (SELECT TIMESTAMPDIFF(SECOND, MAX(l.created_at), NOW())
                   FROM access_log l
                  WHERE l.user_id = u.id AND l.event IN ('login', 'login_link')) AS login_seconds_ago
           FROM users u
          WHERE u.active = 1 AND u.last_seen_at >= NOW() - INTERVAL ? SECOND
          ORDER BY u.last_seen_at DESC"
    );
    $stmt->execute([ONLINE_WINDOW_SECONDS]);
    $users = $stmt->fetchAll();
    $me = currentUserId();
    foreach ($users as &$u) {
        $u['id'] = (int) $u['id'];
        $u['idle_seconds'] = max(0, (int) $u['idle_seconds']);
        $u['login_seconds_ago'] = $u['login_seconds_ago'] === null ? null : (int) $u['login_seconds_ago'];
        $u['is_me'] = $u['id'] === $me;
    }
    unset($u);
    echo json_encode(['ok' => true, 'window' => ONLINE_WINDOW_SECONDS, 'users' => $users]);
} catch (Exception $e) {
    error_log('online: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erro ao consultar usuários online']);
}
