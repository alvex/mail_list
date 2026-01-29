-- Migration: Telefonlista (si_phone_list) i users_mail
-- Syfte:
-- - Lagra exporterade telefonnummer för Temp kunder
-- - Telefon sparas endast vid export
-- - Undvika dubbletter (unik index på phone)

-- OBS:
-- - Denna migration ska köras i databasen users_mail.

SET @db := DATABASE();

-- Ensure primary key + auto increment on id if column exists
SET @has_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'si_phone_list' AND COLUMN_NAME = 'id'
);

SET @id_is_auto := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'si_phone_list' AND COLUMN_NAME = 'id' AND EXTRA LIKE '%auto_increment%'
);

SET @sql := IF(@has_id = 1 AND @id_is_auto = 0, 'ALTER TABLE si_phone_list MODIFY id INT NOT NULL AUTO_INCREMENT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_primary := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'si_phone_list' AND INDEX_NAME = 'PRIMARY'
);

SET @sql := IF(@has_id = 1 AND @has_primary = 0, 'ALTER TABLE si_phone_list ADD PRIMARY KEY (id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add phone column if missing
SET @has_phone := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'si_phone_list' AND COLUMN_NAME = 'phone'
);
SET @sql := IF(@has_phone = 0, 'ALTER TABLE si_phone_list ADD COLUMN phone VARCHAR(32) NULL AFTER name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add created_at if missing
SET @has_created_at := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'si_phone_list' AND COLUMN_NAME = 'created_at'
);
SET @sql := IF(@has_created_at = 0, 'ALTER TABLE si_phone_list ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Indexes
SET @has_ux_phone := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'si_phone_list' AND INDEX_NAME = 'ux_si_phone_list_phone'
);
SET @sql := IF(@has_ux_phone = 0, 'CREATE UNIQUE INDEX ux_si_phone_list_phone ON si_phone_list (phone)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_any_memberid_index := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'si_phone_list' AND COLUMN_NAME = 'memberid'
);
SET @sql := IF(@has_any_memberid_index = 0, 'CREATE INDEX idx_si_phone_list_memberid ON si_phone_list (memberid)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
