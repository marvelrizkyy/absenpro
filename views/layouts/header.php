<?php
// views/layouts/header.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';
require_once __DIR__ . '/../../helpers/sanitize_helper.php';
require_once __DIR__ . '/../../helpers/date_helper.php';

$pageTitle = $pageTitle ?? 'Sistem Absensi Karyawan';
$user = current_user();

// Determine layout mode: Employee is dedicated mobile, Admin is dedicated desktop
$isEmployeeMode = ($user && $user['role'] === 'karyawan');
$bodyClass = $isEmployeeMode ? 'mode-employee-mobile' : 'mode-admin-desktop';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= clean_input($pageTitle) ?> — <?= clean_input(APP_NAME) ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%232563eb'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z'/></svg>">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Main Style -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="<?= $bodyClass ?>">
<div class="app-wrapper">
