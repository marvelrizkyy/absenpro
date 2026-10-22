<?php
// actions/do_leave.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/date_helper.php';
require_once __DIR__ . '/../helpers/sanitize_helper.php';
require_once __DIR__ . '/../helpers/upload_helper.php';
require_once __DIR__ . '/../helpers/fonnte_helper.php';

require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/employee_leaves.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
$action = $_POST['action'] ?? '';

if (!verify_csrf_token($csrfToken)) {
    flash_set('danger', 'Sesi keamanan tidak sah.');
    header('Location: ' . BASE_URL . '/employee_leaves.php');
    exit;
}

$db = get_db_connection();
$employeeId = (int)$user['id'];

if ($action === 'create') {
    $type = $_POST['type'] ?? 'izin';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if (!in_array($type, ['izin', 'sakit'])) {
        flash_set('danger', 'Jenis pengajuan tidak valid.');
        header('Location: ' . BASE_URL . '/employee_leaves.php');
        exit;
    }

    if (empty($startDate) || empty($endDate)) {
        flash_set('danger', 'Tanggal mulai dan tanggal selesai wajib ditentukan.');
        header('Location: ' . BASE_URL . '/employee_leaves.php');
        exit;
    }

    if ($startDate > $endDate) {
        flash_set('danger', 'Tanggal mulai tidak boleh lebih besar dari tanggal selesai.');
        header('Location: ' . BASE_URL . '/employee_leaves.php');
        exit;
    }

    if (empty($reason) || strlen($reason) < 5) {
        flash_set('danger', 'Alasan pengajuan wajib diisi minimal 5 karakter.');
        header('Location: ' . BASE_URL . '/employee_leaves.php');
        exit;
    }

    // Check collision with already recorded attendance on same day
    $today = date('Y-m-d');
    if ($startDate <= $today && $today <= $endDate) {
        $stmtAtt = $db->prepare("SELECT id FROM attendances WHERE employee_id = ? AND attendance_date = ?");
        $stmtAtt->execute([$employeeId, $today]);
        if ($stmtAtt->fetch()) {
            flash_set('danger', 'Anda sudah melakukan check-in absensi untuk hari ini. Pengajuan izin/sakit pada tanggal ini ditolak.');
            header('Location: ' . BASE_URL . '/employee_leaves.php');
            exit;
        }
    }

    // Upload evidence document if provided
    $evidencePath = null;
    if (isset($_FILES['evidence']) && !empty($_FILES['evidence']['name'])) {
        $uploadResult = upload_evidence($_FILES['evidence']);
        if (!$uploadResult['success']) {
            flash_set('danger', $uploadResult['error']);
            header('Location: ' . BASE_URL . '/employee_leaves.php');
            exit;
        }
        $evidencePath = $uploadResult['filename'];
    }

    // Calculate effective working days
    $effectiveDays = calculate_effective_days($startDate, $endDate);

    try {
        $stmt = $db->prepare("INSERT INTO leave_requests 
            (employee_id, type, start_date, end_date, total_days, reason, evidence_path, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'menunggu', NOW())");
        $stmt->execute([$employeeId, $type, $startDate, $endDate, $effectiveDays, $reason, $evidencePath]);
        $leaveId = (int)$db->lastInsertId();

        // Notify Admin via Fonnte
        $stmtSet = $db->query("SELECT admin_phone, company_name FROM work_settings WHERE id = 1");
        $settings = $stmtSet->fetch();
        $adminPhone = $settings['admin_phone'] ?? '081234567890';

        if (!empty($adminPhone)) {
            $adminMsg = "📋 *PENGAJUAN IZIN / SAKIT BARU*\n\n"
                      . "Admin, terdapat permohonan baru yang memerlukan persetujuan:\n\n"
                      . "• *Nama:* {$user['nama_lengkap']} (NIK: {$user['nik']})\n"
                      . "• *Jenis:* " . strtoupper($type) . "\n"
                      . "• *Periode:* " . format_indo_date($startDate) . " s/d " . format_indo_date($endDate) . " ({$effectiveDays} hari kerja)\n"
                      . "• *Alasan:* {$reason}\n"
                      . "• *Lampiran Bukti:* " . ($evidencePath ? 'Ada Dokumen' : 'Tidak Ada Dokumen') . "\n\n"
                      . "Harap periksa dan berikan keputusan melalui panel admin.";
            send_whatsapp_notification($adminPhone, 'leave_request_admin', $adminMsg, $employeeId);
        }

        flash_set('success', "Permohonan {$type} berhasil diajukan dan sedang menunggu persetujuan Admin.");
    } catch (Exception $e) {
        flash_set('danger', 'Gagal menyimpan pengajuan: ' . $e->getMessage());
    }

} elseif ($action === 'cancel') {
    $requestId = (int)($_POST['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM leave_requests WHERE id = ? AND employee_id = ?");
    $stmt->execute([$requestId, $employeeId]);
    $leave = $stmt->fetch();

    if (!$leave) {
        flash_set('danger', 'Data pengajuan tidak ditemukan.');
    } elseif ($leave['status'] !== 'menunggu') {
        flash_set('danger', 'Pengajuan yang sudah disetujui atau ditolak tidak dapat dibatalkan.');
    } else {
        $db->prepare("DELETE FROM leave_requests WHERE id = ?")->execute([$requestId]);
        flash_set('success', 'Pengajuan berhasil dibatalkan.');
    }
}

header('Location: ' . BASE_URL . '/employee_leaves.php');
exit;
