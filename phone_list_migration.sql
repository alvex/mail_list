-- Migration: Telefonlista (cal_phone_list)
-- Syfte:
-- - Lägga till telefonnummerkolumn som endast fylls vid export
-- - Logga skapad tid (created_at)
-- - Möjlighet att koppla nummer till kund (memberid)
-- - Undvika dubbletter (unik index på phone)

-- OBS:
-- - Denna migration utgår från att tabellen cal_phone_list redan finns.
-- - phone kan vara NULL för befintliga rader (t.ex. historik/cursor-rader).

-- Add columns only if missing
SET @db := DATABASE();

-- Ensure primary key + auto increment on id (required by admin CRUD UI)
SET @has_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'cal_phone_list' AND COLUMN_NAME = 'id'
);

SET @id_is_auto := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'cal_phone_list' AND COLUMN_NAME = 'id' AND EXTRA LIKE '%auto_increment%'
);

SET @sql := IF(@has_id = 1 AND @id_is_auto = 0, 'ALTER TABLE cal_phone_list MODIFY id INT NOT NULL AUTO_INCREMENT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_primary := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'cal_phone_list' AND INDEX_NAME = 'PRIMARY'
);

SET @sql := IF(@has_id = 1 AND @has_primary = 0, 'ALTER TABLE cal_phone_list ADD PRIMARY KEY (id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Cleanup: drop legacy customer_id (not used; memberid is the customer reference)
SET @has_customer_id := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'cal_phone_list' AND COLUMN_NAME = 'customer_id'
);

SET @has_customer_id_idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'cal_phone_list' AND COLUMN_NAME = 'customer_id'
);

SET @customer_id_idx_name := (
    SELECT INDEX_NAME
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'cal_phone_list' AND COLUMN_NAME = 'customer_id'
    ORDER BY (INDEX_NAME = 'PRIMARY') ASC, SEQ_IN_INDEX ASC
    LIMIT 1
);

SET @sql := IF(@has_customer_id_idx > 0 AND @customer_id_idx_name IS NOT NULL AND @customer_id_idx_name <> 'PRIMARY', CONCAT('DROP INDEX ', @customer_id_idx_name, ' ON cal_phone_list'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_customer_id = 1, 'ALTER TABLE cal_phone_list DROP COLUMN customer_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_phone := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'cal_phone_list' AND COLUMN_NAME = 'phone'
);
SET @sql := IF(@has_phone = 0, 'ALTER TABLE cal_phone_list ADD COLUMN phone VARCHAR(32) NULL AFTER name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_created_at := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'cal_phone_list' AND COLUMN_NAME = 'created_at'
);
SET @sql := IF(@has_created_at = 0, 'ALTER TABLE cal_phone_list ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Indexes only if missing
SET @has_ux_phone := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'cal_phone_list' AND INDEX_NAME = 'ux_cal_phone_list_phone'
);
SET @sql := IF(@has_ux_phone = 0, 'CREATE UNIQUE INDEX ux_cal_phone_list_phone ON cal_phone_list (phone)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Create index for memberid only if there is no existing index on that column
SET @has_any_memberid_index := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'cal_phone_list' AND COLUMN_NAME = 'memberid'
);
SET @sql := IF(@has_any_memberid_index = 0, 'CREATE INDEX idx_cal_phone_list_memberid ON cal_phone_list (memberid)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Create index for created_at only if there is no existing index on that column
SET @has_any_created_at_index := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'cal_phone_list' AND COLUMN_NAME = 'created_at'
);
SET @sql := IF(@has_any_created_at_index = 0, 'CREATE INDEX idx_cal_phone_list_created_at ON cal_phone_list (created_at)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
