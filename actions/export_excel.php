<?php
// actions/export_excel.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/date_helper.php';

require_admin();
$db = get_db_connection();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$filterEmp = (int)($_GET['employee_id'] ?? 0);
$filterStatus = $_GET['status'] ?? 'all';

// Query data
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
$rows = $stmt->fetchAll();

// Get company name
$stmtSet = $db->query("SELECT company_name FROM work_settings WHERE id = 1");
$companyName = $stmtSet->fetchColumn() ?: 'PT Inovasi Digital Nusantara';

$filename = "Laporan_Absensi_" . date('Ymd_His') . ".xls";

// Set Excel Headers
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF"; // UTF-8 BOM
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Calibri, Arial, sans-serif; }
        .title { font-size: 16pt; font-weight: bold; text-align: center; }
        .subtitle { font-size: 11pt; text-align: center; margin-bottom: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th { background-color: #0F172A; color: #FFFFFF; font-weight: bold; border: 1px solid #334155; padding: 8px; text-align: left; }
        td { border: 1px solid #CBD5E1; padding: 6px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="title"><?= htmlspecialchars($companyName) ?></div>
    <div class="subtitle">Laporan Rekapitulasi Presensi Karyawan<br>Periode: <?= $startDate ?> s/d <?= $endDate ?> (Dicetak: <?= date('d/m/Y H:i') ?> WIB)</div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>NIK</th>
                <th>Nama Karyawan</th>
                <th>Jabatan</th>
                <th>Jam Masuk</th>
                <th>Jam Pulang</th>
                <th>Status Presensi</th>
                <th>Keterlambatan</th>
                <th>Audit Koreksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="10" class="text-center">Tidak ada data kehadiran pada periode ini.</td>
                </tr>
            <?php else: ?>
                <?php $no = 1; foreach ($rows as $r): ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td><?= $r['attendance_date'] ?></td>
                        <td style="mso-number-format:'\@';"><?= htmlspecialchars($r['nik']) ?></td>
                        <td><?= htmlspecialchars($r['nama_lengkap']) ?></td>
                        <td><?= htmlspecialchars($r['jabatan']) ?></td>
                        <td><?= $r['check_in'] ?></td>
                        <td><?= $r['check_out'] ?? '-' ?></td>
                        <td><?= ($r['status'] === 'hadir_tepat_waktu') ? 'Tepat Waktu' : 'Terlambat' ?></td>
                        <td class="text-right"><?= $r['late_minutes'] > 0 ? $r['late_minutes'] . ' Menit' : '-' ?></td>
                        <td class="text-center"><?= $r['correction_count'] > 0 ? 'Pernah Dikoreksi' : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
