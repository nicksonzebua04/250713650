CREATE DATABASE IF NOT EXISTS cattracker_local
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE cattracker_local;

CREATE TABLE IF NOT EXISTS users (
  id_user INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama_lengkap VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
  bio TEXT NULL,
  foto_path VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @profile_photo_column_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'foto_path'
);
SET @profile_photo_migration = IF(
  @profile_photo_column_exists = 0,
  'ALTER TABLE users ADD COLUMN foto_path VARCHAR(255) NULL AFTER bio',
  'SELECT 1'
);
PREPARE profile_photo_migration_statement FROM @profile_photo_migration;
EXECUTE profile_photo_migration_statement;
DEALLOCATE PREPARE profile_photo_migration_statement;

SET @role_column_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'role'
);
SET @role_migration = IF(
  @role_column_exists = 0,
  'ALTER TABLE users ADD COLUMN role ENUM(''admin'', ''user'') NOT NULL DEFAULT ''user'' AFTER password',
  'SELECT 1'
);
PREPARE role_migration_statement FROM @role_migration;
EXECUTE role_migration_statement;
DEALLOCATE PREPARE role_migration_statement;

CREATE TABLE IF NOT EXISTS kucing (
  id_kucing INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  ciri_fisik TEXT NULL,
  lokasi_nongkrong VARCHAR(255) NULL,
  foto_path VARCHAR(255) NULL,
  status ENUM('aktif', 'hilang', 'sakit', 'lainnya', 'diadopsi') NOT NULL DEFAULT 'aktif',
  kepemilikan ENUM('liar', 'peliharaan', 'belum_diketahui') NOT NULL DEFAULT 'belum_diketahui',
  keterangan_status VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @status_values_exist = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'kucing'
    AND COLUMN_NAME = 'status'
    AND COLUMN_TYPE LIKE '%sakit%'
    AND COLUMN_TYPE LIKE '%lainnya%'
);
SET @status_migration = IF(
  @status_values_exist = 0,
  'ALTER TABLE kucing MODIFY COLUMN status ENUM(''aktif'', ''hilang'', ''sakit'', ''lainnya'', ''diadopsi'') NOT NULL DEFAULT ''aktif''',
  'SELECT 1'
);
PREPARE status_migration_statement FROM @status_migration;
EXECUTE status_migration_statement;
DEALLOCATE PREPARE status_migration_statement;

SET @ownership_column_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'kucing'
    AND COLUMN_NAME = 'kepemilikan'
);
SET @ownership_migration = IF(
  @ownership_column_exists = 0,
  'ALTER TABLE kucing ADD COLUMN kepemilikan ENUM(''liar'', ''peliharaan'', ''belum_diketahui'') NOT NULL DEFAULT ''belum_diketahui'' AFTER status',
  'SELECT 1'
);
PREPARE ownership_migration_statement FROM @ownership_migration;
EXECUTE ownership_migration_statement;
DEALLOCATE PREPARE ownership_migration_statement;

SET @status_detail_column_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'kucing'
    AND COLUMN_NAME = 'keterangan_status'
);
SET @status_detail_migration = IF(
  @status_detail_column_exists = 0,
  'ALTER TABLE kucing ADD COLUMN keterangan_status VARCHAR(255) NULL AFTER kepemilikan',
  'SELECT 1'
);
PREPARE status_detail_migration_statement FROM @status_detail_migration;
EXECUTE status_detail_migration_statement;
DEALLOCATE PREPARE status_detail_migration_statement;

SET @cat_photo_column_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'kucing'
    AND COLUMN_NAME = 'foto_path'
);
SET @cat_photo_migration = IF(
  @cat_photo_column_exists = 0,
  'ALTER TABLE kucing ADD COLUMN foto_path VARCHAR(255) NULL AFTER lokasi_nongkrong',
  'SELECT 1'
);
PREPARE cat_photo_migration_statement FROM @cat_photo_migration;
EXECUTE cat_photo_migration_statement;
DEALLOCATE PREPARE cat_photo_migration_statement;

CREATE TABLE IF NOT EXISTS log_interaksi (
  id_log INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_user INT UNSIGNED NOT NULL,
  id_kucing INT UNSIGNED NOT NULL,
  jenis_interaksi ENUM('penampakan', 'beri_makan', 'bermain', 'lainnya') NOT NULL,
  catatan TEXT NULL,
  latitude DECIMAL(10, 8) NULL,
  longitude DECIMAL(11, 8) NULL,
  waktu_kejadian DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_user FOREIGN KEY (id_user)
    REFERENCES users (id_user) ON DELETE RESTRICT,
  CONSTRAINT fk_log_kucing FOREIGN KEY (id_kucing)
    REFERENCES kucing (id_kucing) ON DELETE CASCADE,
  INDEX idx_log_waktu (waktu_kejadian)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
