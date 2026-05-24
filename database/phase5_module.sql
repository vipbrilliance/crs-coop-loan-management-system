-- ============================================================
-- CRS COOP Phase 5 Module — Operational Reports & CSV Import
-- Schema patch: adds outstanding_balance to loans table
-- ============================================================
--
-- Purpose: Imported loans (Phase 5, D-07/D-08) have no amortization_schedule rows.
-- outstanding_balance stores the imported balance so REPORT-01 can display it via
-- COALESCE(computed_schedule_balance, l.outstanding_balance).
--
-- Safe to re-run: conditional ADD COLUMN guard prevents duplicate column error.
--
-- Apply after: schema.sql, phase3_module.sql
-- Apply before: running import.php for the first time
-- ============================================================

SET time_zone = '+08:00';

-- ------------------------------------------------------------
-- 1. ADD outstanding_balance COLUMN to loans (idempotent)
-- ------------------------------------------------------------
-- Uses information_schema lookup + PREPARE/EXECUTE pattern from phase3_module.sql
-- (ADD COLUMN IF NOT EXISTS is MariaDB-only syntax, not standard MySQL 8).
-- ------------------------------------------------------------
SET @col_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'loans'
    AND COLUMN_NAME  = 'outstanding_balance'
);

SET @add_col_sql = IF(
  @col_exists = 0,
  'ALTER TABLE loans ADD COLUMN outstanding_balance DECIMAL(12,2) NULL',
  'SELECT 1'
);

PREPARE add_col_stmt FROM @add_col_sql;
EXECUTE add_col_stmt;
DEALLOCATE PREPARE add_col_stmt;
