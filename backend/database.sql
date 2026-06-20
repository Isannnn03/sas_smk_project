-- =============================================================================
-- SMART ATTENDANCE SYSTEM (SAS) - SMK
-- Database Schema
-- Engine : MySQL 8.0+
-- Charset: utf8mb4
-- =============================================================================

-- Buat dan gunakan database
CREATE DATABASE IF NOT EXISTS `sas_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `sas_db`;

-- =============================================================================
-- TABEL: users
-- Menyimpan akun login untuk Tata Usaha dan Guru.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `username`   VARCHAR(100)    NOT NULL UNIQUE,
    `password`   VARCHAR(255)    NOT NULL,                  -- Disimpan dalam format bcrypt hash
    `role`       ENUM('tata_usaha', 'guru') NOT NULL,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABEL: classes
-- Menyimpan data kelas (misal: X RPL 1, XI TKJ 2, dsb.)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `classes` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100)    NOT NULL UNIQUE,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABEL: students
-- Menyimpan data siswa beserta QR Code unik dan status keaktifan.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `students` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `nis`        VARCHAR(20)     NOT NULL UNIQUE,           -- Nomor Induk Siswa
    `name`       VARCHAR(150)    NOT NULL,
    `class_id`   INT UNSIGNED    NOT NULL,
    `qr_code`    VARCHAR(255)    NOT NULL UNIQUE,           -- Hash unik untuk QR Code
    `status`     ENUM('aktif', 'non_aktif') NOT NULL DEFAULT 'aktif',
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_students_class`
        FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABEL: teachers
-- Menyimpan data guru/pengajar.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `teachers` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `nip`        VARCHAR(30)     NOT NULL UNIQUE,           -- Nomor Induk Pegawai
    `name`       VARCHAR(150)    NOT NULL,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABEL: subjects
-- Menyimpan data mata pelajaran.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `subjects` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(150)    NOT NULL,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABEL: schedules
-- Menyimpan jadwal pelajaran: relasi kelas, guru, mapel, hari & waktu.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `schedules` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `class_id`    INT UNSIGNED    NOT NULL,
    `teacher_id`  INT UNSIGNED    NOT NULL,
    `subject_id`  INT UNSIGNED    NOT NULL,
    `day`         ENUM('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu') NOT NULL,
    `start_time`  TIME            NOT NULL,
    `end_time`    TIME            NOT NULL,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_schedules_class`
        FOREIGN KEY (`class_id`)   REFERENCES `classes`  (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_schedules_teacher`
        FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_schedules_subject`
        FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABEL: attendance_sessions
-- Setiap pertemuan/sesi absensi terikat ke satu jadwal dan satu tanggal.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `attendance_sessions` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `schedule_id` INT UNSIGNED    NOT NULL,
    `date`        DATE            NOT NULL,
    `status`      ENUM('aktif', 'ditutup') NOT NULL DEFAULT 'aktif',
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Satu jadwal hanya boleh punya satu sesi aktif per tanggal
    UNIQUE KEY `uq_session_schedule_date` (`schedule_id`, `date`),
    CONSTRAINT `fk_sessions_schedule`
        FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABEL: attendances
-- Menyimpan data kehadiran per siswa per sesi.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `attendances` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `session_id`  INT UNSIGNED    NOT NULL,
    `student_id`  INT UNSIGNED    NOT NULL,
    `status`      ENUM('hadir', 'alpha', 'izin', 'sakit', 'terlambat') NOT NULL,
    `scan_time`   TIMESTAMP       NULL DEFAULT NULL,        -- NULL jika statusnya alpha/manual
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Satu siswa hanya boleh punya satu record per sesi
    UNIQUE KEY `uq_attendance_session_student` (`session_id`, `student_id`),
    CONSTRAINT `fk_attendances_session`
        FOREIGN KEY (`session_id`)  REFERENCES `attendance_sessions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_attendances_student`
        FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- DATA AWAL (SEED) - Untuk keperluan pengujian
-- =============================================================================

-- Seed: User default (password: 'password123' dalam bcrypt)
INSERT INTO `users` (`username`, `password`, `role`) VALUES
('admin_tu', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tata_usaha'),
('guru_budi', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'guru');

-- Seed: Kelas
INSERT INTO `classes` (`name`) VALUES ('X RPL 1'), ('X RPL 2'), ('XI TKJ 1');

-- Seed: Guru
INSERT INTO `teachers` (`nip`, `name`) VALUES ('197501012005011001', 'Budi Santoso, S.Kom');

-- Seed: Mata Pelajaran
INSERT INTO `subjects` (`name`) VALUES ('Pemrograman Web'), ('Basis Data'), ('Jaringan Komputer');

-- Seed: Jadwal
INSERT INTO `schedules` (`class_id`, `teacher_id`, `subject_id`, `day`, `start_time`, `end_time`) VALUES
(1, 1, 1, 'Senin',  '08:00:00', '09:30:00'),
(1, 1, 2, 'Rabu',   '10:00:00', '11:30:00'),
(2, 1, 3, 'Kamis',  '07:00:00', '08:30:00');

-- Seed: Siswa (qr_code akan di-generate ulang via API, ini hanya placeholder)
INSERT INTO `students` (`nis`, `name`, `class_id`, `qr_code`, `status`) VALUES
('2024001', 'Andi Pratama',       1, SHA2(CONCAT('2024001', 'SAS_SMK_SECRET'), 256), 'aktif'),
('2024002', 'Bella Safitri',      1, SHA2(CONCAT('2024002', 'SAS_SMK_SECRET'), 256), 'aktif'),
('2024003', 'Candra Wijaya',      1, SHA2(CONCAT('2024003', 'SAS_SMK_SECRET'), 256), 'aktif'),
('2024004', 'Dewi Rahayu',        2, SHA2(CONCAT('2024004', 'SAS_SMK_SECRET'), 256), 'aktif'),
('2024005', 'Eko Nugroho',        2, SHA2(CONCAT('2024005', 'SAS_SMK_SECRET'), 256), 'aktif');
