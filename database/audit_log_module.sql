-- ============================================================
-- CRS Audit Log Module
-- Adds durable compliance event storage for operational actions.
-- ============================================================

CREATE TABLE IF NOT EXISTS audit_logs (
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
