<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = getenv("DB_HOST") ?: "127.0.0.1";
    $port = (int) (getenv("DB_PORT") ?: 3306);
    $user = (string) getenv("DB_USER");
    $pass = (string) getenv("DB_PASS");
    $name = (string) getenv("DB_NAME");

    $dsn = "mysql:host=" . $host . ";port=" . $port . ";dbname=" . $name . ";charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Erro ao conectar: " . $e->getMessage());
    }
}
