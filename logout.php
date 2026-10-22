<?php
// logout.php
declare(strict_types=1);

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/helpers/sanitize_helper.php';

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

session_start();
flash_set('info', 'Anda telah berhasil keluar dari sistem.');
header('Location: ' . BASE_URL . '/login.php');
exit;
