<?php
// views/layouts/top_navbar.php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Halaman Utama';
$pageSubtitle = $pageSubtitle ?? '';
$user = current_user();
$isEmployeeMode = ($user && $user['role'] === 'karyawan');
?>
<div class="main-wrapper">
    <?php if ($isEmployeeMode): ?>
        <!-- Mobile App Top Bar (Matches Reference Screen 2 & 3) -->
        <header class="mobile-app-header">
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 32px; height: 32px; border-radius: 10px; background: var(--grad-punch-in); color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem;">
                    A
                </div>
                <span style="font-size: 0.92rem; font-weight: 800; color: var(--text-dark); letter-spacing: -0.02em;">PRESENSI PRO</span>
            </div>

            <div style="display: flex; align-items: center; gap: 10px;">
                <div class="live-clock-pill" style="padding: 4px 12px; font-size: 0.75rem;">
                    <span class="clock-dot"></span>
                    <span class="live-clock"><?= date('H:i') ?></span>
                </div>
                <a href="<?= BASE_URL ?>/profile.php" style="display: flex;">
                    <div class="user-avatar-circle" style="width: 34px; height: 34px; font-size: 0.82rem;">
                        <?= strtoupper(substr($user['nama_lengkap'] ?? 'K', 0, 1)) ?>
                    </div>
                </a>
            </div>
        </header>
    <?php else: ?>
        <!-- Desktop Fixed Top Bar for Admin -->
        <header class="top-navbar">
            <div>
                <h1 class="page-header-title"><?= clean_input($pageTitle) ?></h1>
                <?php if (!empty($pageSubtitle)): ?>
                    <p class="page-header-desc"><?= clean_input($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>

            <div class="navbar-actions">
                <div class="live-clock-pill" title="Waktu Nyata Server (WIB)">
                    <span class="clock-dot"></span>
                    <span>WIB</span>
                    <span class="live-clock"><?= date('H:i:s') ?></span>
                </div>

                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="badge badge-blue">
                        ADMINISTRATOR
                    </span>
                    <a href="<?= BASE_URL ?>/logout.php" class="btn btn-sm btn-secondary" style="color: #EF4444;" title="Keluar">
                        Keluar
                    </a>
                </div>
            </div>
        </header>
    <?php endif; ?>

    <main class="content-body">
        <?= render_flash_messages() ?>
