-- FIXED: users table now includes password, phone, and all columns the app actually uses.
-- The original schema was missing password, phone, login_attempts, locked_until, created_at.
CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    email           VARCHAR(160) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    phone           VARCHAR(25)  NULL,
    whatsapp_opt_in TINYINT(1)   NOT NULL DEFAULT 1,
    login_attempts  TINYINT      NOT NULL DEFAULT 0,
    locked_until    DATETIME     NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tests (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(180) NOT NULL,
    category      VARCHAR(80)  NOT NULL,
    difficulty    ENUM('easy', 'medium', 'hard') NOT NULL DEFAULT 'easy',
    duration      INT NOT NULL,
    passing_score INT NOT NULL DEFAULT 40
);

CREATE TABLE IF NOT EXISTS questions (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    test_id        INT NOT NULL,
    question       TEXT NOT NULL,
    option1        VARCHAR(255) NOT NULL,
    option2        VARCHAR(255) NOT NULL,
    option3        VARCHAR(255) NOT NULL,
    option4        VARCHAR(255) NOT NULL,
    correct_option TINYINT NOT NULL,
    explanation    TEXT NULL,
    difficulty     ENUM('easy', 'medium', 'hard') NOT NULL DEFAULT 'easy',
    position       INT NOT NULL DEFAULT 1,
    CONSTRAINT fk_questions_test FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS results (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    test_id    INT NOT NULL,
    score      DECIMAL(10,2) NOT NULL DEFAULT 0,
    percentage DECIMAL(5,2)  NOT NULL DEFAULT 0,
    created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_results_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_results_test FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE
);


-- ── Coding test system ─────────────────────────────────────────────────────
-- FIXED: These 4 tables were completely missing from the original schema.
-- submit_coding_test.php writes to coding_submissions and
-- coding_submission_case_results — both must exist before any submission works.


