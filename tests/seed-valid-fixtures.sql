-- seed-valid-fixtures.sql
-- Phase 2 VALID-* smoke test fixtures. Do not run against production.
--
-- This file is idempotent: every INSERT uses ON DUPLICATE KEY UPDATE on the
-- primary key (id), so sourcing it twice against the same DB produces the
-- same final row count.  The amortization_schedule INSERT uses DELETE-then-INSERT
-- because (loan_id, period_no) is an INDEX rather than a UNIQUE key.
--
-- Fixture IDs (keep these in sync with smoke-valid.sh):
--
--   Members:
--     9001  TestValid MemberOne  — NO active loan (used by VALID-01, VALID-02, VALID-05)
--     9002  TestValid MemberTwo  — HAS one ACTIVE loan 9105 (used by VALID-03)
--
--   Loans:
--     9101  DRAFT    member 9001  loan_type_id=2  (VALID-05a start: DRAFT→ACTIVE must 422)
--                                                 (VALID-05b start: DRAFT→PENDING must 200)
--     9102  PENDING  member 9001  loan_type_id=2  (VALID-05c start: STAFF PENDING→APPROVED must 403)
--     9103  APPROVED member 9001  loan_type_id=2  (spare fixture)
--     9104  ACTIVE   member 9001  loan_type_id=2  (VALID-04 amortization anchor)
--     9105  ACTIVE   member 9002  loan_type_id=2  (VALID-03 blocking loan)
--     9106  APPROVED member 9002  loan_type_id=2  (VALID-03 target — must fail to go ACTIVE)
--
--   Amortization schedule:
--     loan_id=9104, period_no=1, amount_due=12500.00, paid_amount=0.00
--     → VALID-04a: overpayment 12500.01 → 422
--     → VALID-04b: exact ceiling 12500.00 → 200 (writes real payment row)
--       Re-source this file to restore paid_amount=0.00 before re-running the suite.
--
-- Loan type used: loan_type_id=2 ('salary' / 'Salary / Cash Loan')
--   min_amount =  5,000.00
--   max_amount = 50,000.00
-- VALID-02 test values:
--   Below min: amount=1         (VALID-02a — must 422 "Amount must be between ...")
--   Above max: amount=99999999  (VALID-02b — must 422)
--
-- Prerequisites:
--   * database/schema.sql already applied
--   * All Phase 1 module SQL patches applied
--   * tests/seed-rbac-users.sql already applied (smoke-valid.sh reuses MANAGER,
--     STAFF, and LOAN_OFFICER users; this file does NOT create new users)
--
-- Teardown (before production deploy):
--   DELETE FROM amortization_schedule WHERE loan_id IN (9104);
--   DELETE FROM loans   WHERE id IN (9101,9102,9103,9104,9105,9106);
--   DELETE FROM members WHERE id IN (9001,9002);

-- -------------------------------------------------------
-- MEMBERS
-- -------------------------------------------------------

INSERT INTO members
  (id, member_no, last_name, first_name, middle_name,
   address, contact, email,
   company, status, position, supervisor,
   date_hired, monthly_salary, share_capital, member_status)
VALUES
  (9001, 'TEST-9001', 'MemberOne', 'TestValid', NULL,
   '1 Test Lane, Test City', '0900-000-0001', 'test-valid-9001@test.invalid',
   'Test COOP', 'REGULAR', 'Tester', 'Test Supervisor',
   '2020-01-01', 20000.00, 10000.00, 'ACTIVE')
ON DUPLICATE KEY UPDATE
  last_name   = 'MemberOne',
  first_name  = 'TestValid',
  member_status = 'ACTIVE';

INSERT INTO members
  (id, member_no, last_name, first_name, middle_name,
   address, contact, email,
   company, status, position, supervisor,
   date_hired, monthly_salary, share_capital, member_status)
VALUES
  (9002, 'TEST-9002', 'MemberTwo', 'TestValid', NULL,
   '2 Test Lane, Test City', '0900-000-0002', 'test-valid-9002@test.invalid',
   'Test COOP', 'REGULAR', 'Tester', 'Test Supervisor',
   '2020-01-01', 20000.00, 10000.00, 'ACTIVE')
ON DUPLICATE KEY UPDATE
  last_name   = 'MemberTwo',
  first_name  = 'TestValid',
  member_status = 'ACTIVE';

-- -------------------------------------------------------
-- LOANS — member 9001 (four status landmarks)
-- loan_type_id=2  min=5000  max=50000
-- amount=25000, term_months=12, frequency='monthly', annual_rate=0.12
-- -------------------------------------------------------

-- 9101 DRAFT — VALID-05a and VALID-05b starting point
-- NOTE: smoke-valid.sh VALID-05b transitions this to PENDING (200 regression).
--       Re-sourcing this file resets it back to DRAFT.
INSERT INTO loans
  (id, loan_no, member_id, loan_type_id, amount, term_months, frequency,
   annual_rate, status, application_date, created_by)
VALUES
  (9101, 'TEST-LN-9101', 9001, 2, 25000.00, 12, 'monthly',
   0.12, 'DRAFT', '2026-01-01', NULL)
ON DUPLICATE KEY UPDATE
  status       = 'DRAFT',
  amount       = 25000.00,
  member_id    = 9001,
  loan_type_id = 2;

-- 9102 PENDING — VALID-05c: STAFF attempts PENDING→APPROVED (must 403, RBAC fires first)
INSERT INTO loans
  (id, loan_no, member_id, loan_type_id, amount, term_months, frequency,
   annual_rate, status, application_date, created_by)
VALUES
  (9102, 'TEST-LN-9102', 9001, 2, 25000.00, 12, 'monthly',
   0.12, 'PENDING', '2026-01-01', NULL)
ON DUPLICATE KEY UPDATE
  status       = 'PENDING',
  amount       = 25000.00,
  member_id    = 9001,
  loan_type_id = 2;

-- 9103 APPROVED — spare fixture (not directly targeted by smoke tests)
INSERT INTO loans
  (id, loan_no, member_id, loan_type_id, amount, term_months, frequency,
   annual_rate, status, application_date, created_by)
VALUES
  (9103, 'TEST-LN-9103', 9001, 2, 25000.00, 12, 'monthly',
   0.12, 'APPROVED', '2026-01-01', NULL)
ON DUPLICATE KEY UPDATE
  status       = 'APPROVED',
  amount       = 25000.00,
  member_id    = 9001,
  loan_type_id = 2;

-- 9104 ACTIVE — anchor for the amortization_schedule row (VALID-04)
INSERT INTO loans
  (id, loan_no, member_id, loan_type_id, amount, term_months, frequency,
   annual_rate, status, application_date, created_by)
VALUES
  (9104, 'TEST-LN-9104', 9001, 2, 25000.00, 12, 'monthly',
   0.12, 'ACTIVE', '2026-01-01', NULL)
ON DUPLICATE KEY UPDATE
  status       = 'ACTIVE',
  amount       = 25000.00,
  member_id    = 9001,
  loan_type_id = 2;

-- -------------------------------------------------------
-- LOANS — member 9002 (VALID-03 pair)
-- 9105 ACTIVE  = blocking loan on same loan_type_id=2
-- 9106 APPROVED = loan whose ACTIVE transition must fail (VALID-03)
-- -------------------------------------------------------

-- 9105 ACTIVE — member 9002 already has an ACTIVE Salary/Cash loan
INSERT INTO loans
  (id, loan_no, member_id, loan_type_id, amount, term_months, frequency,
   annual_rate, status, application_date, created_by)
VALUES
  (9105, 'TEST-LN-9105', 9002, 2, 25000.00, 12, 'monthly',
   0.12, 'ACTIVE', '2026-01-01', NULL)
ON DUPLICATE KEY UPDATE
  status       = 'ACTIVE',
  amount       = 25000.00,
  member_id    = 9002,
  loan_type_id = 2;

-- 9106 APPROVED — transitioning to ACTIVE must fail with VALID-03
--                 (member 9002 already has loan 9105 ACTIVE on same loan_type_id=2)
INSERT INTO loans
  (id, loan_no, member_id, loan_type_id, amount, term_months, frequency,
   annual_rate, status, application_date, created_by)
VALUES
  (9106, 'TEST-LN-9106', 9002, 2, 25000.00, 12, 'monthly',
   0.12, 'APPROVED', '2026-01-01', NULL)
ON DUPLICATE KEY UPDATE
  status       = 'APPROVED',
  amount       = 25000.00,
  member_id    = 9002,
  loan_type_id = 2;

-- -------------------------------------------------------
-- AMORTIZATION SCHEDULE
-- loan_id=9104, period_no=1
-- amount_due=12500.00, paid_amount=0.00
--
-- (loan_id, period_no) is an INDEX but not a UNIQUE key, so idempotency
-- is achieved via DELETE-then-INSERT rather than ON DUPLICATE KEY UPDATE.
-- -------------------------------------------------------

DELETE FROM amortization_schedule
  WHERE loan_id = 9104 AND period_no = 1;

INSERT INTO amortization_schedule
  (loan_id, period_no, due_date, principal, interest, amount_due, balance,
   status, paid_amount)
VALUES
  (9104, 1, '2026-02-01', 10416.67, 2083.33, 12500.00, 25000.00,
   'PENDING', 0.00);
