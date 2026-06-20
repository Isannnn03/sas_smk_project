USE `sas_db`;

-- Tambah kolom grade dan major ke tabel classes
ALTER TABLE `classes` ADD COLUMN `grade` VARCHAR(10) NULL AFTER `name`;
ALTER TABLE `classes` ADD COLUMN `major` VARCHAR(100) NULL AFTER `grade`;

-- Tambah kolom email, phone, status ke tabel teachers
ALTER TABLE `teachers` ADD COLUMN `email` VARCHAR(150) NULL AFTER `name`;
ALTER TABLE `teachers` ADD COLUMN `phone` VARCHAR(20) NULL AFTER `email`;
ALTER TABLE `teachers` ADD COLUMN `status` ENUM('aktif','non_aktif') NOT NULL DEFAULT 'aktif' AFTER `phone`;

-- Tambah kolom code ke tabel subjects
ALTER TABLE `subjects` ADD COLUMN `code` VARCHAR(20) NULL AFTER `name`;
