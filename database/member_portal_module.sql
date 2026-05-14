-- ============================================================
-- CRS COOP - Member Portal Access Module
-- Apply this to an existing crs_coop database.
-- ============================================================

USE crs_coop;

CREATE TABLE IF NOT EXISTS member_portal_accounts (
  id                     INT AUTO_INCREMENT PRIMARY KEY,
  member_id              INT NOT NULL,
  username               VARCHAR(80) NOT NULL UNIQUE,
  email                  VARCHAR(150),
  password_hash          VARCHAR(255) NOT NULL,
  force_password_change  TINYINT(1) DEFAULT 1,
  modules_json           LONGTEXT,
  is_active              TINYINT(1) DEFAULT 1,
  last_login_at          TIMESTAMP NULL,
  created_by             INT,
  created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_member_portal_member (member_id),
  INDEX idx_member_portal_email (email),
  INDEX idx_member_portal_active (is_active)
);

CREATE TABLE IF NOT EXISTS member_portal_sessions (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  account_id   INT NOT NULL,
  token_hash   CHAR(64) NOT NULL UNIQUE,
  expires_at   TIMESTAMP NOT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (account_id) REFERENCES member_portal_accounts(id) ON DELETE CASCADE,
  INDEX idx_member_portal_session_account (account_id),
  INDEX idx_member_portal_session_expiry (expires_at)
);

CREATE TABLE IF NOT EXISTS member_portal_audit_logs (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  account_id  INT,
  member_id   INT,
  action      VARCHAR(80) NOT NULL,
  detail      VARCHAR(500),
  ip_address  VARCHAR(64),
  user_agent  VARCHAR(500),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (account_id) REFERENCES member_portal_accounts(id) ON DELETE SET NULL,
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
  INDEX idx_member_portal_audit_account (account_id),
  INDEX idx_member_portal_audit_member (member_id),
  INDEX idx_member_portal_audit_created (created_at)
);
