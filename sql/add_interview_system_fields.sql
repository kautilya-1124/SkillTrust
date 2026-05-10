ALTER TABLE interviews
    ADD COLUMN IF NOT EXISTS interview_id VARCHAR(100) UNIQUE AFTER recruiter_id,
    ADD COLUMN IF NOT EXISTS meeting_link TEXT NULL AFTER interview_id,
    ADD COLUMN IF NOT EXISTS interview_datetime DATETIME NULL AFTER meeting_link;

CREATE TABLE IF NOT EXISTS interview_feedback (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    interview_id BIGINT UNSIGNED NOT NULL,
    communication INT NOT NULL DEFAULT 0,
    technical INT NOT NULL DEFAULT 0,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_interview_feedback_interview FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE CASCADE,
    INDEX idx_interview_feedback_interview (interview_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
