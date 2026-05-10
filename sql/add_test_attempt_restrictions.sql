CREATE TABLE IF NOT EXISTS `test_attempts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `test_id` BIGINT UNSIGNED NOT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts` INT UNSIGNED NOT NULL DEFAULT 3,
    `is_blocked` TINYINT(1) NOT NULL DEFAULT 0,
    `admin_unlocked` TINYINT(1) NOT NULL DEFAULT 0,
    `last_attempt_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_test_attempt_user_test` (`user_id`, `test_id`),
    KEY `idx_test_attempt_blocked` (`is_blocked`, `admin_unlocked`),
    CONSTRAINT `fk_test_attempts_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_test_attempts_test`
        FOREIGN KEY (`test_id`) REFERENCES `tests`(`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
