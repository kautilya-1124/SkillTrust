-- Add schedule columns for running vs expired test logic.
-- Run once against your database (e.g. phpMyAdmin or mysql client).

ALTER TABLE tests
  ADD COLUMN start_datetime DATETIME NULL DEFAULT NULL AFTER passing_score,
  ADD COLUMN expiry_datetime DATETIME NULL DEFAULT NULL AFTER start_datetime;

-- Optional: backfill existing rows so they appear as "running" for 30 days from now.
-- Uncomment if you want automatic defaults for legacy data:
-- UPDATE tests
-- SET start_datetime = COALESCE(start_datetime, NOW()),
--     expiry_datetime = COALESCE(expiry_datetime, DATE_ADD(NOW(), INTERVAL 30 DAY))
-- WHERE start_datetime IS NULL OR expiry_datetime IS NULL;
