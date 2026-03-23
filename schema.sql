-- CliUpload Database Schema
-- Created: 2026-03-23

-- Admin settings table
CREATE TABLE IF NOT EXISTS `admin_settings` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Files metadata table
CREATE TABLE IF NOT EXISTS `files` (
  `id` VARCHAR(10) PRIMARY KEY,
  `original_name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(127) DEFAULT 'application/octet-stream',
  `size` BIGINT UNSIGNED NOT NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `password_hash` VARCHAR(255) DEFAULT NULL,
  `expiration` TIMESTAMP NULL DEFAULT NULL,
  `is_encrypted` TINYINT(1) DEFAULT 0,
  `one_time_view` TINYINT(1) DEFAULT 0,
  `view_count` INT UNSIGNED DEFAULT 0,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  INDEX `idx_expiration` (`expiration`),
  INDEX `idx_uploaded_at` (`uploaded_at`),
  INDEX `idx_one_time_view` (`one_time_view`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting table
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `ip_address` VARCHAR(45) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `attempt_count` INT UNSIGNED DEFAULT 1,
  `first_attempt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_attempt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `blocked_until` TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY `idx_ip_action` (`ip_address`, `action`),
  INDEX `idx_blocked_until` (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity logs table
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
  `file_id` VARCHAR(10) DEFAULT NULL,
  `action` VARCHAR(50) NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_file_id` (`file_id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_created_at` (`created_at`),
  FOREIGN KEY (`file_id`) REFERENCES `files`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (password: change_me_immediately)
-- Password hash for 'change_me_immediately' - CHANGE THIS AFTER FIRST LOGIN!
INSERT INTO `admin_settings` (`username`, `password_hash`)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE username=username;
