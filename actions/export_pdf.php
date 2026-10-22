<?php
// actions/export_pdf.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/date_helper.php';
require_once __DIR__ . '/../helpers/sanitize_helper.php';

require_admin();
$admin = current_user();
$db = get_db_connection();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$filterEmp = (int)($_GET['employee_id'] ?? 0);
$filterStatus = $_GET['status'] ?? 'all';

// Fetch data
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

// Company info
$stmtSet = $db->query("SELECT company_name, check_in_deadline FROM work_settings WHERE id = 1");
$settings = $stmtSet->fetch();
$companyName = $settings['company_name'] ?? 'PT Inovasi Digital Nusantara';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Presensi — <?= clean_input($companyName) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #1E293B;
            margin: 0;
            padding: 30px;
            background: #FFFFFF;
            font-size: 11pt;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0F172A;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 18pt;
            font-weight: bold;
            color: #0F172A;
            letter-spacing: -0.02em;
        }
        .report-title {
            font-size: 13pt;
            font-weight: bold;
            margin-top: 4px;
            color: #2563EB;
        }
        .meta-bar {
            display: flex;
            justify-content: space-between;
            font-size: 9.5pt;
            color: #64748B;
            margin-bottom: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th {
            background-color: #0F172A;
            color: #FFFFFF;
            font-size: 9pt;
            font-weight: bold;
            padding: 8px 10px;
            border: 1px solid #0F172A;
            text-align: left;
        }
        td {
            padding: 7px 10px;
            border: 1px solid #CBD5E1;
            font-size: 9pt;
        }
        tr:nth-child(even) td {
            background-color: #F8FAFC;
        }
        .badge {
            font-size: 7.5pt;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
        }
        .badge-success { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .badge-warning { background: #FFFBEB; color: #D97706; border: 1px solid #FDE68A; }
        .signature-area {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            page-break-inside: avoid;
        }
        .signature-box {
            text-align: center;
            width: 200px;
        }
        .signature-line {
            margin-top: 70px;
            border-top: 1px solid #0F172A;
            font-weight: bold;
        }
        .no-print-bar {
            background: #EFF6FF;
            border: 1px solid #BFDBFE;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-print {
            background: #2563EB;
            color: #FFFFFF;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
        }
        @media print {
            .no-print-bar {
                display: none;
            }
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <div><strong>Pratinjau Cetak Laporan PDF:</strong> Klik tombol untuk mencetak atau menyimpan sebagai file PDF.</div>
    <button class="btn-print" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
</div>

<div class="header">
    <div class="company-name"><?= clean_input($companyName) ?></div>
    <div class="report-title">LAPORAN REKAPITULASI PRESENSI KARYAWAN</div>
</div>

<div class="meta-bar">
    <div>
        <strong>Periode Laporan:</strong> <?= format_indo_date($startDate) ?> s/d <?= format_indo_date($endDate) ?>
    </div>
    <div>
        <strong>Dicetak oleh:</strong> <?= clean_input($admin['nama_lengkap']) ?> (<?= date('d/m/Y H:i') ?> WIB)
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width: 30px;">No</th>
            <th>Tanggal</th>
            <th>NIK</th>
            <th>Nama Karyawan</th>
            <th>Jabatan</th>
            <th>Jam Masuk</th>
            <th>Jam Pulang</th>
            <th>Status</th>
            <th>Keterlambatan</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
            <tr>
                <td colspan="9" style="text-align: center; padding: 24px;">Tidak ada data presensi pada rentang tanggal ini.</td>
            </tr>
        <?php else: ?>
            <?php $no = 1; foreach ($rows as $r): ?>
                <tr>
                    <td style="text-align: center;"><?= $no++ ?></td>
                    <td><strong><?= format_short_date($r['attendance_date']) ?></strong></td>
                    <td><?= clean_input($r['nik']) ?></td>
                    <td><?= clean_input($r['nama_lengkap']) ?></td>
                    <td><?= clean_input($r['jabatan']) ?></td>
                    <td><?= format_time($r['check_in']) ?></td>
                    <td><?= format_time($r['check_out']) ?></td>
                    <td>
                        <?php if ($r['status'] === 'hadir_tepat_waktu'): ?>
                            <span class="badge badge-success">Tepat Waktu</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Terlambat</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $r['late_minutes'] > 0 ? clean_input((string)$r['late_minutes']) . ' Menit' : '-' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<div class="signature-area">
    <div class="signature-box">
        <div>Mengetahui,</div>
        <div style="font-weight: 600; margin-top: 4px;">Pimpinan Perusahaan</div>
        <div class="signature-line">( Direktur Utama )</div>
    </div>
    <div class="signature-box">
        <div>Dibuat oleh,</div>
        <div style="font-weight: 600; margin-top: 4px;">Human Resources Dept</div>
        <div class="signature-line">( <?= clean_input($admin['nama_lengkap']) ?> )</div>
    </div>
</div>

</body>
</html>
