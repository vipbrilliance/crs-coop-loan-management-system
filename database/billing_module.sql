-- ============================================================
-- CRS COOP Billing Module Migration
-- Run this against an existing crs_coop database before using
-- backend/api/bills.php.
-- ============================================================
USE crs_coop;

CREATE TABLE IF NOT EXISTS companies (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(200) NOT NULL UNIQUE,
  contact     VARCHAR(150),
  email       VARCHAR(150),
  address     TEXT,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO companies (name)
SELECT DISTINCT company FROM members WHERE company IS NOT NULL AND company <> ''
ON DUPLICATE KEY UPDATE name = VALUES(name);

ALTER TABLE amortization_schedule
  MODIFY status ENUM('PENDING','BILLED','PAID','PARTIAL','OVERDUE') DEFAULT 'PENDING';

ALTER TABLE amortization_schedule
  ADD COLUMN bill_item_id INT NULL AFTER status;

CREATE TABLE IF NOT EXISTS bills (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  bill_no               VARCHAR(30) NOT NULL UNIQUE,
  company_id            INT NOT NULL,
  status                ENUM('DRAFT','ISSUED','PARTIAL','SETTLED','CANCELLED') DEFAULT 'DRAFT',
  billing_period_start  DATE NOT NULL,
  billing_period_end    DATE NOT NULL,
  total_amount          DECIMAL(12,2) DEFAULT 0,
  amount_remitted       DECIMAL(12,2) DEFAULT 0,
  issued_at             TIMESTAMP NULL,
  settled_at            TIMESTAMP NULL,
  prepared_by           INT,
  notes                 VARCHAR(500),
  created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES companies(id),
  FOREIGN KEY (prepared_by) REFERENCES users(id),
  INDEX idx_bills_company (company_id),
  INDEX idx_bills_status (status),
  INDEX idx_bills_period (billing_period_start, billing_period_end)
);

CREATE TABLE IF NOT EXISTS bill_items (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  bill_id      INT NOT NULL,
  schedule_id  INT NOT NULL,
  member_id    INT NOT NULL,
  loan_id      INT NOT NULL,
  amount_due   DECIMAL(12,2) NOT NULL,
  amount_paid  DECIMAL(12,2) DEFAULT 0,
  status       ENUM('PENDING','PARTIAL','PAID') DEFAULT 'PENDING',
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE,
  FOREIGN KEY (schedule_id) REFERENCES amortization_schedule(id),
  FOREIGN KEY (member_id) REFERENCES members(id),
  FOREIGN KEY (loan_id) REFERENCES loans(id),
  UNIQUE KEY uniq_bill_schedule (schedule_id),
  INDEX idx_bill_items_bill (bill_id),
  INDEX idx_bill_items_member (member_id),
  INDEX idx_bill_items_loan (loan_id)
);


-- Allow partial application of company remittances to bill line items.
ALTER TABLE bill_items
  MODIFY status ENUM('PENDING','PARTIAL','PAID') DEFAULT 'PENDING';

CREATE TABLE IF NOT EXISTS bill_remittances (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  bill_id          INT NOT NULL,
  or_number        VARCHAR(50),
  amount           DECIMAL(12,2) NOT NULL,
  remittance_date  DATE NOT NULL,
  notes            VARCHAR(500),
  file_name        VARCHAR(255),
  posted_by        INT,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE,
  FOREIGN KEY (posted_by) REFERENCES users(id),
  INDEX idx_bill_remit_bill (bill_id)
);

CREATE TABLE IF NOT EXISTS payments (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  loan_id       INT NOT NULL,
  schedule_id   INT NOT NULL,
  amount_paid   DECIMAL(12,2) NOT NULL,
  payment_type  VARCHAR(30) DEFAULT 'billing',
  or_number     VARCHAR(50),
  payment_date  DATE,
  received_by   INT,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (loan_id) REFERENCES loans(id),
  FOREIGN KEY (schedule_id) REFERENCES amortization_schedule(id),
  FOREIGN KEY (received_by) REFERENCES users(id),
  INDEX idx_payments_loan (loan_id),
  INDEX idx_payments_schedule (schedule_id)
);


-- ------------------------------------------------------------
-- SHARE CAPITAL LEDGER
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS share_capital_ledger (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  member_id      INT NOT NULL,
  transaction_date DATE NOT NULL,
  type           ENUM('OPENING','DEPOSIT','WITHDRAWAL','DIVIDEND','ADJUSTMENT') DEFAULT 'DEPOSIT',
  amount         DECIMAL(12,2) NOT NULL,
  reference      VARCHAR(80),
  source         VARCHAR(50),
  company        VARCHAR(200),
  remarks        VARCHAR(500),
  source_key     VARCHAR(120),
  balance_after  DECIMAL(12,2) DEFAULT 0,
  voided         TINYINT(1) DEFAULT 0,
  voided_at      TIMESTAMP NULL,
  posted_by      INT,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id) REFERENCES members(id),
  FOREIGN KEY (posted_by) REFERENCES users(id),
  UNIQUE KEY uniq_share_source_key (source_key),
  INDEX idx_share_member_date (member_id, transaction_date),
  INDEX idx_share_type_date (type, transaction_date)
);
