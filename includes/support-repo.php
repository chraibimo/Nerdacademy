<?php


require_once __DIR__ . '/db.php';

function ensure_support_tickets_table(mysqli $mysqli): void
{
    $mysqli->query("CREATE TABLE IF NOT EXISTS support_tickets (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        client_id BIGINT UNSIGNED NOT NULL,
        subject VARCHAR(190) NOT NULL,
        category VARCHAR(60) NOT NULL DEFAULT 'general',
        priority VARCHAR(20) NOT NULL DEFAULT 'normal',
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        message TEXT NOT NULL,
        admin_notes TEXT NULL,
        assigned_to BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_tickets_client (client_id),
        KEY idx_tickets_status (status),
        KEY idx_tickets_assigned (assigned_to)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function create_support_ticket(mysqli $mysqli, int $clientId, string $subject, string $category, string $priority, string $message): bool
{
    ensure_support_tickets_table($mysqli);

    $stmt = $mysqli->prepare('INSERT INTO support_tickets (client_id, subject, category, priority, status, message) VALUES (?, ?, ?, ?, "open", ?)');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('issss', $clientId, $subject, $category, $priority, $message);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}
