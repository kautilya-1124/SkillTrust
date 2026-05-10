CREATE TABLE IF NOT EXISTS whatsapp_notification_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_phone VARCHAR(40) NOT NULL,
    message_body MEDIUMTEXT NOT NULL,
    status ENUM('sent', 'failed') NOT NULL DEFAULT 'failed',
    error_message TEXT DEFAULT NULL,
    provider VARCHAR(50) NOT NULL DEFAULT 'ultramsg',
    provider_message_id VARCHAR(120) DEFAULT NULL,
    context_type VARCHAR(80) NOT NULL DEFAULT 'general',
    related_id VARCHAR(120) DEFAULT NULL,
    response_json JSON DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_whatsapp_notification_status (status),
    INDEX idx_whatsapp_notification_context (context_type),
    INDEX idx_whatsapp_notification_related (related_id),
    INDEX idx_whatsapp_notification_phone (recipient_phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
