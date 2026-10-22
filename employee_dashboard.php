<?php
// employee_dashboard.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/helpers/auth_helper.php';
require_once __DIR__ . '/helpers/date_helper.php';
require_once __DIR__ . '/helpers/sanitize_helper.php';

require_employee();
$user = current_user();
$db = get_db_connection();

$today = date('Y-m-d');
$employeeId = (int)$user['id'];

// Get Work Settings
$stmtSet = $db->query("SELECT * FROM work_settings WHERE id = 1");
$settings = $stmtSet->fetch() ?: [
    'check_in_deadline' => '08:00:00',
    'company_name' => 'PT Inovasi Digital Nusantara'
];
$deadlineTime = substr($settings['check_in_deadline'], 0, 5);

// Check today's attendance
$stmtAtt = $db->prepare("SELECT * FROM attendances WHERE employee_id = ? AND attendance_date = ? LIMIT 1");
$stmtAtt->execute([$employeeId, $today]);
$todayAtt = $stmtAtt->fetch();

// Check if on approved leave today
$stmtLeave = $db->prepare("SELECT * FROM leave_requests 
    WHERE employee_id = ? AND status = 'disetujui' AND ? BETWEEN start_date AND end_date LIMIT 1");
$stmtLeave->execute([$employeeId, $today]);
$approvedLeave = $stmtLeave->fetch();

// Monthly statistics for current month
$currentMonth = date('Y-m');
$stmtStats = $db->prepare("SELECT 
    COUNT(CASE WHEN status = 'hadir_tepat_waktu' THEN 1 END) as tepat_waktu,
    COUNT(CASE WHEN status = 'terlambat' THEN 1 END) as terlambat,
    COALESCE(SUM(late_minutes), 0) as total_late_minutes,
    COUNT(id) as total_hadir
    FROM attendances 
    WHERE employee_id = ? AND DATE_FORMAT(attendance_date, '%Y-%m') = ?");
$stmtStats->execute([$employeeId, $currentMonth]);
$stats = $stmtStats->fetch();

$stmtLeaveStats = $db->prepare("SELECT 
    COUNT(CASE WHEN type = 'izin' AND status = 'disetujui' THEN 1 END) as total_izin,
    COUNT(CASE WHEN type = 'sakit' AND status = 'disetujui' THEN 1 END) as total_sakit
    FROM leave_requests 
    WHERE employee_id = ? AND DATE_FORMAT(start_date, '%Y-%m') = ?");
$stmtLeaveStats->execute([$employeeId, $currentMonth]);
$leaveStats = $stmtLeaveStats->fetch();

// Recent attendances for timeline list
$stmtRecent = $db->prepare("SELECT * FROM attendances 
    WHERE employee_id = ? ORDER BY attendance_date DESC LIMIT 7");
$stmtRecent->execute([$employeeId]);
$recentAttendances = $stmtRecent->fetchAll();

$isWorkDayToday = is_work_day($today);
$holidayName = get_holiday_name($today);

$pageTitle = 'Presensi Mobile';
$pageSubtitle = $user['nama_lengkap'] . ' — ' . $user['jabatan'];

require_once __DIR__ . '/views/layouts/header.php';
require_once __DIR__ . '/views/layouts/sidebar_employee.php';
require_once __DIR__ . '/views/layouts/top_navbar.php';
?>

<div style="max-width: 640px; margin: 0 auto;">

    <!-- ========================================================
         HERO PUNCH CARD (MATCHES REFERENCE MOCKUP SCREEN 2 & 3)
         ======================================================== -->
    <div class="card mobile-punch-container" id="punch">
        <div class="app-brand-minimal"><?= clean_input($settings['company_name'] ?? 'PRESENSI MOBILE') ?></div>
        
        <!-- Large Minimalist Digital Clock -->
        <div class="punch-digital-clock live-clock"><?= date('H:i') ?></div>
        <div class="punch-date-subtitle"><?= format_indo_date($today) ?></div>

        <!-- Grand Circular Glow Action Button -->
        <div class="punch-circle-wrapper">
            <?php if ($approvedLeave): ?>
                <div class="punch-circle-btn btn-disabled" title="Izin/Sakit Aktif">
                    <div class="punch-btn-icon">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                    </div>
                    <span class="punch-btn-label">IZIN AKTIF</span>
                </div>

            <?php elseif (!$isWorkDayToday): ?>
                <div class="punch-circle-btn btn-disabled" title="Hari Libur">
                    <div class="punch-btn-icon">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line></svg>
                    </div>
                    <span class="punch-btn-label">HARI LIBUR</span>
                </div>

            <?php elseif (!$todayAtt): ?>
                <!-- CLOCK IN ACTION (BLUE/PURPLE GRADIENT WITH HAND TAP ICON) -->
                <form action="<?= BASE_URL ?>/actions/do_attendance.php" method="POST" id="formCheckIn">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="check_in">
                    <button type="button" class="punch-circle-btn btn-in" onclick="if(confirm('Catat Check-In kehadiran Anda sekarang?')) document.getElementById('formCheckIn').submit();">
                        <div class="punch-btn-icon">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.2">
                                <path d="M12 2a4 4 0 0 0-4 4v7.5a2.5 2.5 0 0 1-5 0V11a1 1 0 0 0-2 0v2.5a4.5 4.5 0 0 0 9 0V6a2 2 0 0 1 4 0v10a6 6 0 0 1-12 0"/>
                                <path d="M16 8.5V6a4 4 0 0 1 8 0v8a8 8 0 0 1-16 0"/>
                            </svg>
                        </div>
                        <span class="punch-btn-label">CLOCK IN</span>
                    </button>
                </form>

            <?php elseif (empty($todayAtt['check_out'])): ?>
                <!-- CLOCK OUT ACTION (PINK/MAGENTA GRADIENT WITH HAND TAP ICON) -->
                <form action="<?= BASE_URL ?>/actions/do_attendance.php" method="POST" id="formCheckOut">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="check_out">
                    <button type="button" class="punch-circle-btn btn-out" onclick="if(confirm('Catat Check-Out kepulangan Anda sekarang?')) document.getElementById('formCheckOut').submit();">
                        <div class="punch-btn-icon">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.2">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                        </div>
                        <span class="punch-btn-label">CLOCK OUT</span>
                    </button>
                </form>

            <?php else: ?>
                <!-- ALREADY FINISHED FOR TODAY -->
                <div class="punch-circle-btn btn-done">
                    <div class="punch-btn-icon">
                        <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.8"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </div>
                    <span class="punch-btn-label">COMPLETED</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Location Pin Subtext -->
        <div class="punch-location-info">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
            <span>Lokasi: Kantor Pusat &bull; Batas Masuk: <strong><?= $deadlineTime ?> WIB</strong></span>
        </div>

        <!-- 3-Column Micro-Stats Row (Clock In, Clock Out, Working Status) -->
        <div class="micro-stats-row">
            <div class="micro-stat-box">
                <div class="micro-stat-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                </div>
                <div class="micro-stat-val">
                    <?= $todayAtt ? substr($todayAtt['check_in'], 0, 5) : '--:--' ?>
                </div>
                <div class="micro-stat-lbl">Clock In</div>
            </div>

            <div class="micro-stat-box">
                <div class="micro-stat-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#F43F5E" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 8 14"></polyline></svg>
                </div>
                <div class="micro-stat-val">
                    <?= ($todayAtt && !empty($todayAtt['check_out'])) ? substr($todayAtt['check_out'], 0, 5) : '--:--' ?>
                </div>
                <div class="micro-stat-lbl">Clock Out</div>
            </div>

            <div class="micro-stat-box">
                <div class="micro-stat-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2.2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </div>
                <div class="micro-stat-val" style="font-size: 0.95rem;">
                    <?php if (!$todayAtt): ?>
                        Belum
                    <?php elseif ($todayAtt['status'] === 'hadir_tepat_waktu'): ?>
                        Tepat
                    <?php else: ?>
                        +<?= (int)$todayAtt['late_minutes'] ?>m
                    <?php endif; ?>
                </div>
                <div class="micro-stat-lbl">Status</div>
            </div>
        </div>
    </div>

    <!-- ========================================================
         MONTH FILTER CAROUSEL PILLS (SCREEN 1 IN REFERENCE)
         ======================================================== -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; padding: 0 4px;">
        <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--text-dark);">Attendance Log</h3>
        <a href="<?= BASE_URL ?>/employee_history.php" style="font-size: 0.8rem; font-weight: 700; color: #4F46E5;">Lihat Semua &rarr;</a>
    </div>

    <div class="month-pill-carousel">
        <?php
        $months = [
            '08' => 'Agustus', '09' => 'September', '10' => 'Oktober',
            '11' => 'November', '12' => 'Desember'
        ];
        $currM = date('m');
        foreach ($months as $num => $name):
            $isActive = ($num === $currM) ? 'active' : '';
        ?>
            <a href="<?= BASE_URL ?>/employee_history.php?month=<?= $num ?>&year=<?= date('Y') ?>" class="month-pill <?= $isActive ?>">
                <?= $name ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ========================================================
         ATTENDANCE TIMELINE LIST (MATCHES SCREEN 1 & 5)
         ======================================================== -->
    <div class="timeline-card-list">
        <?php if (empty($recentAttendances)): ?>
            <div class="card" style="text-align: center; padding: 32px; color: var(--text-muted); font-size: 0.88rem;">
                Belum ada riwayat absensi. Silakan lakukan Clock In hari ini!
            </div>
        <?php else: ?>
            <?php foreach ($recentAttendances as $att): ?>
                <?php
                $timestamp = strtotime($att['attendance_date']);
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
                        <span class="pill-time-in">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <?= substr($att['check_in'], 0, 5) ?>
                        </span>
                        
                        <span class="pill-time-out">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="16 17 21 12 16 7"></polyline></svg>
                            <?= !empty($att['check_out']) ? substr($att['check_out'], 0, 5) : '--:--' ?>
                        </span>
                    </div>

                    <div>
                        <?php if ($att['status'] === 'hadir_tepat_waktu'): ?>
                            <span class="badge badge-success">On-Time</span>
                        <?php else: ?>
                            <span class="badge badge-warning">+<?= (int)$att['late_minutes'] ?>m Telat</span>
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
