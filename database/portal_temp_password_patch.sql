-- portal_temp_password_patch.sql
-- Adds temp_password column to member_portal_accounts
-- Idempotent: skips if column already exists
USE crs_coop;
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'crs_coop'
    AND TABLE_NAME = 'member_portal_accounts'
    AND COLUMN_NAME = 'temp_password'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE member_portal_accounts ADD COLUMN temp_password VARCHAR(100) DEFAULT NULL COMMENT ''Plaintext shown to admin until member changes password. NULL = member has changed it.''',
  'SELECT ''Column temp_password already exists, skipping.'' AS note'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
