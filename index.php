<?php
// index.php
declare(strict_types=1);

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/helpers/auth_helper.php';

if (is_logged_in()) {
    $user = current_user();
    if ($user) {
        if ($user['role'] === 'admin') {
            header('Location: ' . BASE_URL . '/admin_dashboard.php');
            exit;
        } elseif ($user['role'] === 'karyawan') {
            if ($user['account_status'] === 'menunggu') {
                header('Location: ' . BASE_URL . '/waiting_approval.php');
                exit;
            }
            header('Location: ' . BASE_URL . '/employee_dashboard.php');
            exit;
        }
    }
}

header('Location: ' . BASE_URL . '/login.php');
exit;
