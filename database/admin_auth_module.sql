-- admin_auth_module.sql -- Phase 1: admin_sessions table + SUPER_ADMIN seed
-- Apply against an existing crs_coop database (idempotent -- safe to re-run).

USE crs_coop;

-- ------------------------------------------------------------
-- Admin session store
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_sessions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  ip_address VARCHAR(64),
  user_agent VARCHAR(500),
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_admin_session_user (user_id),
  INDEX idx_admin_session_expiry (expires_at)
);

-- ------------------------------------------------------------
-- Seed / upgrade the admin@crsholdings.ph user to SUPER_ADMIN
-- with a real bcrypt hash for plaintext: CRS-Admin-2026
-- (ON DUPLICATE KEY UPDATE makes this idempotent)
-- ------------------------------------------------------------
INSERT INTO users (name, email, password_hash, role, is_active)
VALUES ('Admin', 'admin@crsholdings.ph', '$2y$12$cEB/RYQ./j8TrYKCjKFhiOI0F6n0QY2qeyXxxV04TtF83EnV6IUcG', 'SUPER_ADMIN', 1)
ON DUPLICATE KEY UPDATE
  role          = 'SUPER_ADMIN',
  is_active     = 1,
  password_hash = VALUES(password_hash);
