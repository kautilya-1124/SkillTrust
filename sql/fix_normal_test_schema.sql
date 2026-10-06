-- SkillTrust normal test schema fix
-- Run this once in Aiven DBeaver against database: defaultdb

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

ALTER TABLE tests ADD COLUMN IF NOT EXISTS difficulty ENUM('easy','medium','hard') NOT NULL DEFAULT 'easy';
ALTER TABLE tests ADD COLUMN IF NOT EXISTS duration INT NOT NULL DEFAULT 30;
ALTER TABLE tests ADD COLUMN IF NOT EXISTS featured TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE results ADD COLUMN IF NOT EXISTS percentage DECIMAL(5,2) NOT NULL DEFAULT 0;
