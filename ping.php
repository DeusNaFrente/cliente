<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

touchSession(true);
if (currentRole() === null) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}
echo json_encode(['ok' => true]);
