-- ============================================================
-- CRS COOP Phase 3 Module — localStorage Migration Schema
-- Beneficiaries, Co-makers, Loan Restructurings
-- ============================================================

-- ------------------------------------------------------------
-- 1. MEMBER BENEFICIARIES
-- ------------------------------------------------------------
-- Stores primary and secondary beneficiaries declared by each
-- member. Column names match member-portal.php SELECT aliases
-- (full_name, relationship, allocation_percent, beneficiary_type, contact).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS member_beneficiaries (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  member_id         INT NOT NULL,
  full_name         VARCHAR(200) NOT NULL,
  relationship      VARCHAR(80),
  allocation_percent DECIMAL(5,2) DEFAULT 0,
  beneficiary_type  ENUM('primary','secondary') DEFAULT 'primary',
  contact           VARCHAR(80),
  birth_date        DATE,
  address           TEXT,
  id_type           VARCHAR(80),
  id_number         VARCHAR(100),
  registered_name   VARCHAR(200),
  guardian          VARCHAR(200),
  remarks           TEXT,
  created_by        INT,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id)  REFERENCES members(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_beneficiary_member (member_id),
  INDEX idx_beneficiary_type   (member_id, beneficiary_type)
);

-- ------------------------------------------------------------
-- 2. CO-MAKERS
-- ------------------------------------------------------------
-- Records co-makers for a loan. No updated_at — co-makers are
-- insert/delete only (D-05). UNIQUE constraint prevents duplicate
-- co-maker entries for the same loan+member pair.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS co_makers (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  loan_id     INT NOT NULL,
  member_id   INT NOT NULL,
  role        VARCHAR(80) DEFAULT 'Co-maker',
  created_by  INT,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_comaker_loan_member (loan_id, member_id),
  FOREIGN KEY (loan_id)    REFERENCES loans(id) ON DELETE CASCADE,
  FOREIGN KEY (member_id)  REFERENCES members(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_comaker_loan   (loan_id),
  INDEX idx_comaker_member (member_id)
);

-- ------------------------------------------------------------
-- 3. LOAN RESTRUCTURINGS
-- ------------------------------------------------------------
-- Captures every restructuring event for a loan, preserving the
-- original terms for audit. created_by is NOT NULL — restructurings
-- always have an authenticated MANAGER (D-08).
-- This table must be created BEFORE the ALTER TABLE below so that
-- the FK from amortization_schedule can reference it.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS loan_restructurings (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  loan_id               INT NOT NULL,
  restructuring_no      VARCHAR(30) NOT NULL UNIQUE,
  original_amount       DECIMAL(12,2) NOT NULL,
  original_annual_rate  DECIMAL(6,4)  NOT NULL,
  original_term_months  INT NOT NULL,
  original_frequency    ENUM('monthly','bimonthly','weekly') NOT NULL,
  new_amount            DECIMAL(12,2) NOT NULL,
  new_annual_rate       DECIMAL(6,4)  NOT NULL,
  new_term_months       INT NOT NULL,
  new_frequency         ENUM('monthly','bimonthly','weekly') NOT NULL,
  first_due_date        DATE NOT NULL,
  reason                VARCHAR(150),
  notes                 TEXT,
  created_by            INT NOT NULL,
  created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (loan_id)    REFERENCES loans(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_restructuring_loan    (loan_id),
  INDEX idx_restructuring_created (created_at)
);

-- ------------------------------------------------------------
-- 4. ALTER amortization_schedule — add restructuring_id column
-- ------------------------------------------------------------
-- Both the column and the FK constraint are added via dynamic
-- PREPARE statements so that running this file a second time
-- does NOT raise a duplicate column or constraint error.
-- (ADD COLUMN IF NOT EXISTS is MariaDB-only; not standard MySQL)
-- ------------------------------------------------------------
SET @col_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'amortization_schedule'
    AND COLUMN_NAME  = 'restructuring_id'
);
SET @add_col_sql = IF(
  @col_exists = 0,
  'ALTER TABLE amortization_schedule ADD COLUMN restructuring_id INT NULL',
  'SELECT 1'
);
PREPARE add_col_stmt FROM @add_col_sql;
EXECUTE add_col_stmt;
DEALLOCATE PREPARE add_col_stmt;

SET @constraint_exists = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME    = 'amortization_schedule'
    AND CONSTRAINT_NAME = 'fk_amort_restructuring'
);

SET @sql = IF(
  @constraint_exists = 0,
  'ALTER TABLE amortization_schedule ADD CONSTRAINT fk_amort_restructuring FOREIGN KEY (restructuring_id) REFERENCES loan_restructurings(id)',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
