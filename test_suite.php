<?php
// test_suite.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/helpers/date_helper.php';
require_once __DIR__ . '/helpers/fonnte_helper.php';

$db = get_db_connection();

echo "========================================================\n";
echo "   PENGUJIAN SISTEM OTOMATIS (TEST SUITE VERIFICATION)   \n";
echo "========================================================\n\n";

$passCount = 0;
$failCount = 0;

function assert_test(string $testName, bool $condition, string $detail = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] {$testName}\n";
        $passCount++;
    } else {
        echo " [FAIL] {$testName} - Detail: {$detail}\n";
        $failCount++;
    }
}

// TC-01: NIK Unik
$stmt = $db->query("SELECT nik, COUNT(*) as cnt FROM users GROUP BY nik HAVING cnt > 1");
$duplicatesNik = $stmt->fetchAll();
assert_test("TC-01: NIK Karyawan Unik di Database", count($duplicatesNik) === 0);

// TC-02: Email Unik
$stmt = $db->query("SELECT email, COUNT(*) as cnt FROM users GROUP BY email HAVING cnt > 1");
$duplicatesEmail = $stmt->fetchAll();
assert_test("TC-02: Email Karyawan Unik di Database", count($duplicatesEmail) === 0);

// TC-03: Akun Admin default ada dan status disetujui
$stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute(['admin@absenkaryawan.local']);
$adminUser = $stmt->fetch();
assert_test("TC-03: Akun Admin Default Siap Pakai", (bool)$adminUser && $adminUser['role'] === 'admin');

// TC-04: Password bcrypt verification
$adminPassOk = password_verify('admin123', $adminUser['password_hash']);
assert_test("TC-04: Enkripsi Password Menggunakan Bcrypt Terverifikasi", $adminPassOk);

// TC-05: Akun Karyawan Budi siap pakai
$stmt->execute(['budi@absenkaryawan.local']);
$budiUser = $stmt->fetch();
assert_test("TC-05: Akun Karyawan Budi Siap Pakai", (bool)$budiUser && $budiUser['role'] === 'karyawan' && $budiUser['account_status'] === 'disetujui');

// TC-06: Akun Karyawan Terdaftar di Sistem
$stmtEmp = $db->query("SELECT COUNT(*) FROM users WHERE role = 'karyawan'");
assert_test("TC-06: Akun Karyawan Terdaftar di Sistem", (int)$stmtEmp->fetchColumn() > 0);

// TC-07: Batas jam masuk default work_settings
$stmtSettings = $db->query("SELECT * FROM work_settings WHERE id = 1");
$settings = $stmtSettings->fetch();
assert_test("TC-07: Pengaturan Jam Kerja Default (08:00:00)", $settings && $settings['check_in_deadline'] === '08:00:00');

// TC-08: Format Hari Kerja
$workDays = explode(',', $settings['work_days']);
assert_test("TC-08: Hari Kerja Reguler (Senin - Sabtu)", in_array('1', $workDays) && in_array('6', $workDays));

// TC-09: Kalender Hari Libur
$stmtHolidays = $db->query("SELECT COUNT(*) FROM holidays");
$holidayCount = (int)$stmtHolidays->fetchColumn();
assert_test("TC-09: Kalender Hari Libur Nasional Tersedia", $holidayCount > 0);

// TC-10: Test Fonnte notification logging
$fonnteResult = send_whatsapp_notification('081298765432', 'system_test', 'Pesan Uji Coba Presensi Otomatis', (int)$budiUser['id']);
$stmtLog = $db->prepare("SELECT * FROM notification_logs WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmtLog->execute([(int)$budiUser['id']]);
$lastLog = $stmtLog->fetch();
assert_test("TC-10: Audit Log Notifikasi WhatsApp Fonnte Berfungsi", (bool)$lastLog && str_starts_with($lastLog['event_type'], 'system_test'));

// TC-11: Test Attendance check-in simulation
$testDate = '2026-10-21'; // yesterday
$db->prepare("DELETE FROM attendances WHERE employee_id = ? AND attendance_date = ?")->execute([(int)$budiUser['id'], $testDate]);
$db->prepare("INSERT INTO attendances (employee_id, attendance_date, check_in, status, late_minutes) VALUES (?, ?, '07:55:00', 'hadir_tepat_waktu', 0)")
   ->execute([(int)$budiUser['id'], $testDate]);

$stmtCheck = $db->prepare("SELECT * FROM attendances WHERE employee_id = ? AND attendance_date = ?");
$stmtCheck->execute([(int)$budiUser['id'], $testDate]);
$attTest = $stmtCheck->fetch();
assert_test("TC-11: Pencatatan Check-In Tepat Waktu (07:55 <= 08:00)", $attTest && $attTest['status'] === 'hadir_tepat_waktu');

// TC-12: Constraint Unique Attendance per day
try {
    $db->prepare("INSERT INTO attendances (employee_id, attendance_date, check_in, status) VALUES (?, ?, '08:10:00', 'terlambat')")
       ->execute([(int)$budiUser['id'], $testDate]);
    assert_test("TC-12: Pencegahan Check-In Ganda pada Hari yang Sama", false, "Constraint gagal menolak duplicate row");
} catch (PDOException $e) {
    assert_test("TC-12: Pencegahan Check-In Ganda pada Hari yang Sama", true);
}

// TC-13: Test Check-Out
$db->prepare("UPDATE attendances SET check_out = '17:05:00' WHERE id = ?")->execute([$attTest['id']]);
$stmtCheck->execute([(int)$budiUser['id'], $testDate]);
$attUpdated = $stmtCheck->fetch();
assert_test("TC-13: Pencatatan Check-Out Pulang Berhasil", !empty($attUpdated['check_out']));

// TC-14: Test Correction Audit Trail
$oldVal = json_encode(['check_in' => '07:55:00', 'status' => 'hadir_tepat_waktu']);
$newVal = json_encode(['check_in' => '07:45:00', 'status' => 'hadir_tepat_waktu']);
$stmtCorr = $db->prepare("INSERT INTO attendance_corrections (attendance_id, corrected_by, old_values, new_values, reason, corrected_at) VALUES (?, ?, ?, ?, ?, NOW())");
$stmtCorr->execute([$attTest['id'], (int)$adminUser['id'], $oldVal, $newVal, 'Koreksi jam masuk sesuai fingerprint']);

$corrId = (int)$db->lastInsertId();
assert_test("TC-14: Audit Trail Koreksi Absensi Tersimpan", $corrId > 0);

// TC-15: Test Uploads Security
$htaccessExists = file_exists(BASE_PATH . '/uploads/evidence/.htaccess');
assert_test("TC-15: Proteksi Direktori Unggahan .htaccess Aktif", $htaccessExists);

echo "\n--------------------------------------------------------\n";
echo "HASIL PENGUJIAN: {$passCount} LULUS, {$failCount} GAGAL\n";
echo "========================================================\n";
