-- SkillTrust normal test schema fix
-- Run this once in Aiven DBeaver against database: defaultdb
-- Compatible with MySQL versions that reject ALTER TABLE ... ADD COLUMN IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS questions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    test_id INT UNSIGNED NOT NULL,
    question TEXT NOT NULL,
    option1 VARCHAR(255) NOT NULL,
    option2 VARCHAR(255) NOT NULL,
    option3 VARCHAR(255) NOT NULL,
    option4 VARCHAR(255) NOT NULL,
    correct_option TINYINT UNSIGNED NOT NULL,
    explanation TEXT NULL,
    difficulty ENUM('easy','medium','hard') NOT NULL DEFAULT 'easy',
    position INT NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_questions_test_id (test_id),
    CONSTRAINT fk_questions_test
      FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        "ALTER TABLE tests ADD COLUMN difficulty ENUM('easy','medium','hard') NOT NULL DEFAULT 'easy'",
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tests'
      AND COLUMN_NAME = 'difficulty'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        "ALTER TABLE tests ADD COLUMN duration INT NOT NULL DEFAULT 30",
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tests'
      AND COLUMN_NAME = 'duration'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        "ALTER TABLE tests ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0",
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tests'
      AND COLUMN_NAME = 'featured'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        "ALTER TABLE results ADD COLUMN percentage DECIMAL(5,2) NOT NULL DEFAULT 0",
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'results'
      AND COLUMN_NAME = 'percentage'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
