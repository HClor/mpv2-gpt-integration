CREATE TABLE IF NOT EXISTS `modx_pending_emails` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email_type` VARCHAR(64) NOT NULL,
  `recipient_email` VARCHAR(191) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body_html` MEDIUMTEXT NOT NULL,
  `status` ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `available_at` DATETIME NOT NULL,
  `sent_at` DATETIME NULL,
  `last_error` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pending_status_available` (`status`, `available_at`),
  KEY `idx_pending_recipient` (`recipient_email`),
  KEY `idx_pending_type` (`email_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
