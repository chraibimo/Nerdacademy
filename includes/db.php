<?php
// Basic MySQL connection for XAMPP local setup.
// Update credentials if your MySQL config is different.


$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_PORT = (int)(getenv('DB_PORT') ?: 3306);
$DB_NAME = getenv('DB_NAME') ?: 'ai_courses';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, '', $DB_PORT);

if ($mysqli->connect_errno) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'db-connect-failed',
        'message' => 'Unable to connect to MySQL. Check includes/db.php credentials.'
    ]);
    exit;
}

// Ensure the target database exists, then select it.
$safeDbName = preg_replace('/[^a-zA-Z0-9_]/', '', $DB_NAME);
if ($safeDbName === '') {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'invalid-db-name',
        'message' => 'Invalid DB_NAME value in includes/db.php.'
    ]);
    exit;
}

$createDbSql = "CREATE DATABASE IF NOT EXISTS `{$safeDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if (!$mysqli->query($createDbSql)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'db-create-failed',
        'message' => 'Unable to create/select database ' . $safeDbName . ': ' . $mysqli->error
    ]);
    exit;
}

if (!$mysqli->select_db($safeDbName)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'db-select-failed',
        'message' => 'Unable to select database ' . $safeDbName . ': ' . $mysqli->error
    ]);
    exit;
}

$mysqli->set_charset('utf8mb4');

// Helper for non-fatal schema migrations when mysqli exception mode is enabled.
$runOptionalMigration = static function (mysqli $conn, string $sql, array $ignoreErrorCodes = []): void {
    try {
        $ok = $conn->query($sql);
        if ($ok === false) {
            $errno = (int)$conn->errno;
            if (!in_array($errno, $ignoreErrorCodes, true)) {
                throw new RuntimeException('Schema migration failed: ' . $conn->error);
            }
        }
    } catch (mysqli_sql_exception $e) {
        $code = (int)$e->getCode();
        if (!in_array($code, $ignoreErrorCodes, true)) {
            throw $e;
        }
    }
};

$createClientsSql = "
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
";

$runOptionalMigration($mysqli, $createClientsSql);

$addPasswordHashSql = 'ALTER TABLE clients ADD COLUMN password_hash VARCHAR(255) NULL AFTER email';
$runOptionalMigration($mysqli, $addPasswordHashSql, [1060]);

$addIsAdminSql = 'ALTER TABLE clients ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER role';
$runOptionalMigration($mysqli, $addIsAdminSql, [1060]);

// One-time lightweight migration: rename legacy firebase_uid column if present.
$renameSql = 'ALTER TABLE clients CHANGE COLUMN firebase_uid user_uid VARCHAR(128) NOT NULL';
$runOptionalMigration($mysqli, $renameSql, [1054, 1146]);
