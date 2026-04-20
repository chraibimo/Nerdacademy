<?php
// Basic MySQL connection.
// Reads from environment variables (getenv or $_SERVER via Apache SetEnv).

// Helper: read env var from getenv() or $_SERVER (Apache SetEnv uses $_SERVER).
function _env(string $key, string $default = ''): string {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    return $_SERVER[$key] ?? $_ENV[$key] ?? $default;
}

$DB_HOST = _env('DB_HOST', '127.0.0.1');
$DB_PORT = (int)_env('DB_PORT', '3306');
$DB_NAME = _env('DB_NAME', 'ai_courses');
$DB_USER = _env('DB_USER', 'root');
$DB_PASS = _env('DB_PASS', '');

// Connect directly to the named database (shared hosting won't allow CREATE DATABASE).
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);

if ($mysqli->connect_errno) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'db-connect-failed',
        'message' => 'Unable to connect to MySQL. Check includes/db.php credentials. (' . $mysqli->connect_error . ')'
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
