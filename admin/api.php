<?php
declare(strict_types=1);
require_once __DIR__ . '/../coletas_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function param(string $name): string
{
    $v = $_POST[$name] ?? ($_GET[$name] ?? '');
    return is_string($v) ? trim($v) : '';
}

function settingGet(string $key): ?string
{
    $st = getDB()->prepare('SELECT v FROM app_settings WHERE k = ?');
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? null : (string) $v;
}

function settingSet(string $key, string $value): void
{
    getDB()->prepare('INSERT INTO app_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)')->execute([$key, $value]);
}

function cadastroResumo(array $d): array
{
    $pf = ($d['type_pessoa'] ?? 'pj') === 'pf';
    $emp = is_array($d['empresa'] ?? null) ? $d['empresa'] : [];
    $resp = is_array($d['responsavel'] ?? null) ? $d['responsavel'] : [];
    $end = is_array($d['endereco'] ?? null) ? $d['endereco'] : [];
    $nome = (string) ($pf ? ($resp['nomeCompleto'] ?? '') : ($emp['razaoSocial'] ?? ''));
    return [
        'tipo' => $pf ? 'pf' : 'pj',
        'nome' => $nome !== '' ? $nome : '—',
        'documento' => onlyDigits((string) ($pf ? ($resp['cpf'] ?? '') : ($emp['cnpj'] ?? ''))),
        'local' => trim((string) ($end['cidade'] ?? '') . (empty($end['estado']) ? '' : ' / ' . $end['estado'])),
    ];
}

function senhaResultado(array $user, string $password, bool $enviar, bool $nova): array
{
    $emailed = false;
    if ($enviar && filter_var((string) ($user['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
        $emailed = sendSenhaEmail((string) $user['email'], (string) $user['username'], $password, $nova);
    }
    return ['ok' => true, 'username' => $user['username'], 'password' => $password, 'emailed' => $emailed];
}

touchSession();
if (currentRole() !== 'admin') {
    out(401, ['ok' => false, 'error' => 'Sessão expirada. Entre novamente.']);
}
$action = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPost && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
    out(403, ['ok' => false, 'error' => 'Requisição inválida.']);
}
$db = getDB();
$me = (int) currentUserId();

try {
    if ($action === 'cadastros') {
        $rows = $db->query(
            'SELECT s.id, s.coleta_id, s.data_json, UNIX_TIMESTAMP(s.created_at) AS created, UNIX_TIMESTAMP(s.updated_at) AS updated,
                    e.username AS emissor, co.username AS coletor
               FROM submissions s
          LEFT JOIN users e ON e.id = s.user_id
          LEFT JOIN coletas c ON c.id = s.coleta_id
          LEFT JOIN users co ON co.id = c.coletor_id
           ORDER BY s.updated_at DESC
              LIMIT 2000'
        )->fetchAll();
        $items = [];
        foreach ($rows as $r) {
            $items[] = cadastroResumo(json_decode((string) $r['data_json'], true) ?: []) + [
                'id' => $r['id'],
                'coleta_id' => $r['coleta_id'] === null ? null : (int) $r['coleta_id'],
                'created' => (int) $r['created'],
                'updated' => (int) $r['updated'],
                'emissor' => $r['coleta_id'] === null ? null : $r['emissor'],
                'coletor' => $r['coletor'],
            ];
        }
        out(200, ['ok' => true, 'items' => $items]);
    }

    if ($action === 'cadastro') {
        $st = $db->prepare('SELECT id, coleta_id, data_json FROM submissions WHERE id = ?');
        $st->execute([param('id')]);
        $r = $st->fetch();
        if (!$r) {
            out(404, ['ok' => false, 'error' => 'Cadastro não encontrado.']);
        }
        $eventos = [];
        if ($r['coleta_id'] !== null) {
            $st = $db->prepare('SELECT ev.evento, UNIX_TIMESTAMP(ev.created_at) AS at, u.username FROM coleta_eventos ev LEFT JOIN users u ON u.id = ev.user_id WHERE ev.coleta_id = ? ORDER BY ev.id');
            $st->execute([(int) $r['coleta_id']]);
            foreach ($st->fetchAll() as $ev) {
                $eventos[] = ['evento' => $ev['evento'], 'at' => (int) $ev['at'], 'por' => $ev['username']];
            }
        }
        out(200, ['ok' => true, 'id' => $r['id'], 'data' => json_decode((string) $r['data_json'], true) ?: new stdClass(), 'eventos' => $eventos]);
    }

    if ($action === 'excluir_cadastro' && $isPost) {
        $id = (string) preg_replace('/[^A-Za-z0-9_\-]/', '', param('id'));
        $st = $db->prepare('SELECT coleta_id FROM submissions WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        if ($id === '' || !$r) {
            out(404, ['ok' => false, 'error' => 'Cadastro não encontrado.']);
        }
        $db->prepare('DELETE FROM submissions WHERE id = ?')->execute([$id]);
        foreach (glob(UPLOADS_DIR . '/' . $id . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if ($r['coleta_id'] !== null) {
            $db->prepare("UPDATE coletas SET status = 'aberto' WHERE id = ? AND status = 'recebido'")->execute([(int) $r['coleta_id']]);
            logColetaEvento((int) $r['coleta_id'], $me, 'excluido_admin');
        }
        out(200, ['ok' => true]);
    }

    if ($action === 'contas') {
        $rows = $db->query(
            'SELECT u.id, u.username, u.email, u.role, u.profile, u.active,
                    UNIX_TIMESTAMP(u.created_at) AS created, UNIX_TIMESTAMP(u.last_seen_at) AS seen,
                    (SELECT COUNT(*) FROM coletas c WHERE c.coletor_id = u.id) AS pedidos,
                    (SELECT COUNT(*) FROM submissions s WHERE s.user_id = u.id AND s.coleta_id IS NOT NULL) AS cadastros
               FROM users u
           ORDER BY u.created_at DESC, u.id DESC'
        )->fetchAll();
        foreach ($rows as &$r) {
            foreach (['id', 'active', 'created', 'pedidos', 'cadastros'] as $k) {
                $r[$k] = (int) $r[$k];
            }
            $r['seen'] = $r['seen'] === null ? null : (int) $r['seen'];
            $r['me'] = $r['id'] === $me;
        }
        unset($r);
        out(200, ['ok' => true, 'items' => $rows]);
    }

    if ($action === 'conta_status' && $isPost) {
        $id = (int) param('id');
        $active = param('active') === '1' ? 1 : 0;
        if ($id === $me) {
            out(400, ['ok' => false, 'error' => 'Você não pode bloquear a própria conta.']);
        }
        $st = $db->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'seller'");
        $st->execute([$id]);
        if ((int) $st->fetchColumn() === 0) {
            out(404, ['ok' => false, 'error' => 'Conta não encontrada.']);
        }
        $db->prepare('UPDATE users SET active = ?, last_seen_at = IF(? = 0, NULL, last_seen_at) WHERE id = ?')->execute([$active, $active, $id]);
        out(200, ['ok' => true]);
    }

    if ($action === 'conta_reset' && $isPost) {
        $st = $db->prepare("SELECT id, username FROM users WHERE id = ? AND role = 'seller'");
        $st->execute([(int) param('id')]);
        $user = $st->fetch();
        if (!$user) {
            out(404, ['ok' => false, 'error' => 'Conta não encontrada.']);
        }
        $password = '123456';
        $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_BCRYPT), (int) $user['id']]);
        out(200, ['ok' => true, 'username' => $user['username'], 'password' => $password, 'emailed' => false]);
    }

    if ($action === 'conta_editar' && $isPost) {
        $id = (int) param('id');
        $username = param('username');
        $email = strtolower(param('email'));
        $documento = onlyDigits(param('documento'));
        $st = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'seller'");
        $st->execute([$id]);
        if (!$st->fetch()) {
            out(404, ['ok' => false, 'error' => 'Conta não encontrada.']);
        }
        if (!preg_match('/^[a-zA-Z0-9_.@-]{3,100}$/', $username)) {
            out(400, ['ok' => false, 'error' => 'Usuário inválido: de 3 a 100 caracteres (letras, números, . _ - @).']);
        }
        if ($email !== '' && (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            out(400, ['ok' => false, 'error' => 'E-mail inválido.']);
        }
        if ($documento !== '' && !validDocAuto($documento)) {
            out(400, ['ok' => false, 'error' => 'CPF ou CNPJ inválido.']);
        }
        try {
            $db->prepare('UPDATE users SET username = ?, email = ?, documento = ? WHERE id = ?')
                ->execute([$username, $email !== '' ? $email : null, $documento !== '' ? $documento : null, $id]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                out(400, ['ok' => false, 'error' => 'Usuário ou e-mail já cadastrado.']);
            }
            throw $e;
        }
        out(200, ['ok' => true]);
    }

    if ($action === 'conta_excluir' && $isPost) {
        $id = (int) param('id');
        if ($id === $me) {
            out(400, ['ok' => false, 'error' => 'Você não pode apagar a própria conta.']);
        }
        $st = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'seller'");
        $st->execute([$id]);
        if (!$st->fetch()) {
            out(404, ['ok' => false, 'error' => 'Conta não encontrada.']);
        }
        $st = $db->prepare('SELECT id FROM submissions WHERE user_id = ?');
        $st->execute([$id]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $subId) {
            foreach (glob(UPLOADS_DIR . '/' . $subId . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        out(200, ['ok' => true]);
    }

    if ($action === 'conta_criar' && $isPost) {
        $username = param('username');
        $email = strtolower(param('email'));
        $documento = onlyDigits(param('documento'));
        if (!preg_match('/^[a-zA-Z0-9_.@-]{3,100}$/', $username)) {
            out(400, ['ok' => false, 'error' => 'Usuário inválido: de 3 a 100 caracteres (letras, números, . _ - @).']);
        }
        if ($email !== '' && (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            out(400, ['ok' => false, 'error' => 'E-mail inválido.']);
        }
        if ($documento !== '' && !validDocAuto($documento)) {
            out(400, ['ok' => false, 'error' => 'CPF ou CNPJ inválido.']);
        }
        $password = randomPassword();
        $db->prepare("INSERT INTO users (username, email, documento, password_hash, role, active) VALUES (?, ?, ?, ?, 'seller', 1)")
            ->execute([$username, $email !== '' ? $email : null, $documento !== '' ? $documento : null, password_hash($password, PASSWORD_BCRYPT)]);
        out(200, senhaResultado(['username' => $username, 'email' => $email], $password, param('enviar') === '1', true));
    }

    if ($action === 'metricas') {
        $dias = ['1' => 1, '7' => 7, '30' => 30][param('periodo')] ?? null;
        $reset = (int) (settingGet('metrics_reset_at') ?? 0);
        $since = $dias === null ? $reset : max($reset, time() - $dias * 86400);
        $q = function (string $sql, array $p = []) use ($db): PDOStatement {
            $st = $db->prepare($sql);
            $st->execute($p);
            return $st;
        };
        $num = function (string $sql, array $p = []) use ($q): int {
            return (int) $q($sql, $p)->fetchColumn();
        };
        $cards = [
            'logins' => $num("SELECT COUNT(*) FROM access_log WHERE event IN ('login', 'login_link') AND created_at >= FROM_UNIXTIME(?)", [$since]),
            'usuarios' => $num("SELECT COUNT(DISTINCT user_id) FROM access_log WHERE event IN ('login', 'login_link') AND created_at >= FROM_UNIXTIME(?)", [$since]),
            'ips' => $num('SELECT COUNT(DISTINCT ip) FROM access_log WHERE created_at >= FROM_UNIXTIME(?)', [$since]),
            'falhas' => $num("SELECT COUNT(*) FROM access_log WHERE event = 'login_failed' AND created_at >= FROM_UNIXTIME(?)", [$since]),
            'contas' => $num('SELECT COUNT(*) FROM users WHERE created_at >= FROM_UNIXTIME(?)', [$since]),
            'pedidos' => $num('SELECT COUNT(*) FROM coletas WHERE created_at >= FROM_UNIXTIME(?)', [$since]),
            'cadastros' => $num("SELECT COUNT(*) FROM coleta_eventos WHERE evento = 'enviado' AND created_at >= FROM_UNIXTIME(?)", [$since]),
            'online' => $num('SELECT COUNT(*) FROM users WHERE active = 1 AND last_seen_at >= NOW() - INTERVAL ? SECOND', [ONLINE_WINDOW_SECONDS]),
        ];

        $serieSince = max($since, time() - 30 * 86400);
        $porDia = [];
        foreach ($q("SELECT DATE(created_at) AS d, SUM(event IN ('login', 'login_link')) AS logins, COUNT(DISTINCT ip) AS ips FROM access_log WHERE created_at >= FROM_UNIXTIME(?) GROUP BY d", [$serieSince])->fetchAll() as $r) {
            $porDia[$r['d']] = ['logins' => (int) $r['logins'], 'ips' => (int) $r['ips']];
        }
        [$ini, $hoje] = $q('SELECT DATE(FROM_UNIXTIME(?)), CURDATE()', [$serieSince])->fetch(PDO::FETCH_NUM);
        $serie = [];
        for ($d = new DateTime($ini), $fim = new DateTime($hoje); $d <= $fim; $d->modify('+1 day')) {
            $k = $d->format('Y-m-d');
            $serie[] = ['dia' => $k] + ($porDia[$k] ?? ['logins' => 0, 'ips' => 0]);
        }

        $topIps = $q('SELECT ip, COUNT(*) AS acessos, COUNT(DISTINCT user_id) AS contas, UNIX_TIMESTAMP(MAX(created_at)) AS ultimo FROM access_log WHERE created_at >= FROM_UNIXTIME(?) GROUP BY ip ORDER BY acessos DESC LIMIT 10', [$since])->fetchAll();
        foreach ($topIps as &$r) {
            $r['acessos'] = (int) $r['acessos'];
            $r['contas'] = (int) $r['contas'];
            $r['ultimo'] = (int) $r['ultimo'];
        }
        unset($r);
        $ultimos = $q('SELECT username, event, ip, user_agent, UNIX_TIMESTAMP(created_at) AS at FROM access_log WHERE created_at >= FROM_UNIXTIME(?) ORDER BY id DESC LIMIT 300', [$since])->fetchAll();
        foreach ($ultimos as &$r) {
            $r['at'] = (int) $r['at'];
        }
        unset($r);

        out(200, ['ok' => true, 'since' => $since, 'reset_at' => $reset ?: null, 'cards' => $cards, 'serie' => $serie, 'top_ips' => $topIps, 'ultimos' => $ultimos]);
    }

    if ($action === 'zerar_metricas' && $isPost) {
        $db->exec('DELETE FROM access_log');
        settingSet('metrics_reset_at', (string) time());
        out(200, ['ok' => true]);
    }
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        out(400, ['ok' => false, 'error' => 'Usuário ou e-mail já cadastrado.']);
    }
    error_log('admin api: ' . $e->getMessage());
    out(500, ['ok' => false, 'error' => 'Erro ao processar. Tente de novo.']);
}

out(400, ['ok' => false, 'error' => 'Ação inválida.']);
