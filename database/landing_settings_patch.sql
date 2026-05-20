-- Expand system_settings.value to TEXT to support JSON payloads (landing page settings etc.)
ALTER TABLE system_settings MODIFY COLUMN value TEXT NOT NULL;
