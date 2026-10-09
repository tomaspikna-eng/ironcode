-- Import into the MariaDB database created in Hostinger hPanel / phpMyAdmin.
CREATE TABLE IF NOT EXISTS audit_requests (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 requester VARCHAR(160) NOT NULL,
 description TEXT NOT NULL,
 email VARCHAR(254) NOT NULL,
 phone VARCHAR(32) NOT NULL,
 notification_status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_created (created_at),
 INDEX idx_notification (notification_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
