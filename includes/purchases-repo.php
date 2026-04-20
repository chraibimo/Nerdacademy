<?php


require_once __DIR__ . '/db.php';

function ensure_purchases_table(mysqli $mysqli): void
{
    $mysqli->query("CREATE TABLE IF NOT EXISTS purchases (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        client_id BIGINT UNSIGNED NOT NULL,
        course_id INT NOT NULL,
        original_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        coupon_code VARCHAR(50) NULL,
        discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
        currency VARCHAR(10) NOT NULL DEFAULT 'USD',
        status VARCHAR(20) NOT NULL DEFAULT 'completed',
        purchased_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_client_course (client_id, course_id),
        KEY idx_purchases_client_id (client_id),
        KEY idx_purchases_coupon (coupon_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    try {
        $mysqli->query("ALTER TABLE purchases ADD COLUMN original_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER course_id");
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() !== 1060) {
            throw $e;
        }
    }

    try {
        $mysqli->query("ALTER TABLE purchases ADD COLUMN coupon_code VARCHAR(50) NULL AFTER amount");
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() !== 1060) {
            throw $e;
        }
    }

    try {
        $mysqli->query("ALTER TABLE purchases ADD COLUMN discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER coupon_code");
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() !== 1060) {
            throw $e;
        }
    }
}

function has_user_enrolled_course(mysqli $mysqli, int $clientId, int $courseId): bool
{
    ensure_purchases_table($mysqli);

    $stmt = $mysqli->prepare('SELECT 1 FROM purchases WHERE client_id = ? AND course_id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ii', $clientId, $courseId);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }

    $result = $stmt->get_result();
    $exists = (bool)($result && $result->fetch_assoc());
    $stmt->close();

    return $exists;
}

function get_user_enrolled_course_ids(mysqli $mysqli, int $clientId): array
{
    ensure_purchases_table($mysqli);

    $ids = [];
    $stmt = $mysqli->prepare('SELECT course_id FROM purchases WHERE client_id = ? ORDER BY purchased_at DESC');
    if (!$stmt) {
        return $ids;
    }

    $stmt->bind_param('i', $clientId);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result?->fetch_assoc()) {
            $ids[] = (int)($row['course_id'] ?? 0);
        }
    }
    $stmt->close();

    return $ids;
}

function ensure_course_progress_table(mysqli $mysqli): void
{
    $mysqli->query("CREATE TABLE IF NOT EXISTS course_progress (
        client_id BIGINT UNSIGNED NOT NULL,
        course_id INT NOT NULL,
        progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
        last_lesson VARCHAR(190) NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (client_id, course_id),
        KEY idx_course_progress_course (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function set_course_progress(mysqli $mysqli, int $clientId, int $courseId, int $progressPercent, ?string $lastLesson = null): bool
{
    ensure_course_progress_table($mysqli);
    $progressPercent = max(0, min(100, $progressPercent));

    $stmt = $mysqli->prepare('INSERT INTO course_progress (client_id, course_id, progress_percent, last_lesson) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE progress_percent = VALUES(progress_percent), last_lesson = VALUES(last_lesson), updated_at = NOW()');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('iiis', $clientId, $courseId, $progressPercent, $lastLesson);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function get_user_progress_map(mysqli $mysqli, int $clientId): array
{
    ensure_course_progress_table($mysqli);

    $map = [];
    $stmt = $mysqli->prepare('SELECT course_id, progress_percent, last_lesson FROM course_progress WHERE client_id = ?');
    if (!$stmt) {
        return $map;
    }

    $stmt->bind_param('i', $clientId);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result?->fetch_assoc()) {
            $cid = (int)($row['course_id'] ?? 0);
            $map[$cid] = [
                'progress_percent' => (int)($row['progress_percent'] ?? 0),
                'last_lesson' => (string)($row['last_lesson'] ?? ''),
            ];
        }
    }
    $stmt->close();

    return $map;
}

/**
 * Returns a map of course_id => purchase details for a given user.
 */
function get_user_purchases_map(mysqli $mysqli, int $clientId): array
{
    ensure_purchases_table($mysqli);

    $map = [];
    $stmt = $mysqli->prepare('SELECT id, course_id, original_amount, amount, coupon_code, discount_percent, currency, status, purchased_at FROM purchases WHERE client_id = ? ORDER BY purchased_at DESC');
    if (!$stmt) {
        return $map;
    }

    $stmt->bind_param('i', $clientId);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result?->fetch_assoc()) {
            $cid = (int)($row['course_id'] ?? 0);
            $map[$cid] = $row;
        }
    }
    $stmt->close();

    return $map;
}
