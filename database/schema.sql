-- ============================================================
--  CRS HOLDINGS CORPORATIONS — EMPLOYEES CREDIT COOPERATIVE
--  Database Schema v1.0
-- ============================================================

CREATE DATABASE IF NOT EXISTS crs_coop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crs_coop;

-- ------------------------------------------------------------
-- MEMBERS
-- ------------------------------------------------------------
CREATE TABLE members (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  member_no     VARCHAR(20) NOT NULL UNIQUE,          -- e.g. CRS-00572
  last_name     VARCHAR(100) NOT NULL,
  first_name    VARCHAR(100) NOT NULL,
  middle_name   VARCHAR(100),
  address       TEXT,
  contact       VARCHAR(20),
  email         VARCHAR(150),
  profile_image_url LONGTEXT,
  employment_history LONGTEXT,
  company       VARCHAR(200),
  branch        VARCHAR(100),
  department    VARCHAR(100),
  status        ENUM('REGULAR','PROBI','SUSPENDED','INACTIVE') DEFAULT 'PROBI',
  position      VARCHAR(150),
  supervisor    VARCHAR(150),
  date_hired    DATE,
  monthly_salary DECIMAL(12,2) DEFAULT 0,
  share_capital  DECIMAL(12,2) DEFAULT 0,
  member_status  ENUM('ACTIVE','INACTIVE','RESIGNED') DEFAULT 'ACTIVE',
  photo_url     VARCHAR(255),
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- ------------------------------------------------------------
-- LOAN TYPES
-- ------------------------------------------------------------
CREATE TABLE loan_types (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  code          VARCHAR(30) NOT NULL UNIQUE,          -- commodity, salary, emergency, educ, multi
  label         VARCHAR(100) NOT NULL,
  min_amount    DECIMAL(12,2) NOT NULL,
  max_amount    DECIMAL(12,2) NOT NULL,
  min_term      INT NOT NULL,                          -- months
  max_term      INT NOT NULL,
  annual_rate   DECIMAL(6,4) NOT NULL DEFAULT 0.12,
  is_active     TINYINT(1) DEFAULT 1,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- LOAN APPLICATIONS
-- ------------------------------------------------------------
CREATE TABLE loans (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  loan_no           VARCHAR(30) NOT NULL UNIQUE,        -- e.g. LN-2026-00001
  member_id         INT NOT NULL,
  loan_type_id      INT NOT NULL,
  amount            DECIMAL(12,2) NOT NULL,
  term_months       INT NOT NULL,
  frequency         ENUM('monthly','bimonthly','weekly') DEFAULT 'bimonthly',
  annual_rate       DECIMAL(6,4) NOT NULL,
  purpose           TEXT,
  co_maker_1_id     INT,
  co_maker_2_id     INT,
  status            ENUM('DRAFT','PENDING','APPROVED','ACTIVE','CLOSED','REJECTED') DEFAULT 'DRAFT',
  -- computed / cached
  total_payment     DECIMAL(14,2),
  total_interest    DECIMAL(14,2),
  n_periods         INT,
  first_payment_amt DECIMAL(12,2),
  last_payment_amt  DECIMAL(12,2),
  -- dates
  application_date  DATE,
  approval_date     DATE,
  first_due_date    DATE,
  end_date          DATE,
  -- approvals
  approved_by_hr    VARCHAR(150),
  approved_by_coop  VARCHAR(150),
  -- attachments
  signed_form_url   VARCHAR(255),
  signed_form_name  VARCHAR(255),
  signed_form_data  LONGTEXT,
  approval_attachment_name VARCHAR(255),
  approval_attachment_data LONGTEXT,
  notes             TEXT,
  created_by        INT,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id)     REFERENCES members(id),
  FOREIGN KEY (loan_type_id)  REFERENCES loan_types(id),
  FOREIGN KEY (co_maker_1_id) REFERENCES members(id),
  FOREIGN KEY (co_maker_2_id) REFERENCES members(id)
);

-- ------------------------------------------------------------
-- AMORTIZATION SCHEDULE
-- ------------------------------------------------------------
CREATE TABLE amortization_schedule (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  loan_id       INT NOT NULL,
  period_no     INT NOT NULL,
  due_date      DATE,
  principal     DECIMAL(12,2) NOT NULL,
  interest      DECIMAL(12,2) NOT NULL,
  amount_due    DECIMAL(12,2) NOT NULL,
  balance       DECIMAL(12,2) NOT NULL,
  status        ENUM('PENDING','BILLED','PAID','PARTIAL','OVERDUE') DEFAULT 'PENDING',
  bill_item_id  INT,
  paid_amount   DECIMAL(12,2) DEFAULT 0,
  paid_date     DATE,
  or_number     VARCHAR(50),
  FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
  INDEX idx_loan_period (loan_id, period_no)
);

-- ------------------------------------------------------------
-- USERS (officers / admins)
-- ------------------------------------------------------------
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(150) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('SUPER_ADMIN','ADMIN','MANAGER','LOAN_OFFICER','STAFF','AUDITOR') DEFAULT 'STAFF',
  is_active     TINYINT(1) DEFAULT 1,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);




-- ------------------------------------------------------------
-- NOTIFICATION LOGS
-- ------------------------------------------------------------
CREATE TABLE notification_logs (
  id             BIGINT AUTO_INCREMENT PRIMARY KEY,
  source_key     VARCHAR(160) UNIQUE,
  event_key      VARCHAR(80) NOT NULL,
  event_label    VARCHAR(150),
  channel        ENUM('SMS','EMAIL','SYSTEM','DISABLED') DEFAULT 'SYSTEM',
  recipient_name VARCHAR(180),
  destination    VARCHAR(180),
  reference      VARCHAR(180),
  message        TEXT NOT NULL,
  status         ENUM('QUEUED','SENT','FAILED','DISABLED') DEFAULT 'QUEUED',
  payload_json   LONGTEXT,
  created_by     INT,
  sent_at        TIMESTAMP NULL,
  failed_at      TIMESTAMP NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_notification_status (status),
  INDEX idx_notification_channel (channel),
  INDEX idx_notification_event (event_key),
  INDEX idx_notification_created (created_at)
);

-- ------------------------------------------------------------
-- AUDIT LOGS
-- ------------------------------------------------------------
CREATE TABLE audit_logs (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  module        VARCHAR(80) NOT NULL,
  action        VARCHAR(50) NOT NULL,
  record_type   VARCHAR(120),
  record_id     VARCHAR(80),
  record_label  VARCHAR(180),
  actor_user_id INT,
  actor_name    VARCHAR(150),
  detail        TEXT,
  risk          ENUM('LOW','MEDIUM','HIGH') DEFAULT 'LOW',
  payload_json  LONGTEXT,
  ip_address    VARCHAR(64),
  user_agent    VARCHAR(500),
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (actor_user_id) REFERENCES users(id),
  INDEX idx_audit_created (created_at),
  INDEX idx_audit_module_action (module, action),
  INDEX idx_audit_record (record_type, record_id),
  INDEX idx_audit_actor (actor_user_id)
);

-- ------------------------------------------------------------
-- SHARE CAPITAL LEDGER
-- ------------------------------------------------------------
CREATE TABLE share_capital_ledger (
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

-- ------------------------------------------------------------
-- COMPANIES
-- ------------------------------------------------------------
CREATE TABLE companies (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(200) NOT NULL UNIQUE,
  contact     VARCHAR(150),
  email       VARCHAR(150),
  address     TEXT,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- BILLING
-- ------------------------------------------------------------
CREATE TABLE bills (
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

CREATE TABLE bill_items (
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

CREATE TABLE bill_remittances (
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

CREATE TABLE payments (
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
-- SEED DATA
-- ------------------------------------------------------------
INSERT INTO companies (name) VALUES
  ('CRS Holdings Corp.'),
  ('CRS Holdings Corporation')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO loan_types (code, label, min_amount, max_amount, min_term, max_term, annual_rate) VALUES
  ('commodity', 'Commodity Loan',     10000, 100000, 6, 36, 0.12),
  ('salary',    'Salary / Cash Loan', 5000,  50000,  3, 24, 0.12),
  ('emergency', 'Emergency Loan',     3000,  30000,  3, 12, 0.12),
  ('educ',      'Educational Loan',   5000,  80000,  6, 24, 0.10),
  ('multi',     'Multi-purpose Loan', 10000, 150000, 6, 48, 0.12);

INSERT INTO users (name, email, password_hash, role) VALUES
  ('J. Monteverde', 'j.monteverde@crsholdings.ph', '$2y$10$examplehashhere', 'LOAN_OFFICER'),
  ('Admin', 'admin@crsholdings.ph', '$2y$10$examplehashhere', 'ADMIN');

INSERT INTO members (member_no, last_name, first_name, middle_name, address, contact, email, company, status, position, supervisor, date_hired, monthly_salary, share_capital, member_status) VALUES
  ('CRS-00234', 'Santos',    'Maria Clara',  'D.', '127 Mabolo St., Mandaue City, Cebu',     '0917-234-5678', 'mc.santos@crsholdings.ph',    'CRS Holdings Corp.', 'REGULAR', 'Sr. Accountant',        'Engr. R. Villanueva', '2021-03-15', 32000, 15000, 'ACTIVE'),
  ('CRS-00411', 'Dela Cruz', 'Juan Ponce',   'C.', 'Blk 7 Lot 3, Basak, Lapu-Lapu City',    '0928-115-9901', 'jp.delacruz@crsholdings.ph',  'CRS Holdings Corp.', 'REGULAR', 'Logistics Lead',        'Ma. T. Abellanosa',   '2019-08-02', 28500, 22000, 'ACTIVE'),
  ('CRS-00572', 'Bacolod',   'Aileen',       'R.', '42 Guizo Rd., Mandaue City',             '0945-880-4412', 'a.bacolod@crsholdings.ph',    'CRS Holdings Corp.', 'PROBI',   'HR Assistant',          'J. Monteverde',        '2025-11-10', 19500, 3000,  'ACTIVE'),
  ('CRS-00103', 'Tan',       'Rodrigo',      'M.', '88 Cabancalan, Mandaue City',            '0919-442-0087', 'r.tan@crsholdings.ph',         'CRS Holdings Corp.', 'REGULAR', 'Warehouse Supervisor',  'A. Lim',              '2017-01-20', 34500, 40000, 'ACTIVE');

INSERT INTO share_capital_ledger
  (member_id, transaction_date, type, amount, reference, source, company, remarks, source_key, balance_after)
SELECT
  m.id,
  COALESCE(m.date_hired, CURDATE()),
  'OPENING',
  m.share_capital,
  CONCAT('SC-OPEN-', m.member_no),
  'opening',
  m.company,
  'Opening balance from member profile',
  CONCAT('share-opening-', m.id),
  m.share_capital
FROM members m
WHERE m.share_capital > 0
  AND NOT EXISTS (
    SELECT 1
    FROM share_capital_ledger scl
    WHERE scl.source_key = CONCAT('share-opening-', m.id)
  );
