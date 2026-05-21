-- seed-phase3-fixtures.sql
-- Phase 3 smoke test fixtures. Do not run against production.
--
-- This file is idempotent: member and loan INSERTs use
-- ON DUPLICATE KEY UPDATE so sourcing it twice produces the
-- same final row count.
--
-- Fixture IDs (keep in sync with smoke-phase3.sh):
--
--   Members:
--     9901  Phase3 TestMember  — ACTIVE, used by all Phase 3 smoke tests
--
--   Loans:
--     9901  LN-TEST-P3001  — ACTIVE loan for member 9901, used by
--           BENEF-*, CO-MAKER, RESTR-* tests
--
-- Prerequisites:
--   * database/schema.sql already applied
--   * database/phase3_module.sql already applied (loan_restructurings,
--     member_beneficiaries, co_makers tables must exist)
--   * tests/seed-rbac-users.sql already applied (MANAGER, LOAN_OFFICER,
--     AUDITOR users already exist — this file does NOT create new users)
--
-- Teardown (before production deploy):
--   DELETE FROM loans   WHERE id = 9901;
--   DELETE FROM members WHERE id = 9901;

-- -------------------------------------------------------
-- MEMBERS
-- -------------------------------------------------------

INSERT INTO members
  (id, member_no, last_name, first_name, member_status, monthly_salary)
VALUES
  (9901, 'TEST-P3-001', 'TestMember', 'Phase3', 'ACTIVE', 20000.00)
ON DUPLICATE KEY UPDATE
  last_name     = 'TestMember',
  first_name    = 'Phase3',
  member_status = 'ACTIVE';

-- -------------------------------------------------------
-- LOANS — member 9901 (Phase 3 anchor)
-- Uses SELECT subquery for loan_type_id so this works regardless
-- of which loan types exist in the target DB.
-- ON DUPLICATE KEY UPDATE with no-op ensures idempotency on MySQL
-- versions where INSERT IGNORE + SELECT does not suppress duplicate errors.
-- -------------------------------------------------------

INSERT INTO loans
  (id, loan_no, member_id, loan_type_id, amount, term_months, frequency,
   annual_rate, status, application_date, created_by)
SELECT
  9901, 'LN-TEST-P3001', 9901, id, 50000.00, 12, 'monthly',
  0.12, 'ACTIVE', '2026-01-01', 1
FROM loan_types
LIMIT 1
ON DUPLICATE KEY UPDATE
  loan_no = loan_no;
