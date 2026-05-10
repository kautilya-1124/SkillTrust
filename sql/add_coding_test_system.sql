CREATE TABLE IF NOT EXISTS coding_questions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    question LONGTEXT NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard') NOT NULL DEFAULT 'medium',
    category VARCHAR(100) NOT NULL DEFAULT 'Programming',
    starter_code LONGTEXT DEFAULT NULL,
    allowed_languages JSON DEFAULT NULL,
    time_limit_seconds DECIMAL(5,2) NOT NULL DEFAULT 2.00,
    memory_limit_kb INT UNSIGNED NOT NULL DEFAULT 131072,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_coding_questions_active (is_active),
    INDEX idx_coding_questions_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coding_question_test_cases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    coding_question_id BIGINT UNSIGNED NOT NULL,
    input_data LONGTEXT DEFAULT NULL,
    expected_output LONGTEXT NOT NULL,
    is_sample TINYINT(1) NOT NULL DEFAULT 0,
    weight DECIMAL(6,2) NOT NULL DEFAULT 1.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_coding_question_test_cases_question
        FOREIGN KEY (coding_question_id) REFERENCES coding_questions(id) ON DELETE CASCADE,
    INDEX idx_coding_question_cases_question (coding_question_id),
    INDEX idx_coding_question_cases_sample (coding_question_id, is_sample)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coding_submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    coding_question_id BIGINT UNSIGNED NOT NULL,
    language_key VARCHAR(40) NOT NULL,
    source_code LONGTEXT NOT NULL,
    result_status ENUM('passed', 'failed', 'error') NOT NULL DEFAULT 'failed',
    passed_test_cases INT UNSIGNED NOT NULL DEFAULT 0,
    total_test_cases INT UNSIGNED NOT NULL DEFAULT 0,
    score DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    judge0_status VARCHAR(120) DEFAULT NULL,
    execution_time DECIMAL(8,3) DEFAULT NULL,
    memory_usage_kb INT UNSIGNED DEFAULT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_coding_submissions_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_coding_submissions_question
        FOREIGN KEY (coding_question_id) REFERENCES coding_questions(id) ON DELETE CASCADE,
    INDEX idx_coding_submissions_user_question (user_id, coding_question_id),
    INDEX idx_coding_submissions_question (coding_question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coding_submission_case_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id BIGINT UNSIGNED NOT NULL,
    test_case_id BIGINT UNSIGNED NOT NULL,
    judge0_token VARCHAR(120) DEFAULT NULL,
    status_label VARCHAR(120) DEFAULT NULL,
    stdin_data LONGTEXT DEFAULT NULL,
    expected_output LONGTEXT DEFAULT NULL,
    actual_output LONGTEXT DEFAULT NULL,
    stderr_output LONGTEXT DEFAULT NULL,
    compile_output LONGTEXT DEFAULT NULL,
    passed TINYINT(1) NOT NULL DEFAULT 0,
    execution_time DECIMAL(8,3) DEFAULT NULL,
    memory_usage_kb INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_coding_submission_case_results_submission
        FOREIGN KEY (submission_id) REFERENCES coding_submissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_coding_submission_case_results_case
        FOREIGN KEY (test_case_id) REFERENCES coding_question_test_cases(id) ON DELETE CASCADE,
    INDEX idx_coding_submission_case_results_submission (submission_id),
    INDEX idx_coding_submission_case_results_case (test_case_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
