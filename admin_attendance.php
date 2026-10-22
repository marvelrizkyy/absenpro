<?php
// admin_attendance.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/helpers/auth_helper.php';
require_once __DIR__ . '/helpers/date_helper.php';
require_once __DIR__ . '/helpers/sanitize_helper.php';

require_admin();
$db = get_db_connection();

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$filterStatus = $_GET['status'] ?? 'all';

$isWorkDay = is_work_day($selectedDate);
$holidayName = get_holiday_name($selectedDate);

// Fetch all active employees
$stmtEmps = $db->query("SELECT id, nik, nama_lengkap, jabatan, no_wa FROM users WHERE role = 'karyawan' AND account_status = 'disetujui' AND is_active = 1 ORDER BY nama_lengkap ASC");
$allEmployees = $stmtEmps->fetchAll();

// Fetch attendances for selected date
$stmtAtt = $db->prepare("SELECT a.*, 
    (SELECT COUNT(*) FROM attendance_corrections ac WHERE ac.attendance_id = a.id) as correction_count
    FROM attendances a WHERE a.attendance_date = ?");
$stmtAtt->execute([$selectedDate]);
$attendancesMap = [];
foreach ($stmtAtt->fetchAll() as $row) {
    $attendancesMap[$row['employee_id']] = $row;
}

// Fetch approved leave requests for selected date
$stmtLeaves = $db->prepare("SELECT * FROM leave_requests WHERE status = 'disetujui' AND ? BETWEEN start_date AND end_date");
$stmtLeaves->execute([$selectedDate]);
$leavesMap = [];
foreach ($stmtLeaves->fetchAll() as $row) {
    $leavesMap[$row['employee_id']] = $row;
}

// Build consolidated list
$monitoringData = [];
$today = date('Y-m-d');

$countHadir = 0;
$countTerlambat = 0;
$countIzinSakit = 0;
$countBelumHadir = 0;
$countAlpa = 0;

foreach ($allEmployees as $emp) {
    $empId = (int)$emp['id'];
    $att = $attendancesMap[$empId] ?? null;
    $leave = $leavesMap[$empId] ?? null;

    $itemStatus = 'belum_hadir';
    $statusLabel = 'Belum Hadir';
    $badgeClass = 'badge-secondary';

    if ($att) {
        if ($att['status'] === 'hadir_tepat_waktu') {
            $itemStatus = 'tepat_waktu';
            $statusLabel = 'Hadir Tepat Waktu';
            $badgeClass = 'badge-success';
            $countHadir++;
        } else {
            $itemStatus = 'terlambat';
            $statusLabel = "Terlambat ({$att['late_minutes']} mnt)";
            $badgeClass = 'badge-warning';
            $countTerlambat++;
        }
    } elseif ($leave) {
        $itemStatus = $leave['type'];
        $statusLabel = strtoupper($leave['type']);
        $badgeClass = ($leave['type'] === 'sakit') ? 'badge-danger' : 'badge-blue';
        $countIzinSakit++;
    } else {
        if ($selectedDate < $today) {
            if ($isWorkDay) {
                $itemStatus = 'alpa';
                $statusLabel = 'Alpa (Tanpa Keterangan)';
                $badgeClass = 'badge-danger';
                $countAlpa++;
            } else {
                $itemStatus = 'libur';
                $statusLabel = 'Hari Libur';
                $badgeClass = 'badge-secondary';
            }
        } else {
            if ($isWorkDay) {
                $itemStatus = 'belum_hadir';
                $statusLabel = 'Belum Hadir';
                $badgeClass = 'badge-secondary';
                $countBelumHadir++;
            } else {
                $itemStatus = 'libur';
                $statusLabel = 'Hari Libur';
                $badgeClass = 'badge-secondary';
            }
        }
    }

    if ($filterStatus === 'all' || $filterStatus === $itemStatus) {
        $monitoringData[] = [
            'employee' => $emp,
            'attendance' => $att,
            'leave' => $leave,
            'status_key' => $itemStatus,
            'status_label' => $statusLabel,
            'badge_class' => $badgeClass
        ];
    }
}

$pageTitle = 'Monitoring Presensi Harian';
$pageSubtitle = 'Tanggal pemantauan: ' . format_indo_date($selectedDate);

require_once __DIR__ . '/views/layouts/header.php';
require_once __DIR__ . '/views/layouts/sidebar_admin.php';
require_once __DIR__ . '/views/layouts/top_navbar.php';
?>

<!-- Filter & Date Selector -->
<div class="card" style="margin-bottom: 24px;">
    <form method="GET" action="<?= BASE_URL ?>/admin_attendance.php" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 180px;">
            <label class="form-label">Pilih Tanggal Presensi</label>
            <input type="date" name="date" class="form-control" value="<?= clean_input($selectedDate) ?>" onchange="this.form.submit()">
        </div>

        <div style="flex: 1; min-width: 160px;">
            <label class="form-label">Filter Status</label>
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Semua Status</option>
                <option value="tepat_waktu" <?= $filterStatus === 'tepat_waktu' ? 'selected' : '' ?>>Tepat Waktu</option>
                <option value="terlambat" <?= $filterStatus === 'terlambat' ? 'selected' : '' ?>>Terlambat</option>
                <option value="izin" <?= $filterStatus === 'izin' ? 'selected' : '' ?>>Izin</option>
                <option value="sakit" <?= $filterStatus === 'sakit' ? 'selected' : '' ?>>Sakit</option>
                <option value="belum_hadir" <?= $filterStatus === 'belum_hadir' ? 'selected' : '' ?>>Belum Hadir</option>
                <option value="alpa" <?= $filterStatus === 'alpa' ? 'selected' : '' ?>>Alpa</option>
            </select>
        </div>

        <div>
            <a href="<?= BASE_URL ?>/admin_attendance.php?date=<?= date('Y-m-d') ?>" class="btn btn-secondary">
                Hari Ini
            </a>
        </div>
    </form>
</div>

<!-- Daily Status Stat Cards -->
<div class="stat-grid">
    <div class="stat-card stat-success">
        <div>
            <div class="stat-label">Hadir Tepat Waktu</div>
            <div class="stat-value"><?= $countHadir ?></div>
            <div class="stat-subtext">Sebelum jam batas</div>
        </div>
        <div class="stat-icon-wrapper">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>
    </div>

    <div class="stat-card stat-warning">
        <div>
            <div class="stat-label">Terlambat</div>
            <div class="stat-value"><?= $countTerlambat ?></div>
            <div class="stat-subtext">Presensi telat</div>
        </div>
        <div class="stat-icon-wrapper">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        </div>
    </div>

    <div class="stat-card stat-purple">
        <div>
            <div class="stat-label">Izin / Sakit</div>
            <div class="stat-value"><?= $countIzinSakit ?></div>
            <div class="stat-subtext">Telah disetujui HR</div>
        </div>
        <div class="stat-icon-wrapper">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
        </div>
    </div>

    <div class="stat-card <?= $selectedDate < $today ? 'stat-danger' : '' ?>">
        <div>
            <div class="stat-label"><?= $selectedDate < $today ? 'Alpa / Tidak Hadir' : 'Belum Hadir' ?></div>
            <div class="stat-value"><?= $selectedDate < $today ? $countAlpa : $countBelumHadir ?></div>
            <div class="stat-subtext"><?= $selectedDate < $today ? 'Tanpa keterangan' : 'Menunggu check-in' ?></div>
        </div>
        <div class="stat-icon-wrapper">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        </div>
    </div>
</div>

<!-- Attendance Monitoring Table -->
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Rekap Presensi Harian: <?= format_indo_date($selectedDate) ?></h2>
            <?php if (!$isWorkDay): ?>
                <div style="font-size: 0.8rem; color: var(--danger-600); margin-top: 2px;">
                    ⚠️ Tanggal ini adalah <?= $holidayName ? "Hari Libur Nasional ({$holidayName})" : "Hari Minggu (Bukan hari kerja reguler)" ?>.
                </div>
            <?php endif; ?>
        </div>
        <span class="badge badge-secondary"><?= count($monitoringData) ?> Karyawan</span>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Karyawan</th>
                    <th>Jam Masuk</th>
                    <th>Jam Pulang</th>
                    <th>Status Presensi</th>
                    <th>Keterlambatan</th>
                    <th style="text-align: right;">Aksi Audit</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($monitoringData)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 36px; color: var(--text-muted);">
                            Tidak ada karyawan yang cocok dengan filter status ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($monitoringData as $row): ?>
                        <?php 
                        $emp = $row['employee'];
                        $att = $row['attendance'];
                        ?>
                        <tr>
                            <td>
                                <a href="<?= BASE_URL ?>/admin_employee_detail.php?id=<?= $emp['id'] ?>" style="font-weight: 700; color: var(--navy-900);">
                                    <?= clean_input($emp['nama_lengkap']) ?>
                                </a>
                                <div style="font-size: 0.74rem; color: var(--text-muted);">
                                    <?= clean_input($emp['jabatan']) ?> (<?= clean_input($emp['nik']) ?>)
                                </div>
                            </td>
                            <td>
                                <?= $att ? format_time($att['check_in']) : '-' ?>
                            </td>
                            <td>
                                <?= ($att && !empty($att['check_out'])) ? format_time($att['check_out']) : '-' ?>
                            </td>
                            <td>
                                <span class="badge <?= $row['badge_class'] ?>">
                                    <?= clean_input($row['status_label']) ?>
                                </span>
                                <?php if ($att && $att['correction_count'] > 0): ?>
                                    <span class="badge badge-secondary" title="Pernah dikoreksi admin">✏️ Dikoreksi</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= ($att && $att['late_minutes'] > 0) ? "{$att['late_minutes']} Menit" : '-' ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if ($att): ?>
                                    <button type="button" class="btn btn-sm btn-secondary" onclick='openCorrectionModal(<?= json_encode($att) ?>, <?= json_encode($emp) ?>)'>
                                        ✏️ Koreksi
                                    </button>
                                <?php else: ?>
                                    <span style="font-size: 0.75rem; color: var(--text-light);">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Correction -->
<div id="correctionModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Koreksi Data Presensi (Audit Trail)</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('correctionModal')">&times;</button>
        </div>

        <form action="<?= BASE_URL ?>/actions/do_admin_action.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="correct_attendance">
            <input type="hidden" name="attendance_id" id="corr_att_id" value="">

            <div style="background: #F8FAFC; border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 12px; margin-bottom: 16px; font-size: 0.82rem;">
                <div>Karyawan: <strong id="corr_emp_name"></strong></div>
                <div style="color: var(--text-muted); margin-top: 2px;">Tanggal Presensi: <span id="corr_date"></span></div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div class="form-group">
                    <label for="corr_check_in" class="form-label required">Jam Masuk Baru</label>
                    <input type="time" id="corr_check_in" name="check_in" step="1" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="corr_check_out" class="form-label">Jam Pulang Baru</label>
                    <input type="time" id="corr_check_out" name="check_out" step="1" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label for="corr_reason" class="form-label required">Alasan Koreksi (Wajib Diisi)</label>
                <textarea id="corr_reason" name="correction_reason" class="form-control" placeholder="Contoh: Kesalahan teknis jaringan internet kantor / izin tugas luar kantor..." required></textarea>
                <div class="form-hint">Alasan wajib dicatat dan akan disimpan ke histori audit serta dikirim ke WhatsApp karyawan.</div>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('correctionModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Koreksi</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCorrectionModal(att, emp) {
    document.getElementById('corr_att_id').value = att.id;
    document.getElementById('corr_emp_name').textContent = emp.nama_lengkap + ' (' + emp.nik + ')';
    document.getElementById('corr_date').textContent = att.attendance_date;
    document.getElementById('corr_check_in').value = att.check_in;
    document.getElementById('corr_check_out').value = att.check_out || '';
    document.getElementById('corr_reason').value = '';
    openModal('correctionModal');
}
</script>

<?php
require_once __DIR__ . '/views/layouts/footer.php';
?>
