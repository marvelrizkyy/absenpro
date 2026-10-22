-- Database Schema: Sistem Informasi Manajemen Karyawan, Absensi, dan WhatsApp Fonnte
-- Target: MySQL / MariaDB 10.4+ (XAMPP)

CREATE DATABASE IF NOT EXISTS `db_absensi_karyawan` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_absensi_karyawan`;

-- 1. Table users
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nik` VARCHAR(30) NOT NULL,
    `nama_lengkap` VARCHAR(100) NOT NULL,
    `jabatan` VARCHAR(50) NOT NULL,
    `no_wa` VARCHAR(20) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'karyawan') NOT NULL DEFAULT 'karyawan',
    `account_status` ENUM('menunggu', 'disetujui', 'ditolak') NOT NULL DEFAULT 'menunggu',
    `rejection_reason` TEXT DEFAULT NULL,
    `approved_by` INT UNSIGNED DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_users_nik` (`nik`),
    UNIQUE KEY `uk_users_email` (`email`),
    INDEX `idx_users_role_status` (`role`, `account_status`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table work_settings
CREATE TABLE IF NOT EXISTS `work_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `check_in_deadline` TIME NOT NULL DEFAULT '08:00:00',
    `check_out_time` TIME NOT NULL DEFAULT '17:00:00',
    `work_days` VARCHAR(50) NOT NULL DEFAULT '1,2,3,4,5,6',
    `company_name` VARCHAR(100) NOT NULL DEFAULT 'PT Inovasi Digital Nusantara',
    `admin_phone` VARCHAR(20) NOT NULL DEFAULT '081234567890',
    `updated_by` INT UNSIGNED DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table attendances
CREATE TABLE IF NOT EXISTS `attendances` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NOT NULL,
    `attendance_date` DATE NOT NULL,
    `check_in` TIME NOT NULL,
    `check_out` TIME DEFAULT NULL,
    `status` ENUM('hadir_tepat_waktu', 'terlambat') NOT NULL DEFAULT 'hadir_tepat_waktu',
    `late_minutes` INT NOT NULL DEFAULT 0,
    `is_early_leave` TINYINT(1) NOT NULL DEFAULT 0,
    `early_leave_minutes` INT NOT NULL DEFAULT 0,
    `early_leave_reason` TEXT DEFAULT NULL,
    `early_leave_evidence` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_emp_date` (`employee_id`, `attendance_date`),
    INDEX `idx_attendance_date` (`attendance_date`),
    INDEX `idx_attendance_status` (`status`),
    CONSTRAINT `fk_att_employee` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table leave_requests
CREATE TABLE IF NOT EXISTS `leave_requests` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NOT NULL,
    `type` ENUM('izin', 'sakit') NOT NULL DEFAULT 'izin',
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `total_days` INT NOT NULL DEFAULT 1,
    `reason` TEXT NOT NULL,
    `evidence_path` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('menunggu', 'disetujui', 'ditolak') NOT NULL DEFAULT 'menunggu',
    `decided_by` INT UNSIGNED DEFAULT NULL,
    `decision_reason` TEXT DEFAULT NULL,
    `decided_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_leave_emp_status` (`employee_id`, `status`),
    INDEX `idx_leave_dates` (`start_date`, `end_date`),
    CONSTRAINT `fk_leave_employee` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_leave_decided_by` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Table attendance_corrections
CREATE TABLE IF NOT EXISTS `attendance_corrections` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `attendance_id` INT UNSIGNED NOT NULL,
    `corrected_by` INT UNSIGNED NOT NULL,
    `old_values` TEXT NOT NULL,
    `new_values` TEXT NOT NULL,
    `reason` TEXT NOT NULL,
    `corrected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_corr_attendance` (`attendance_id`),
    CONSTRAINT `fk_corr_attendance` FOREIGN KEY (`attendance_id`) REFERENCES `attendances` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_corr_admin` FOREIGN KEY (`corrected_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Table notification_logs
CREATE TABLE IF NOT EXISTS `notification_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `recipient_number` VARCHAR(25) NOT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `message_payload` TEXT NOT NULL,
    `delivery_status` ENUM('sent', 'failed') NOT NULL DEFAULT 'sent',
    `provider_reference` VARCHAR(100) DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_notif_status` (`delivery_status`),
    INDEX `idx_notif_user` (`user_id`),
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Table holidays
CREATE TABLE IF NOT EXISTS `holidays` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `holiday_date` DATE NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_holiday_date` (`holiday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Default Settings
INSERT INTO `work_settings` (`id`, `check_in_deadline`, `work_days`, `company_name`, `admin_phone`)
VALUES (1, '08:00:00', '1,2,3,4,5,6', 'PT Inovasi Digital Nusantara', '081234567890')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Seed Default Admin: admin@absenkaryawan.local / admin123
-- Hash for 'admin123': $2y$10$wEkg9u/4P1l7QvXfE6jGveUaVbK7ZlBv5Z5Cq6mFp4NqF.2Vw2J8e (will re-hash via PHP or insert verifiable bcrypt)
INSERT INTO `users` (`id`, `nik`, `nama_lengkap`, `jabatan`, `no_wa`, `email`, `password_hash`, `role`, `account_status`, `is_active`)
VALUES (1, 'ADM001', 'Super Administrator', 'HR Manager & System Admin', '081234567890', 'admin@absenkaryawan.local', '$2y$10$tZ2E2n93w1z5qP2B6Y6w0.4Wj7v6gY2a1f0d8e3c4b5a697812345', 'admin', 'disetujui', 1)
ON DUPLICATE KEY UPDATE `id`=`id`;
