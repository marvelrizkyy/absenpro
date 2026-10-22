<?php
// views/layouts/footer.php
declare(strict_types=1);

$user = current_user();
$currentScript = basename($_SERVER['PHP_SELF']);
$role = $user['role'] ?? 'karyawan';
?>
    </main>

    <footer style="padding: 18px 28px; background: transparent; font-size: 0.78rem; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
        <div>&copy; <?= date('Y') ?> <strong><?= clean_input(APP_NAME) ?></strong>.</div>
        <div style="display: flex; gap: 14px;">
            <span>Waktu: <strong>WIB (Asia/Jakarta)</strong></span>
            <span>Versi: <strong>2.0 Mobile-First</strong></span>
        </div>
    </footer>
</div><!-- /.main-wrapper -->
</div><!-- /.app-wrapper -->

<!-- Floating Mobile Bottom Navigation Bar (Visible on phones & small screens) -->
<?php if (is_logged_in() && $user && $user['account_status'] === 'disetujui'): ?>
<nav class="mobile-bottom-nav" aria-label="Mobile Navigation">
    <?php if ($role === 'karyawan'): ?>
        <a href="<?= BASE_URL ?>/employee_dashboard.php" class="bottom-nav-item <?= $currentScript === 'employee_dashboard.php' ? 'active' : '' ?>">
            <div class="nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            </div>
            <span>Home</span>
        </a>

        <a href="<?= BASE_URL ?>/employee_history.php" class="bottom-nav-item <?= $currentScript === 'employee_history.php' ? 'active' : '' ?>">
            <div class="nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            </div>
            <span>Riwayat</span>
        </a>

        <a href="<?= BASE_URL ?>/employee_dashboard.php#punch" class="bottom-nav-item bottom-nav-punch">
            <div class="bottom-nav-punch-circle" title="Absen Sekarang">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8z"/><circle cx="12" cy="12" r="3" fill="currentColor"/></svg>
            </div>
            <span style="font-weight: 700; color: #4F46E5; margin-top: 2px;">Absen</span>
        </a>

        <a href="<?= BASE_URL ?>/employee_leaves.php" class="bottom-nav-item <?= $currentScript === 'employee_leaves.php' ? 'active' : '' ?>">
            <div class="nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
            </div>
            <span>Izin</span>
        </a>

        <a href="<?= BASE_URL ?>/profile.php" class="bottom-nav-item <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
            <div class="nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            </div>
            <span>Profil</span>
        </a>

    <?php else: /* ADMIN ROLE */ ?>
        <a href="<?= BASE_URL ?>/admin_dashboard.php" class="bottom-nav-item <?= $currentScript === 'admin_dashboard.php' ? 'active' : '' ?>">
            <div class="nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            </div>
            <span>Beranda</span>
        </a>

        <a href="<?= BASE_URL ?>/admin_employees.php" class="bottom-nav-item <?= in_array($currentScript, ['admin_employees.php', 'admin_employee_detail.php']) ? 'active' : '' ?>">
            <div class="nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path></svg>
            </div>
            <span>Karyawan</span>
        </a>

        <a href="<?= BASE_URL ?>/admin_attendance.php" class="bottom-nav-item bottom-nav-punch">
            <div class="bottom-nav-punch-circle" title="Monitoring Absensi">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            </div>
            <span style="font-weight: 700; color: #4F46E5; margin-top: 2px;">Presensi</span>
        </a>

        <a href="<?= BASE_URL ?>/admin_leaves.php" class="bottom-nav-item <?= $currentScript === 'admin_leaves.php' ? 'active' : '' ?>">
            <div class="nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
            </div>
            <span>Izin</span>
        </a>

        <a href="<?= BASE_URL ?>/profile.php" class="bottom-nav-item <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
            <div class="nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            </div>
            <span>Profil</span>
        </a>
    <?php endif; ?>
</nav>
<?php endif; ?>

<!-- Main Interactive Scripts -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
