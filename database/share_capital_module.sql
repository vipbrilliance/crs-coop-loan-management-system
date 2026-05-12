-- ============================================================
--  CRS Share Capital Ledger Module
--  Adds auditable share capital transactions and seeds opening
--  balances from existing member profile balances.
-- ============================================================

USE crs_coop;

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
