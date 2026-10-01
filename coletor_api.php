<?php
declare(strict_types=1);
require_once __DIR__ . '/coletas_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Pedidos do coletor com o historico de cada um. */
function coletaItems(int $coletorId, ?int $onlyId = null): array
{
    $db = getDB();
    $sql = 'SELECT c.id, c.type_pessoa, c.nome, c.documento, c.email, c.status,
                   UNIX_TIMESTAMP(c.created_at) AS created, UNIX_TIMESTAMP(c.updated_at) AS updated, e.username AS emissor
              FROM coletas c LEFT JOIN users e ON e.id = c.emissor_id
             WHERE c.coletor_id = ?';
    $params = [$coletorId];
    if ($onlyId !== null) {
        $sql .= ' AND c.id = ?';
        $params[] = $onlyId;
    }
    $st = $db->prepare($sql . ' ORDER BY c.created_at DESC, c.id DESC LIMIT 1000');
    $st->execute($params);
    $items = [];
    foreach ($st->fetchAll() as $r) {
        $r['id'] = (int) $r['id'];
        $r['created'] = (int) $r['created'];
        $r['updated'] = (int) $r['updated'];
        $r['eventos'] = [];
        $items[$r['id']] = $r;
    }
    if ($items) {
        $in = implode(',', array_fill(0, count($items), '?'));
        $st = $db->prepare("SELECT coleta_id, evento, UNIX_TIMESTAMP(created_at) AS at FROM coleta_eventos WHERE coleta_id IN ($in) ORDER BY id");
        $st->execute(array_keys($items));
        foreach ($st->fetchAll() as $ev) {
            $items[(int) $ev['coleta_id']]['eventos'][] = ['evento' => $ev['evento'], 'at' => (int) $ev['at']];
        }
    }
    return array_values($items);
}

touchSession();
if (!in_array(currentRole(), ['admin', 'seller'], true) || currentProfile() !== 'coletor') {
    respond(401, ['ok' => false, 'error' => t('Sessão expirada. Entre novamente.')]);
}
$me = (int) currentUserId();
$action = is_string($_GET['action'] ?? null) ? $_GET['action'] : 'list';
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPost && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
    respond(403, ['ok' => false, 'error' => t('Requisição inválida.')]);
}

try {
    if ($action === 'list') {
        respond(200, ['ok' => true, 'items' => coletaItems($me)]);
    }

    if ($action === 'create' && $isPost) {
        $type = (string) ($_POST['type_pessoa'] ?? '');
        $nome = trim((string) preg_replace('/\s+/u', ' ', (string) ($_POST['nome'] ?? '')));
        $doc = onlyDigits((string) ($_POST['documento'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if ($type !== 'pf' && $type !== 'pj') {
            respond(400, ['ok' => false, 'error' => t('Escolha Pessoa Física ou Jurídica.')]);
        }
        if (!preg_match('/^.{3,200}$/u', $nome)) {
            respond(400, ['ok' => false, 'error' => $type === 'pf' ? t('Informe o nome completo.') : t('Informe a razão social.')]);
        }
        if ($type === 'pf' ? !validCpf($doc) : !validCnpj($doc)) {
            respond(400, ['ok' => false, 'error' => $type === 'pf' ? t('CPF inválido.') : t('CNPJ inválido.')]);
        }
        if (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond(400, ['ok' => false, 'error' => t('Informe um e-mail válido de quem vai preencher o cadastro.')]);
        }
        $db = getDB();
        $meuEmail = (string) $db->query('SELECT email FROM users WHERE id = ' . $me)->fetchColumn();
        if ($meuEmail !== '' && strtolower($meuEmail) === $email) {
            respond(400, ['ok' => false, 'error' => t('Informe o e-mail de quem vai preencher, não o seu.')]);
        }
        $db->prepare('INSERT INTO coletas (coletor_id, type_pessoa, nome, documento, email) VALUES (?, ?, ?, ?, ?)')->execute([$me, $type, $nome, $doc, $email]);
        $id = (int) $db->lastInsertId();
        $link = renewColetaToken($id);
        logColetaEvento($id, $me, 'criado');
        $item = coletaItems($me, $id)[0];
        respond(200, ['ok' => true, 'item' => $item, 'link' => $link, 'message' => coletaMessage($item, (string) currentUsername(), $link)]);
    }

    if ($action === 'reenviar' && $isPost) {
        $id = (int) ($_POST['id'] ?? 0);
        if (!coletaItems($me, $id)) {
            respond(404, ['ok' => false, 'error' => t('Pedido não encontrado.')]);
        }
        $link = renewColetaToken($id);
        logColetaEvento($id, $me, 'reenviado');
        $item = coletaItems($me, $id)[0];
        respond(200, ['ok' => true, 'item' => $item, 'link' => $link, 'message' => coletaMessage($item, (string) currentUsername(), $link)]);
    }

    if ($action === 'excluir' && $isPost) {
        $id = (int) ($_POST['id'] ?? 0);
        if (!coletaItems($me, $id)) {
            respond(404, ['ok' => false, 'error' => t('Pedido não encontrado.')]);
        }
        $db = getDB();
        $st = $db->prepare('SELECT id FROM submissions WHERE coleta_id = ?');
        $st->execute([$id]);
        $subId = $st->fetchColumn();
        if ($subId) {
            foreach (glob(UPLOADS_DIR . '/' . $subId . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            $db->prepare('DELETE FROM submissions WHERE id = ?')->execute([$subId]);
        }
        $db->prepare('DELETE FROM coletas WHERE id = ? AND coletor_id = ?')->execute([$id, $me]);
        respond(200, ['ok' => true]);
    }

    if ($action === 'clientes') {
        if (currentRole() === 'admin') {
            $st = getDB()->query("SELECT username AS nome, documento, email FROM users WHERE role = 'seller' ORDER BY username");
            $out = $st->fetchAll();
            foreach ($out as &$r) {
                $len = strlen((string) $r['documento']);
                $r['type_pessoa'] = $len === 11 ? 'pf' : ($len === 14 ? 'pj' : null);
            }
            unset($r);
        } else {
            $st = getDB()->prepare('SELECT nome, documento, email, type_pessoa FROM coletas WHERE coletor_id = ? ORDER BY created_at DESC LIMIT 2000');
            $st->execute([$me]);
            $seen = [];
            $out = [];
            foreach ($st->fetchAll() as $r) {
                if (isset($seen[$r['nome']])) {
                    continue;
                }
                $seen[$r['nome']] = true;
                $out[] = $r;
            }
        }
        respond(200, ['ok' => true, 'items' => $out]);
    }
} catch (Exception $e) {
    error_log('coletor_api: ' . $e->getMessage());
    respond(500, ['ok' => false, 'error' => t('Erro ao processar. Tente de novo.')]);
}

respond(400, ['ok' => false, 'error' => t('Ação inválida.')]);
