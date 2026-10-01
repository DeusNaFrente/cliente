<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/i18n.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_string($_POST['lang'] ?? null)) {
    setLang($_POST['lang']);
}
$back = is_string($_POST['back'] ?? null) ? $_POST['back'] : '';
if (!preg_match('#^[a-z_-]+\.php(\?[A-Za-z0-9_=&%.-]*)?$#', $back)) {
    $back = 'login.php';
}
header('Location: ' . $back);
exit;
