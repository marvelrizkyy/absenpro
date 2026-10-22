<?php
// employee_history.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/helpers/auth_helper.php';
require_once __DIR__ . '/helpers/date_helper.php';
require_once __DIR__ . '/helpers/sanitize_helper.php';

require_employee();
$user = current_user();
$db = get_db_connection();
$employeeId = (int)$user['id'];

// Filter Month & Year
$selectedMonth = $_GET['month'] ?? date('m');
$selectedYear = $_GET['year'] ?? date('Y');
$monthParam = sprintf('%04d-%02d', (int)$selectedYear, (int)$selectedMonth);

// Fetch attendances for selected month
$stmt = $db->prepare("SELECT a.*, 
    (SELECT COUNT(*) FROM attendance_corrections ac WHERE ac.attendance_id = a.id) as has_correction
    FROM attendances a
    WHERE a.employee_id = ? AND DATE_FORMAT(a.attendance_date, '%Y-%m') = ?
    ORDER BY a.attendance_date DESC");
$stmt->execute([$employeeId, $monthParam]);
$attendances = $stmt->fetchAll();

// Month summary calculations
$totalHadir = count($attendances);
$tepatWaktu = 0;
$terlambat = 0;
$totalMenit = 0;
foreach ($attendances as $row) {
    if ($row['status'] === 'hadir_tepat_waktu') {
        $tepatWaktu++;
    } else {
        $terlambat++;
        $totalMenit += (int)$row['late_minutes'];
    }
}

$indMonths = [
    '01'=>'Januari', '02'=>'Februari', '03'=>'Maret', '04'=>'April',
    '05'=>'Mei', '06'=>'Juni', '07'=>'Juli', '08'=>'Agustus',
    '09'=>'September', '10'=>'Oktober', '11'=>'November', '12'=>'Desember'
];

$pageTitle = 'Riwayat Presensi';
$pageSubtitle = 'Rekap kehadiran bulan ' . ($indMonths[$selectedMonth] ?? '') . ' ' . $selectedYear;

require_once __DIR__ . '/views/layouts/header.php';
require_once __DIR__ . '/views/layouts/sidebar_employee.php';
require_once __DIR__ . '/views/layouts/top_navbar.php';
?>

<div style="max-width: 680px; margin: 0 auto;">

    <!-- ========================================================
         MONTH FILTER CAROUSEL PILLS (SCREEN 1 IN REFERENCE)
         ======================================================== -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; padding: 0 4px;">
        <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-dark);">Pilih Bulan</h3>
        <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted);"><?= $selectedYear ?></span>
    </div>

    <div class="month-pill-carousel">
        <?php foreach ($indMonths as $num => $nama): ?>
            <a href="<?= BASE_URL ?>/employee_history.php?month=<?= $num ?>&year=<?= $selectedYear ?>" class="month-pill <?= $selectedMonth === $num ? 'active' : '' ?>">
                <?= $nama ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Monthly Summary Metric Boxes (3-Column Layout) -->
    <div class="micro-stats-row" style="max-width: 100%; margin-bottom: 24px;">
        <div class="micro-stat-box">
            <div class="micro-stat-val" style="color: #4F46E5;"><?= $totalHadir ?></div>
            <div class="micro-stat-lbl">Total Hadir</div>
        </div>
        <div class="micro-stat-box">
            <div class="micro-stat-val" style="color: #10B981;"><?= $tepatWaktu ?></div>
            <div class="micro-stat-lbl">Tepat Waktu</div>
        </div>
        <div class="micro-stat-box">
            <div class="micro-stat-val" style="color: #F59E0B;"><?= $terlambat ?></div>
            <div class="micro-stat-lbl">Terlambat (<?= $totalMenit ?>m)</div>
        </div>
    </div>

    <!-- ========================================================
         ATTENDANCE TIMELINE LIST (MATCHES SCREEN 1 & 5)
         ======================================================== -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; padding: 0 4px;">
        <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-dark);">
            Catatan: <?= $indMonths[$selectedMonth] ?> <?= $selectedYear ?>
        </h3>
        <span class="badge badge-secondary"><?= $totalHadir ?> Hari Kerja</span>
    </div>

    <div class="timeline-card-list">
        <?php if (empty($attendances)): ?>
            <div class="card" style="text-align: center; padding: 36px; color: var(--text-muted); font-size: 0.88rem;">
                Tidak ada data presensi pada bulan ini.
            </div>
        <?php else: ?>
            <?php foreach ($attendances as $row): ?>
                <?php
                $timestamp = strtotime($row['attendance_date']);
                $dayNum = date('d', $timestamp);
                $dayName = [
                    'Sunday'=>'Minggu', 'Monday'=>'Senin', 'Tuesday'=>'Selasa',
                    'Wednesday'=>'Rabu', 'Thursday'=>'Kamis', 'Friday'=>'Jumat', 'Saturday'=>'Sabtu'
                ][date('l', $timestamp)] ?? '';
                ?>
                <div class="timeline-item-card">
                    <div class="timeline-date-col">
                        <div class="timeline-day-num"><?= $dayNum ?></div>
                        <div>
                            <div class="timeline-day-sub"><?= $dayName ?></div>
                            <div style="font-size: 0.7rem; color: var(--text-light);"><?= date('M Y', $timestamp) ?></div>
                        </div>
                    </div>

                    <div class="timeline-times-col">
                        <span class="pill-time-in" title="Jam Masuk">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <?= substr($row['check_in'], 0, 5) ?>
                        </span>

                        <span class="pill-time-out" title="Jam Pulang">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="16 17 21 12 16 7"></polyline></svg>
                            <?= !empty($row['check_out']) ? substr($row['check_out'], 0, 5) : '--:--' ?>
                        </span>
                    </div>

                    <div style="text-align: right;">
                        <?php if ($row['status'] === 'hadir_tepat_waktu'): ?>
                            <span class="badge badge-success">On-Time</span>
                        <?php else: ?>
                            <span class="badge badge-warning">+<?= (int)$row['late_minutes'] ?>m Telat</span>
                        <?php endif; ?>

                        <?php if ($row['has_correction'] > 0): ?>
                            <div style="font-size: 0.68rem; color: #6366F1; font-weight: 700; margin-top: 4px;">✏️ Dikoreksi</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php
require_once __DIR__ . '/views/layouts/footer.php';
?>
