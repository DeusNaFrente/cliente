<?php
declare(strict_types=1);

/**
 * Lê um arquivo .env simples (CHAVE=valor por linha) sem depender de bibliotecas externas.
 */
function loadEnv(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        [$key, $value] = $parts;
        $key = trim($key);
        $value = trim($value);
        if (preg_match('/^"(.*)"$/', $value, $m)) {
            $value = $m[1];
        } elseif (preg_match("/^'(.*)'$/", $value, $m)) {
            $value = $m[1];
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

loadEnv(__DIR__ . '/.env');

define('DATA_DIR', __DIR__ . '/data');
define('UPLOADS_DIR', __DIR__ . '/uploads');
define('MAX_FILE_SIZE', 8 * 1024 * 1024); // 8MB por arquivo
define('ALLOWED_FILE_EXT', ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'pdf']);

if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0775, true);
}
if (!is_dir(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0775, true);
}
