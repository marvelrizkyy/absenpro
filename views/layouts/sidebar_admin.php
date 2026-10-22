<?php
// views/layouts/sidebar_admin.php
declare(strict_types=1);

$currentScript = basename($_SERVER['PHP_SELF']);
$user = current_user();
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-logo-icon">A</div>
        <div>
            <div class="sidebar-brand-title">ABSENSI PRO</div>
            <div class="sidebar-brand-subtitle">Admin Portal</div>
        </div>
    </div>

    <ul class="sidebar-menu">
        <li class="menu-category">Utama</li>
        <li class="menu-item">
            <a href="<?= BASE_URL ?>/admin_dashboard.php" class="menu-link <?= $currentScript === 'admin_dashboard.php' ? 'active' : '' ?>">
                <span class="menu-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                </span>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="menu-item">
            <a href="<?= BASE_URL ?>/admin_employees.php" class="menu-link <?= in_array($currentScript, ['admin_employees.php', 'admin_employee_detail.php']) ? 'active' : '' ?>">
                <span class="menu-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </span>
                <span>Data Karyawan</span>
            </a>
        </li>

        <li class="menu-category">Operasional Absensi</li>
        <li class="menu-item">
            <a href="<?= BASE_URL ?>/admin_attendance.php" class="menu-link <?= $currentScript === 'admin_attendance.php' ? 'active' : '' ?>">
                <span class="menu-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                </span>
                <span>Monitoring Absensi</span>
            </a>
        </li>
        <li class="menu-item">
            <a href="<?= BASE_URL ?>/admin_leaves.php" class="menu-link <?= $currentScript === 'admin_leaves.php' ? 'active' : '' ?>">
                <span class="menu-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                </span>
                <span>Izin & Sakit</span>
            </a>
        </li>
        <li class="menu-item">
            <a href="<?= BASE_URL ?>/admin_reports.php" class="menu-link <?= $currentScript === 'admin_reports.php' ? 'active' : '' ?>">
                <span class="menu-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                </span>
                <span>Laporan & Rekap</span>
            </a>
        </li>

        <li class="menu-category">Sistem & Integrasi</li>
        <li class="menu-item">
            <a href="<?= BASE_URL ?>/admin_fonnte_logs.php" class="menu-link <?= $currentScript === 'admin_fonnte_logs.php' ? 'active' : '' ?>">
                <span class="menu-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                </span>
                <span>Log WhatsApp</span>
            </a>
        </li>
        <li class="menu-item">
            <a href="<?= BASE_URL ?>/admin_settings.php" class="menu-link <?= $currentScript === 'admin_settings.php' ? 'active' : '' ?>">
                <span class="menu-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                </span>
                <span>Pengaturan</span>
            </a>
        </li>
        <li class="menu-item">
            <a href="<?= BASE_URL ?>/profile.php" class="menu-link <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
                <span class="menu-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </span>
                <span>Profil Admin</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <div class="user-profile-badge">
            <div class="user-avatar-circle">
                <?= strtoupper(substr($user['nama_lengkap'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="user-info-text">
                <div class="user-info-name"><?= clean_input($user['nama_lengkap'] ?? 'Admin') ?></div>
                <div class="user-info-role"><?= clean_input($user['jabatan'] ?? 'Administrator') ?></div>
            </div>
            <a href="<?= BASE_URL ?>/logout.php" title="Keluar / Logout" style="color: #EF4444; margin-left: auto; display: flex; align-items: center;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            </a>
        </div>
    </div>
</aside>
