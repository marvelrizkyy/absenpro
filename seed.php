<?php
// seed.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

try {
    $pdo = get_db_connection();

    // Update Admin password
    $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = 1')->execute([$adminPass]);

    $karyawanPass = password_hash('karyawan123', PASSWORD_BCRYPT);

    // Seed sample approved employee
    $stmt = $pdo->prepare('INSERT INTO users (nik, nama_lengkap, jabatan, no_wa, email, password_hash, role, account_status, is_active) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)');
    $stmt->execute(['KRY001', 'Budi Pratama, S.Kom', 'Senior Programmer', '081298765432', 'budi@absenkaryawan.local', $karyawanPass, 'karyawan', 'disetujui', 1]);

    // Seed sample pending employee
    $stmt->execute(['KRY002', 'Siti Rahmawati', 'UI/UX Designer', '081311223344', 'siti@absenkaryawan.local', $karyawanPass, 'karyawan', 'menunggu', 1]);

    // Seed sample national holidays
    $holidays = [
        ['2026-01-01', 'Tahun Baru Masehi'],
        ['2026-05-01', 'Hari Buruh Internasional'],
        ['2026-08-17', 'Hari Kemerdekaan RI'],
        ['2026-12-25', 'Hari Raya Natal']
    ];
    $stmtH = $pdo->prepare('INSERT IGNORE INTO holidays (holiday_date, name) VALUES (?, ?)');
    foreach ($holidays as $h) {
        $stmtH->execute($h);
    }

    echo "Seed completed successfully via .env configuration!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
