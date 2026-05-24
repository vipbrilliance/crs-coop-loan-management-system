-- ============================================================
--  CRS HOLDINGS CORPORATIONS — EMPLOYEES CREDIT COOPERATIVE
--  Migration: Add bank account disbursement fields to members
--  Apply once on existing databases (schema.sql already includes these)
-- ============================================================

ALTER TABLE members
  ADD COLUMN account_name   VARCHAR(200) AFTER email,
  ADD COLUMN account_number VARCHAR(100) AFTER account_name;
