CREATE TABLE IF NOT EXISTS clients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_uid VARCHAR(128) NOT NULL,
    full_name VARCHAR(150) NOT NULL DEFAULT '',
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NULL,
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    account_status VARCHAR(40) NOT NULL DEFAULT 'pending_verification',
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    provider_ids_json TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_clients_user_uid (user_uid),
    UNIQUE KEY uniq_clients_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
