<?php
// admin_reports.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/helpers/auth_helper.php';
require_once __DIR__ . '/helpers/date_helper.php';
require_once __DIR__ . '/helpers/sanitize_helper.php';

require_admin();
$db = get_db_connection();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$filterEmp = (int)($_GET['employee_id'] ?? 0);
$filterStatus = $_GET['status'] ?? 'all';

// Fetch employees list for dropdown
$stmtEmpList = $db->query("SELECT id, nik, nama_lengkap FROM users WHERE role = 'karyawan' ORDER BY nama_lengkap ASC");
$employeesList = $stmtEmpList->fetchAll();

// Build query
$sql = "SELECT a.*, u.nik, u.nama_lengkap, u.jabatan,
    (SELECT COUNT(*) FROM attendance_corrections ac WHERE ac.attendance_id = a.id) as correction_count
    FROM attendances a
    JOIN users u ON a.employee_id = u.id
    WHERE a.attendance_date BETWEEN :start_date AND :end_date";
$params = [
    ':start_date' => $startDate,
    ':end_date' => $endDate
];

if ($filterEmp > 0) {
    $sql .= " AND a.employee_id = :emp_id";
    $params[':emp_id'] = $filterEmp;
}

if ($filterStatus === 'hadir_tepat_waktu' || $filterStatus === 'terlambat') {
    $sql .= " AND a.status = :status";
    $params[':status'] = $filterStatus;
}

$sql .= " ORDER BY a.attendance_date DESC, a.check_in ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$reportData = $stmt->fetchAll();

// Calculate summary totals
$totalRecords = count($reportData);
$totalTepatWaktu = 0;
$totalTerlambat = 0;
$totalLateMinutes = 0;

foreach ($reportData as $row) {
    if ($row['status'] === 'hadir_tepat_waktu') {
        $totalTepatWaktu++;
    } else {
        $totalTerlambat++;
        $totalLateMinutes += (int)$row['late_minutes'];
    }
}

// Build export query string
$queryString = http_build_query([
    'start_date' => $startDate,
    'end_date' => $endDate,
    'employee_id' => $filterEmp,
    'status' => $filterStatus
]);

$pageTitle = 'Laporan & Rekapitulasi Presensi';
$pageSubtitle = 'Periode: ' . format_indo_date($startDate) . ' s/d ' . format_indo_date($endDate);

require_once __DIR__ . '/views/layouts/header.php';
require_once __DIR__ . '/views/layouts/sidebar_admin.php';
require_once __DIR__ . '/views/layouts/top_navbar.php';
?>

<!-- Filter Card -->
<div class="card" style="margin-bottom: 24px;">
    <form method="GET" action="<?= BASE_URL ?>/admin_reports.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; align-items: flex-end;">
        <div>
            <label class="form-label">Tanggal Mulai</label>
            <input type="date" name="start_date" class="form-control" value="<?= clean_input($startDate) ?>" required>
        </div>

        <div>
            <label class="form-label">Tanggal Selesai</label>
            <input type="date" name="end_date" class="form-control" value="<?= clean_input($endDate) ?>" required>
        </div>

        <div>
            <label class="form-label">Pilih Karyawan</label>
            <select name="employee_id" class="form-select">
                <option value="0">Semua Karyawan</option>
                <?php foreach ($employeesList as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $filterEmp === (int)$e['id'] ? 'selected' : '' ?>>
                        <?= clean_input($e['nama_lengkap']) ?> (<?= clean_input($e['nik']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="form-label">Status Presensi</label>
            <select name="status" class="form-select">
                <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Semua Status</option>
                <option value="hadir_tepat_waktu" <?= $filterStatus === 'hadir_tepat_waktu' ? 'selected' : '' ?>>Tepat Waktu</option>
                <option value="terlambat" <?= $filterStatus === 'terlambat' ? 'selected' : '' ?>>Terlambat</option>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary" style="flex: 1;">
                Terapkan Filter
            </button>
            <a href="<?= BASE_URL ?>/admin_reports.php" class="btn btn-secondary">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Summary Metric Cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div>
            <div class="stat-label">Total Kehadiran</div>
            <div class="stat-value"><?= $totalRecords ?></div>
            <div class="stat-subtext">Catatan hadir pada periode terpilih</div>
        </div>
        <div class="stat-icon-wrapper">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
        </div>
    </div>

    <div class="stat-card stat-success">
        <div>
            <div class="stat-label">Hadir Tepat Waktu</div>
            <div class="stat-value"><?= $totalTepatWaktu ?></div>
            <div class="stat-subtext">Presensi sebelum batas waktu</div>
        </div>
        <div class="stat-icon-wrapper">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>
    </div>

    <div class="stat-card stat-warning">
        <div>
            <div class="stat-label">Presensi Terlambat</div>
            <div class="stat-value"><?= $totalTerlambat ?></div>
            <div class="stat-subtext">Akumulasi <?= $totalLateMinutes ?> menit telat</div>
        </div>
        <div class="stat-icon-wrapper">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        </div>
    </div>
</div>

<!-- Report Table Card -->
<div class="card">
    <div class="card-header" style="flex-wrap: wrap; gap: 14px;">
        <div>
            <h2 class="card-title">Data Rekapitulasi Presensi</h2>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;">
                Ditemukan <strong><?= $totalRecords ?></strong> baris data sesuai filter
            </div>
        </div>

        <div style="display: flex; gap: 10px;">
            <a href="<?= BASE_URL ?>/actions/export_excel.php?<?= $queryString ?>" class="btn btn-sm btn-success" target="_blank">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Ekspor ke Excel
            </a>
            <a href="<?= BASE_URL ?>/actions/export_pdf.php?<?= $queryString ?>" class="btn btn-sm btn-secondary" target="_blank">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Cetak / Ekspor PDF
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Nama Karyawan</th>
                    <th>NIK</th>
                    <th>Jabatan</th>
                    <th>Jam Masuk</th>
                    <th>Jam Pulang</th>
                    <th>Status Presensi</th>
                    <th>Keterlambatan</th>
                    <th>Audit</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reportData)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 36px; color: var(--text-muted);">
                            Tidak ada data presensi yang ditemukan pada rentang tanggal ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($reportData as $row): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= format_short_date($row['attendance_date']) ?></strong></td>
                            <td><strong><?= clean_input($row['nama_lengkap']) ?></strong></td>
                            <td><?= clean_input($row['nik']) ?></td>
                            <td><?= clean_input($row['jabatan']) ?></td>
                            <td><?= format_time($row['check_in']) ?></td>
                            <td><?= format_time($row['check_out']) ?></td>
                            <td>
                                <?php if ($row['status'] === 'hadir_tepat_waktu'): ?>
                                    <span class="badge badge-success">Tepat Waktu</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Terlambat</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $row['late_minutes'] > 0 ? "<strong>{$row['late_minutes']}</strong> Menit" : '-' ?>
                            </td>
                            <td>
                                <?= $row['correction_count'] > 0 ? '<span class="badge badge-secondary" title="Pernah dikoreksi">✏️ Koreksi</span>' : '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/views/layouts/footer.php';
?>
