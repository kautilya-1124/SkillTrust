-- Run once on database `skilltrust` (phpMyAdmin: SQL tab).
-- If a column already exists, skip that line or ignore the error.

ALTER TABLE users ADD COLUMN username VARCHAR(100) NULL DEFAULT NULL AFTER name;
ALTER TABLE users ADD COLUMN phone VARCHAR(50) NULL DEFAULT NULL AFTER email;
ALTER TABLE users ADD COLUMN bio TEXT NULL AFTER phone;
ALTER TABLE users ADD COLUMN skills TEXT NULL AFTER bio;
ALTER TABLE users ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER skills;

UPDATE users
SET username = SUBSTRING_INDEX(email, '@', 1)
WHERE (username IS NULL OR username = '')
  AND email IS NOT NULL AND email != '';
