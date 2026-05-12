-- ============================================================
-- CRS COOP Phase 6-7 Native SQL Additions
-- Dashboard, user management, mobile/performance support.
-- ============================================================
USE crs_coop;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1;
ALTER TABLE users MODIFY role ENUM('SUPER_ADMIN','ADMIN','MANAGER','LOAN_OFFICER','STAFF','AUDITOR') DEFAULT 'STAFF';

CREATE INDEX idx_loans_status_created ON loans (status, created_at);
CREATE INDEX idx_loans_application_date ON loans (application_date);
CREATE INDEX idx_members_status_company ON members (member_status, company);
CREATE INDEX idx_amort_status_due ON amortization_schedule (status, due_date);
CREATE INDEX idx_amort_loan_status ON amortization_schedule (loan_id, status);
CREATE INDEX idx_payments_date ON payments (payment_date);
CREATE INDEX idx_payments_loan_date ON payments (loan_id, payment_date);
CREATE INDEX idx_bills_status_period ON bills (status, billing_period_start, billing_period_end);

-- Member profile photos
ALTER TABLE members ADD COLUMN IF NOT EXISTS profile_image_url LONGTEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS employment_history LONGTEXT;
