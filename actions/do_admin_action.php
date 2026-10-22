<?php
// actions/do_admin_action.php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/date_helper.php';
require_once __DIR__ . '/../helpers/sanitize_helper.php';
require_once __DIR__ . '/../helpers/fonnte_helper.php';

require_admin();
$admin = current_user();
$adminId = (int)$admin['id'];
$db = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin_dashboard.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
$action = $_POST['action'] ?? '';

if (!verify_csrf_token($csrfToken)) {
    flash_set('danger', 'Sesi keamanan tidak sah.');
    header('Location: ' . BASE_URL . '/admin_dashboard.php');
    exit;
}

// Get work settings
$stmtSet = $db->query("SELECT company_name, check_in_deadline FROM work_settings WHERE id = 1");
$settings = $stmtSet->fetch();
$companyName = $settings['company_name'] ?? 'PT Inovasi Digital Nusantara';

// -------------------------------------------------------------
// 1. APPROVE USER REGISTRATION
// -------------------------------------------------------------
if ($action === 'approve_user') {
    $targetId = (int)($_POST['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$targetId]);
    $targetUser = $stmt->fetch();

    if (!$targetUser) {
        flash_set('danger', 'Karyawan tidak ditemukan.');
    } else {
        $stmtApprove = $db->prepare("UPDATE users SET account_status = 'disetujui', approved_by = ?, approved_at = NOW(), rejection_reason = NULL WHERE id = ?");
        $stmtApprove->execute([$adminId, $targetId]);

        // WhatsApp to employee
        $msg = "✅ *AKUN BERHASIL DISETUJUI*\n\n"
             . "Halo {$targetUser['nama_lengkap']},\n"
             . "Pendaftaran akun Anda di *{$companyName}* telah disetujui oleh Administrator.\n\n"
             . "• *NIK:* {$targetUser['nik']}\n"
             . "• *Jabatan:* {$targetUser['jabatan']}\n"
             . "• *Email Login:* {$targetUser['email']}\n\n"
             . "Sekarang Anda dapat login dan melakukan absensi harian melalui portal absensi.\n"
             . "Selamat bertugas!";
        send_whatsapp_notification($targetUser['no_wa'], 'account_approved', $msg, $targetId);

        flash_set('success', "Akun karyawan '{$targetUser['nama_lengkap']}' berhasil disetujui!");
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/admin_employees.php')));
    exit;
}

// -------------------------------------------------------------
// 2. REJECT USER REGISTRATION
// -------------------------------------------------------------
elseif ($action === 'reject_user') {
    $targetId = (int)($_POST['id'] ?? 0);
    $reason = trim($_POST['rejection_reason'] ?? 'Persyaratan data belum memenuhi kriteria perusahaan.');

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$targetId]);
    $targetUser = $stmt->fetch();

    if ($targetUser) {
        $stmtReject = $db->prepare("UPDATE users SET account_status = 'ditolak', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?");
        $stmtReject->execute([$adminId, $reason, $targetId]);

        // WhatsApp to employee
        $msg = "❌ *PENDAFTARAN AKUN DITOLAK*\n\n"
             . "Halo {$targetUser['nama_lengkap']},\n"
             . "Mohon maaf, permohonan akun Anda di *{$companyName}* belum dapat disetujui oleh HR/Admin.\n\n"
             . "• *Alasan Penolakan:* {$reason}\n\n"
             . "Silakan menghubungi pihak HR untuk informasi lebih lanjut.";
        send_whatsapp_notification($targetUser['no_wa'], 'account_rejected', $msg, $targetId);

        flash_set('warning', "Pendaftaran '{$targetUser['nama_lengkap']}' telah ditolak.");
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/admin_employees.php')));
    exit;
}

// -------------------------------------------------------------
// 3. TOGGLE ARCHIVE USER (SOFT DELETE)
// -------------------------------------------------------------
elseif ($action === 'toggle_archive_user') {
    $targetId = (int)($_POST['id'] ?? 0);
    if ($targetId === $adminId) {
        flash_set('danger', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
    } else {
        $stmt = $db->prepare("SELECT is_active, nama_lengkap FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $targetUser = $stmt->fetch();
        if ($targetUser) {
            $newStatus = $targetUser['is_active'] == 1 ? 0 : 1;
            $db->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $targetId]);
            $label = $newStatus == 1 ? 'diaktifkan kembali' : 'diarsipkan (dinonaktifkan)';
            flash_set('success', "Akun '{$targetUser['nama_lengkap']}' berhasil {$label}. Histori absensi tetap tersimpan aman.");
        }
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/admin_employees.php')));
    exit;
}

// -------------------------------------------------------------
// 3b. DELETE EMPLOYEE PERMANENTLY
// -------------------------------------------------------------
elseif ($action === 'delete_user') {
    $targetId = (int)($_POST['id'] ?? 0);
    if ($targetId === $adminId) {
        flash_set('danger', 'Anda tidak dapat menghapus akun Anda sendiri.');
        header('Location: ' . BASE_URL . '/admin_employees.php');
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$targetId]);
    $targetUser = $stmt->fetch();

    if (!$targetUser) {
        flash_set('danger', 'Karyawan tidak ditemukan.');
        header('Location: ' . BASE_URL . '/admin_employees.php');
        exit;
    }

    if ($targetUser['role'] === 'admin') {
        flash_set('danger', 'Akun Administrator tidak dapat dihapus melalui fitur ini.');
        header('Location: ' . BASE_URL . '/admin_employees.php');
        exit;
    }

    try {
        $db->beginTransaction();

        // 1. Hapus file fisik lampiran bukti izin/sakit jika ada
        $stmtLeaves = $db->prepare("SELECT evidence_path FROM leave_requests WHERE employee_id = ?");
        $stmtLeaves->execute([$targetId]);
        while ($ev = $stmtLeaves->fetchColumn()) {
            if (!empty($ev)) {
                $filePath = BASE_PATH . '/uploads/evidence/' . $ev;
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
        }

        // 2. Hapus data koreksi absensi terkait absensi karyawan ini
        $stmtAtt = $db->prepare("SELECT id FROM attendances WHERE employee_id = ?");
        $stmtAtt->execute([$targetId]);
        $attIds = $stmtAtt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($attIds)) {
            $inClause = implode(',', array_map('intval', $attIds));
            $db->exec("DELETE FROM attendance_corrections WHERE attendance_id IN ({$inClause})");
        }

        // 3. Hapus data absensi
        $db->prepare("DELETE FROM attendances WHERE employee_id = ?")->execute([$targetId]);

        // 4. Hapus data pengajuan izin/cuti
        $db->prepare("DELETE FROM leave_requests WHERE employee_id = ?")->execute([$targetId]);

        // 5. Lepaskan referensi pada tabel lain jika karyawan pernah menjadi admin/approver
        $db->prepare("UPDATE users SET approved_by = NULL WHERE approved_by = ?")->execute([$targetId]);
        $db->prepare("UPDATE leave_requests SET decided_by = NULL WHERE decided_by = ?")->execute([$targetId]);
        $db->prepare("UPDATE work_settings SET updated_by = NULL WHERE updated_by = ?")->execute([$targetId]);
        $db->prepare("UPDATE notification_logs SET user_id = NULL WHERE user_id = ?")->execute([$targetId]);

        // 6. Hapus user utama
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);

        $db->commit();

        // 7. Kirim notifikasi WhatsApp ke nomor karyawan yang dihapus & tembusan ke nomor Admin
        if (!empty($targetUser['no_wa'])) {
            $deleteMsg = "⚠️ *PEMBERITAHUAN PENGHAPUSAN AKUN*\n\n"
                       . "Halo {$targetUser['nama_lengkap']},\n"
                       . "Data akun dan keanggotaan Anda di *{$companyName}* telah *DIHAPUS* secara permanen oleh Administrator.\n\n"
                       . "• *NIK:* {$targetUser['nik']}\n"
                       . "• *Jabatan:* {$targetUser['jabatan']}\n"
                       . "• *Waktu Penghapusan:* " . date('d/m/Y H:i') . " WIB\n\n"
                       . "Akses Anda ke portal absensi telah ditutup.\n"
                       . "Terima kasih atas kerja sama dan dedikasi Anda selama ini.";
            send_whatsapp_notification($targetUser['no_wa'], 'account_deleted', $deleteMsg, null);
        }

        flash_set('success', "Data karyawan '{$targetUser['nama_lengkap']}' (NIK: {$targetUser['nik']}) beserta seluruh riwayatnya berhasil dihapus secara permanen.");
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        flash_set('danger', 'Gagal menghapus data karyawan: ' . $e->getMessage());
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (strpos($referer, 'admin_employee_detail.php') !== false) {
        header('Location: ' . BASE_URL . '/admin_employees.php');
    } else {
        header('Location: ' . ($referer ?: (BASE_URL . '/admin_employees.php')));
    }
    exit;
}

// -------------------------------------------------------------
// 4. SAVE / EDIT EMPLOYEE BY ADMIN
// -------------------------------------------------------------
elseif ($action === 'save_employee') {
    $targetId = (int)($_POST['id'] ?? 0);
    $nik = trim($_POST['nik'] ?? '');
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $jabatan = trim($_POST['jabatan'] ?? '');
    $noWa = trim($_POST['no_wa'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['admin', 'karyawan']) ? $_POST['role'] : 'karyawan';
    $password = $_POST['password'] ?? '';

    if (empty($nik) || empty($nama) || empty($jabatan) || empty($noWa) || empty($email)) {
        flash_set('danger', 'Seluruh kolom identitas wajib diisi.');
        header('Location: ' . BASE_URL . '/admin_employees.php');
        exit;
    }

    if ($targetId > 0) {
        // Edit existing
        $stmtUnique = $db->prepare("SELECT COUNT(*) FROM users WHERE (nik = ? OR email = ?) AND id != ?");
        $stmtUnique->execute([$nik, $email, $targetId]);
        if ($stmtUnique->fetchColumn() > 0) {
            flash_set('danger', 'NIK atau Email sudah digunakan oleh karyawan lain.');
        } else {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET nik = ?, nama_lengkap = ?, jabatan = ?, no_wa = ?, email = ?, role = ?, password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$nik, $nama, $jabatan, $noWa, $email, $role, $hash, $targetId]);
            } else {
                $stmt = $db->prepare("UPDATE users SET nik = ?, nama_lengkap = ?, jabatan = ?, no_wa = ?, email = ?, role = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$nik, $nama, $jabatan, $noWa, $email, $role, $targetId]);
            }

            // Kirim notifikasi WhatsApp pembaruan data ke karyawan & tembusan ke Admin
            $passNote = !empty($password) ? "\n• *Kata Sandi Baru:* Telah diperbarui oleh Admin" : "";
            $editMsg = "📝 *PEMBARUAN DATA KARYAWAN*\n\n"
                     . "Halo {$nama},\n"
                     . "Data profil/kepegawaian Anda di *{$companyName}* telah *DIPERBARUI* oleh Administrator:\n\n"
                     . "• *NIK:* {$nik}\n"
                     . "• *Nama:* {$nama}\n"
                     . "• *Jabatan:* {$jabatan}\n"
                     . "• *No. WhatsApp:* {$noWa}\n"
                     . "• *Email:* {$email}"
                     . "{$passNote}\n"
                     . "• *Waktu Perubahan:* " . date('d/m/Y H:i') . " WIB\n\n"
                     . "Silakan login ke portal absensi untuk memastikan data Anda sudah sesuai.";
            send_whatsapp_notification($noWa, 'employee_updated', $editMsg, $targetId);

            flash_set('success', "Data karyawan '{$nama}' berhasil diperbarui.");
        }
    } else {
        // Create new
        $stmtUnique = $db->prepare("SELECT COUNT(*) FROM users WHERE nik = ? OR email = ?");
        $stmtUnique->execute([$nik, $email]);
        if ($stmtUnique->fetchColumn() > 0) {
            flash_set('danger', 'NIK atau Email sudah terdaftar.');
        } else {
            $passInitial = !empty($password) ? $password : 'karyawan123';
            $hash = password_hash($passInitial, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users 
                (nik, nama_lengkap, jabatan, no_wa, email, password_hash, role, account_status, approved_by, approved_at, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'disetujui', ?, NOW(), 1, NOW())");
            $stmt->execute([$nik, $nama, $jabatan, $noWa, $email, $hash, $role, $adminId]);
            $newId = (int)$db->lastInsertId();

            // Kirim notifikasi WhatsApp pembuatan akun baru ke karyawan & tembusan ke Admin
            $createMsg = "🎉 *AKUN KARYAWAN BARU DIBUAT*\n\n"
                       . "Halo {$nama},\n"
                       . "Akun Anda telah dibuatkan oleh Administrator di *{$companyName}*:\n\n"
                       . "• *NIK:* {$nik}\n"
                       . "• *Jabatan:* {$jabatan}\n"
                       . "• *Email Login:* {$email}\n"
                       . "• *Kata Sandi Awal:* {$passInitial}\n\n"
                       . "Silakan gunakan data di atas untuk login ke portal absensi dan segera ubah kata sandi Anda di menu Profil demi keamanan.";
            send_whatsapp_notification($noWa, 'employee_created', $createMsg, $newId);

            flash_set('success', "Karyawan baru '{$nama}' berhasil ditambahkan dengan kata sandi: {$passInitial}");
        }
    }
    header('Location: ' . BASE_URL . '/admin_employees.php');
    exit;
}

// -------------------------------------------------------------
// 5. APPROVE LEAVE REQUEST
// -------------------------------------------------------------
elseif ($action === 'approve_leave') {
    $leaveId = (int)($_POST['id'] ?? 0);
    $decisionNote = trim($_POST['decision_reason'] ?? 'Disetujui oleh Administrator');

    $stmt = $db->prepare("SELECT lr.*, u.nama_lengkap, u.no_wa FROM leave_requests lr JOIN users u ON lr.employee_id = u.id WHERE lr.id = ?");
    $stmt->execute([$leaveId]);
    $leave = $stmt->fetch();

    if ($leave) {
        $stmtApprove = $db->prepare("UPDATE leave_requests SET status = 'disetujui', decided_by = ?, decision_reason = ?, decided_at = NOW() WHERE id = ?");
        $stmtApprove->execute([$adminId, $decisionNote, $leaveId]);

        // WhatsApp to employee
        $msg = "✅ *PERMOHONAN " . strtoupper($leave['type']) . " DISETUJUI*\n\n"
             . "Halo {$leave['nama_lengkap']},\n"
             . "Pengajuan " . strtoupper($leave['type']) . " Anda telah *DISETUJUI* oleh Admin:\n\n"
             . "• *Periode:* " . format_indo_date($leave['start_date']) . " s/d " . format_indo_date($leave['end_date']) . " ({$leave['total_days']} Hari)\n"
             . "• *Catatan:* {$decisionNote}\n\n"
             . "Catatan kehadiran pada tanggal tersebut telah disesuaikan.";
        send_whatsapp_notification($leave['no_wa'], 'leave_approved', $msg, (int)$leave['employee_id']);

        flash_set('success', "Pengajuan {$leave['type']} '{$leave['nama_lengkap']}' telah disetujui.");
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/admin_leaves.php')));
    exit;
}

// -------------------------------------------------------------
// 6. REJECT LEAVE REQUEST
// -------------------------------------------------------------
elseif ($action === 'reject_leave') {
    $leaveId = (int)($_POST['id'] ?? 0);
    $decisionNote = trim($_POST['decision_reason'] ?? '');

    if (empty($decisionNote)) {
        flash_set('danger', 'Alasan penolakan izin/sakit wajib diisi.');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/admin_leaves.php')));
        exit;
    }

    $stmt = $db->prepare("SELECT lr.*, u.nama_lengkap, u.no_wa FROM leave_requests lr JOIN users u ON lr.employee_id = u.id WHERE lr.id = ?");
    $stmt->execute([$leaveId]);
    $leave = $stmt->fetch();

    if ($leave) {
        $stmtReject = $db->prepare("UPDATE leave_requests SET status = 'ditolak', decided_by = ?, decision_reason = ?, decided_at = NOW() WHERE id = ?");
        $stmtReject->execute([$adminId, $decisionNote, $leaveId]);

        // WhatsApp to employee
        $msg = "❌ *PERMOHONAN " . strtoupper($leave['type']) . " DITOLAK*\n\n"
             . "Halo {$leave['nama_lengkap']},\n"
             . "Pengajuan " . strtoupper($leave['type']) . " Anda periode " . format_short_date($leave['start_date']) . " s/d " . format_short_date($leave['end_date']) . " telah *DITOLAK* oleh Admin:\n\n"
             . "• *Alasan Penolakan:* {$decisionNote}\n\n"
             . "Silakan berkoordinasi langsung dengan pihak HR jika diperlukan.";
        send_whatsapp_notification($leave['no_wa'], 'leave_rejected', $msg, (int)$leave['employee_id']);

        flash_set('warning', "Pengajuan {$leave['type']} '{$leave['nama_lengkap']}' telah ditolak.");
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/admin_leaves.php')));
    exit;
}

// -------------------------------------------------------------
// 7. CORRECT ATTENDANCE (WITH MANDATORY REASON & AUDIT TRAIL)
// -------------------------------------------------------------
elseif ($action === 'correct_attendance') {
    $attendanceId = (int)($_POST['attendance_id'] ?? 0);
    $newCheckIn = trim($_POST['check_in'] ?? '');
    $newCheckOut = trim($_POST['check_out'] ?? '');
    $reason = trim($_POST['correction_reason'] ?? '');

    if (empty($reason) || strlen($reason) < 6) {
        flash_set('danger', 'Alasan koreksi absensi wajib diisi minimal 6 karakter demi audit trail.');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/admin_attendance.php')));
        exit;
    }

    $stmtAtt = $db->prepare("SELECT a.*, u.nama_lengkap, u.no_wa FROM attendances a JOIN users u ON a.employee_id = u.id WHERE a.id = ?");
    $stmtAtt->execute([$attendanceId]);
    $oldAtt = $stmtAtt->fetch();

    if (!$oldAtt) {
        flash_set('danger', 'Data absensi tidak ditemukan.');
    } else {
        // Recalculate status and late minutes based on settings deadline
        $deadlineTime = $settings['check_in_deadline'] ?? '08:00:00';
        $serverTimeObj = new DateTime($newCheckIn);
        $deadlineObj = new DateTime($deadlineTime);
        $newStatus = 'hadir_tepat_waktu';
        $newLateMinutes = 0;

        if ($serverTimeObj > $deadlineObj) {
            $newStatus = 'terlambat';
            $diff = $serverTimeObj->diff($deadlineObj);
            $newLateMinutes = ($diff->h * 60) + $diff->i;
        }

        $oldValuesJson = json_encode([
            'check_in' => $oldAtt['check_in'],
            'check_out' => $oldAtt['check_out'],
            'status' => $oldAtt['status'],
            'late_minutes' => $oldAtt['late_minutes']
        ]);

        $newValuesJson = json_encode([
            'check_in' => $newCheckIn,
            'check_out' => !empty($newCheckOut) ? $newCheckOut : null,
            'status' => $newStatus,
            'late_minutes' => $newLateMinutes
        ]);

        // Update attendances table
        $stmtUp = $db->prepare("UPDATE attendances SET check_in = ?, check_out = ?, status = ?, late_minutes = ?, updated_at = NOW() WHERE id = ?");
        $stmtUp->execute([
            $newCheckIn,
            !empty($newCheckOut) ? $newCheckOut : null,
            $newStatus,
            $newLateMinutes,
            $attendanceId
        ]);

        // Insert into attendance_corrections audit trail
        $stmtCorr = $db->prepare("INSERT INTO attendance_corrections 
            (attendance_id, corrected_by, old_values, new_values, reason, corrected_at) 
            VALUES (?, ?, ?, ?, ?, NOW())");
        $stmtCorr->execute([$attendanceId, $adminId, $oldValuesJson, $newValuesJson, $reason]);

        // Notify employee via WhatsApp about the correction
        $msg = "✏️ *PEMBERITAHUAN KOREKSI ABSENSI*\n\n"
             . "Halo {$oldAtt['nama_lengkap']},\n"
             . "Data absensi Anda pada tanggal *" . format_indo_date($oldAtt['attendance_date']) . "* telah dikoreksi oleh Admin:\n\n"
             . "• *Jam Masuk Baru:* {$newCheckIn} WIB\n"
             . "• *Jam Pulang Baru:* " . (!empty($newCheckOut) ? "{$newCheckOut} WIB" : "-") . "\n"
             . "• *Status Baru:* " . ($newStatus === 'hadir_tepat_waktu' ? 'Tepat Waktu' : "Terlambat ({$newLateMinutes} Menit)") . "\n"
             . "• *Alasan Koreksi:* {$reason}\n\n"
             . "Koreksi ini telah tercatat secara resmi pada audit log perusahaan.";
        send_whatsapp_notification($oldAtt['no_wa'], 'attendance_corrected', $msg, (int)$oldAtt['employee_id']);

        flash_set('success', "Absensi '{$oldAtt['nama_lengkap']}' tanggal " . format_indo_date($oldAtt['attendance_date']) . " berhasil dikoreksi dan dicatat ke audit log.");
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/admin_attendance.php')));
    exit;
}

// -------------------------------------------------------------
// 8. RESEND FONNTE NOTIFICATION
// -------------------------------------------------------------
elseif ($action === 'resend_fonnte') {
    $logId = (int)($_POST['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM notification_logs WHERE id = ?");
    $stmt->execute([$logId]);
    $log = $stmt->fetch();

    if ($log) {
        $res = send_whatsapp_notification($log['recipient_number'], $log['event_type'] . '_resend', $log['message_payload'], $log['user_id'] ? (int)$log['user_id'] : null);
        if ($res['success']) {
            flash_set('success', 'Pesan WhatsApp berhasil dikirim ulang via Fonnte.');
        } else {
            flash_set('danger', 'Gagal mengirim ulang pesan: ' . ($res['error'] ?? $res['message']));
        }
    }
    header('Location: ' . BASE_URL . '/admin_fonnte_logs.php');
    exit;
}

header('Location: ' . BASE_URL . '/admin_dashboard.php');
exit;
