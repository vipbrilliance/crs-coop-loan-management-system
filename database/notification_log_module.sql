-- ============================================================
-- CRS Notification Log Module
-- Stores generated, manual, sent, failed, and disabled notification events.
-- ============================================================

CREATE TABLE IF NOT EXISTS notification_logs (
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
