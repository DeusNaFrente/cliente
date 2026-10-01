<?php
declare(strict_types=1);
require_once __DIR__ . '/coletas_lib.php';

touchSession();
$id = (string) preg_replace('/[^A-Za-z0-9_\-]/', '', is_string($_GET['id'] ?? null) ? $_GET['id'] : '');
$group = is_string($_GET['group'] ?? null) ? $_GET['group'] : '';
$field = is_string($_GET['field'] ?? null) ? $_GET['field'] : '';
$forceDownload = isset($_GET['download']);

if ($id === '' || !in_array($group, ['evidenciasEmpresa', 'evidenciasResponsavel'], true) || !preg_match('/^[A-Za-z]+$/', $field)) {
    http_response_code(400);
    exit('Requisição inválida.');
}

$st = getDB()->prepare('SELECT s.data_json, c.coletor_id, c.emissor_id FROM submissions s LEFT JOIN coletas c ON c.id = s.coleta_id WHERE s.id = ?');
$st->execute([$id]);
$row = $st->fetch();
$me = currentUserId();
$allowed = $row && (currentRole() === 'admin'
    || ($me !== null && currentRole() === 'seller' && in_array($me, [(int) $row['coletor_id'], (int) $row['emissor_id']], true)));
$info = $allowed ? ((json_decode((string) $row['data_json'], true) ?: [])[$group][$field] ?? null) : null;
if (!$info || empty($info['storedName'])) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

$realBase = realpath(UPLOADS_DIR);
$realPath = realpath(UPLOADS_DIR . '/' . $id . '/' . basename((string) $info['storedName']));
if ($realBase === false || $realPath === false || strpos($realPath, $realBase . DIRECTORY_SEPARATOR) !== 0 || !is_file($realPath)) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

$mime = (string) ($info['mime'] ?? '');
$inline = preg_match('#^(image/(jpeg|png|webp|heic|heif)|application/pdf)$#', $mime) === 1;
$filename = (string) preg_replace('/[^\p{L}\p{N}._ -]/u', '_', (string) ($info['originalName'] ?? basename($realPath)));

header('Content-Type: ' . ($inline ? $mime : 'application/octet-stream'));
header('Content-Length: ' . (string) filesize($realPath));
header('Content-Disposition: ' . ($forceDownload || !$inline ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, no-cache');
readfile($realPath);
exit;
