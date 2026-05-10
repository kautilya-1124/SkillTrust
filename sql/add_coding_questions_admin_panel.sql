CREATE TABLE IF NOT EXISTS coding_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    problem_statement LONGTEXT NOT NULL,
    category VARCHAR(120) NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard') NOT NULL DEFAULT 'medium',
    time_limit DECIMAL(6,2) NOT NULL,
    memory_limit INT NOT NULL,
    input_format LONGTEXT NOT NULL,
    output_format LONGTEXT NOT NULL,
    sample_input LONGTEXT NOT NULL,
    sample_output LONGTEXT NOT NULL,
    allowed_languages JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
