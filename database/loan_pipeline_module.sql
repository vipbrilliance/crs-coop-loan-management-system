-- ============================================================
--  CRS Loan Pipeline Operational Handoff
--  Adds signed approval attachment fields used when applications
--  are approved and activated into monitoring/billing/payments.
-- ============================================================

USE crs_coop;

ALTER TABLE loans
  ADD COLUMN IF NOT EXISTS signed_form_name VARCHAR(255) NULL AFTER signed_form_url,
  ADD COLUMN IF NOT EXISTS signed_form_data LONGTEXT NULL AFTER signed_form_name,
  ADD COLUMN IF NOT EXISTS approval_attachment_name VARCHAR(255) NULL AFTER signed_form_data,
  ADD COLUMN IF NOT EXISTS approval_attachment_data LONGTEXT NULL AFTER approval_attachment_name;
