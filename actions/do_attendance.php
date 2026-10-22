<?php
// actions/do_attendance.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/date_helper.php';
require_once __DIR__ . '/../helpers/sanitize_helper.php';
require_once __DIR__ . '/../helpers/fonnte_helper.php';

require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/employee_dashboard.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
$action = $_POST['action'] ?? '';

if (!verify_csrf_token($csrfToken)) {
    flash_set('danger', 'Sesi keamanan tidak valid. Silakan coba kembali.');
    header('Location: ' . BASE_URL . '/employee_dashboard.php');
    exit;
}

$db = get_db_connection();
$today = date('Y-m-d');
$nowTime = date('H:i:s');
$employeeId = (int)$user['id'];

// 1. Check if today is a working day
if (!is_work_day($today)) {
    $holidayName = get_holiday_name($today);
    $reason = $holidayName ? "Hari Libur Nasional ({$holidayName})" : "Hari Minggu / Bukan Hari Kerja";
    flash_set('warning', "Hari ini ({$reason}), absensi reguler tidak diperlukan.");
    header('Location: ' . BASE_URL . '/employee_dashboard.php');
    exit;
}

// 2. Check if employee has approved leave/sick covering today
$stmtLeave = $db->prepare("SELECT * FROM leave_requests 
    WHERE employee_id = ? AND status = 'disetujui' AND ? BETWEEN start_date AND end_date LIMIT 1");
$stmtLeave->execute([$employeeId, $today]);
$approvedLeave = $stmtLeave->fetch();

if ($approvedLeave) {
    flash_set('info', "Anda sedang dalam masa {$approvedLeave['type']} yang telah disetujui untuk tanggal hari ini.");
    header('Location: ' . BASE_URL . '/employee_dashboard.php');
    exit;
}

// Get work settings
$stmtSet = $db->query("SELECT * FROM work_settings WHERE id = 1");
$settings = $stmtSet->fetch() ?: [
    'check_in_deadline' => '08:00:00',
    'company_name' => 'PT Inovasi Digital Nusantara',
    'admin_phone' => '081234567890'
];
$deadlineTime = $settings['check_in_deadline'];
$companyName = $settings['company_name'];
$adminPhone = $settings['admin_phone'];

// Check existing attendance today
$stmtAtt = $db->prepare("SELECT * FROM attendances WHERE employee_id = ? AND attendance_date = ? LIMIT 1");
$stmtAtt->execute([$employeeId, $today]);
$existingAtt = $stmtAtt->fetch();

if ($action === 'check_in') {
    if ($existingAtt) {
        flash_set('warning', 'Anda sudah melakukan check-in hari ini pada pukul ' . format_time($existingAtt['check_in']));
        header('Location: ' . BASE_URL . '/employee_dashboard.php');
        exit;
    }

    // Determine status & late minutes
    $serverTimeObj = new DateTime($nowTime);
    $deadlineObj = new DateTime($deadlineTime);
    $status = 'hadir_tepat_waktu';
    $lateMinutes = 0;

    if ($serverTimeObj > $deadlineObj) {
        $status = 'terlambat';
        $diff = $serverTimeObj->diff($deadlineObj);
        $lateMinutes = ($diff->h * 60) + $diff->i;
        if ($diff->s > 0) $lateMinutes++; // round up if seconds passed
    }

    try {
        $stmtInsert = $db->prepare("INSERT INTO attendances 
            (employee_id, attendance_date, check_in, status, late_minutes, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())");
        $stmtInsert->execute([$employeeId, $today, $nowTime, $status, $lateMinutes]);

        // Send WhatsApp notification
        $statusLabel = ($status === 'hadir_tepat_waktu') ? '✅ Tepat Waktu' : "⚠️ Terlambat ({$lateMinutes} Menit)";
        $lateNote = ($status === 'terlambat') ? "\n• *Keterlambatan:* {$lateMinutes} Menit dari batas {$deadlineTime}" : "";

        $userMsg = "📌 *NOTIFIKASI CHECK-IN BERHASIL*\n\n"
                 . "Halo {$user['nama_lengkap']},\n"
                 . "Absensi masuk Anda telah tercatat pada sistem *{$companyName}*:\n\n"
                 . "• *Tanggal:* " . format_indo_date($today) . "\n"
                 . "• *Waktu Check-In:* " . date('H:i') . " WIB\n"
                 . "• *Status Kehadiran:* {$statusLabel}"
                 . "{$lateNote}\n\n"
                 . "Selamat beraktivitas dan utamakan keselamatan kerja!";
        send_whatsapp_notification($user['no_wa'], 'check_in_user', $userMsg, $employeeId);

        // If late, notify Admin as well!
        if ($status === 'terlambat' && !empty($adminPhone)) {
            $adminLateMsg = "⚠️ *PERINGATAN KARYAWAN TERLAMBAT*\n\n"
                          . "Pemberitahuan keterlambatan check-in karyawan:\n\n"
                          . "• *Nama:* {$user['nama_lengkap']} (NIK: {$user['nik']})\n"
                          . "• *Jabatan:* {$user['jabatan']}\n"
                          . "• *Waktu Masuk:* " . date('H:i') . " WIB\n"
                          . "• *Batas Masuk:* {$deadlineTime} WIB\n"
                          . "• *Keterlambatan:* {$lateMinutes} Menit\n\n"
                          . "Catatan kehadiran telah otomatis direkam di sistem.";
            send_whatsapp_notification($adminPhone, 'late_alert_admin', $adminLateMsg, $employeeId);
        }

        flash_set('success', "Check-In berhasil dicatat pada pukul {$nowTime} WIB ({$statusLabel})");
    } catch (Exception $e) {
        flash_set('danger', 'Gagal memproses check-in: ' . $e->getMessage());
    }

} elseif ($action === 'check_out') {
    if (!$existingAtt) {
        flash_set('danger', 'Anda belum melakukan check-in hari ini.');
        header('Location: ' . BASE_URL . '/employee_dashboard.php');
        exit;
    }

    if (!empty($existingAtt['check_out'])) {
        flash_set('warning', 'Anda sudah melakukan check-out hari ini pada pukul ' . format_time($existingAtt['check_out']));
        header('Location: ' . BASE_URL . '/employee_dashboard.php');
        exit;
    }

    try {
        $stmtUpdate = $db->prepare("UPDATE attendances SET check_out = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpdate->execute([$nowTime, $existingAtt['id']]);

        // Send WhatsApp check-out confirmation to employee
        $userMsg = "👋 *NOTIFIKASI CHECK-OUT (PULANG)*\n\n"
                 . "Halo {$user['nama_lengkap']},\n"
                 . "Absensi pulang Anda telah berhasil dicatat pada sistem *{$companyName}*:\n\n"
                 . "• *Tanggal:* " . format_indo_date($today) . "\n"
                 . "• *Waktu Pulang:* " . date('H:i') . " WIB\n\n"
                 . "Terima kasih atas dedikasi dan kerja keras Anda hari ini. Selamat beristirahat!";
        send_whatsapp_notification($user['no_wa'], 'check_out_user', $userMsg, $employeeId);

        flash_set('success', "Check-Out berhasil dicatat pada pukul {$nowTime} WIB. Selamat beristirahat!");
    } catch (Exception $e) {
        flash_set('danger', 'Gagal memproses check-out: ' . $e->getMessage());
    }
}

header('Location: ' . BASE_URL . '/employee_dashboard.php');
exit;
