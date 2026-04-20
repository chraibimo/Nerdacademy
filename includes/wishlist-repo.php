<?php


require_once __DIR__ . '/db.php';

function ensure_wishlist_table(mysqli $mysqli): void
{
    $mysqli->query("CREATE TABLE IF NOT EXISTS wishlists (
        client_id BIGINT UNSIGNED NOT NULL,
        course_id INT NOT NULL,
        added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (client_id, course_id),
        KEY idx_wishlists_course (course_id),
        KEY idx_wishlists_client (client_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/**
 * Toggle a course in/out of a user's wishlist.
 * Returns true if the course was ADDED, false if it was REMOVED.
 */
function toggle_wishlist(mysqli $mysqli, int $clientId, int $courseId): bool
{
    if (is_wishlisted($mysqli, $clientId, $courseId)) {
        $stmt = $mysqli->prepare('DELETE FROM wishlists WHERE client_id = ? AND course_id = ?');
        if ($stmt) {
            $stmt->bind_param('ii', $clientId, $courseId);
            $stmt->execute();
            $stmt->close();
        }
        return false;
    }

    $stmt = $mysqli->prepare('INSERT IGNORE INTO wishlists (client_id, course_id, added_at) VALUES (?, ?, NOW())');
    if ($stmt) {
        $stmt->bind_param('ii', $clientId, $courseId);
        $stmt->execute();
        $stmt->close();
    }
    return true;
}

function is_wishlisted(mysqli $mysqli, int $clientId, int $courseId): bool
{
    $stmt = $mysqli->prepare('SELECT 1 FROM wishlists WHERE client_id = ? AND course_id = ? LIMIT 1');
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

/**
 * Returns an array of course_id integers wishlisted by a user.
 */
function get_user_wishlist_ids(mysqli $mysqli, int $clientId): array
{
    $ids = [];
    $stmt = $mysqli->prepare('SELECT course_id FROM wishlists WHERE client_id = ? ORDER BY added_at DESC');
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

/**
 * Returns how many users have wishlisted a given course.
 */
function get_wishlist_count(mysqli $mysqli, int $courseId): int
{
    $stmt = $mysqli->prepare('SELECT COUNT(*) AS cnt FROM wishlists WHERE course_id = ?');
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $courseId);
    if (!$stmt->execute()) {
        $stmt->close();
        return 0;
    }

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int)($row['cnt'] ?? 0);
}

/**
 * Returns full course rows (from courses_catalog) for all courses a user has wishlisted,
 * ordered by the time they were added to the wishlist (newest first).
 */
function get_user_wishlist_courses(mysqli $mysqli, int $clientId): array
{
    $courses = [];
    $stmt = $mysqli->prepare(
        'SELECT c.* FROM courses_catalog c
         INNER JOIN wishlists w ON w.course_id = c.id
         WHERE w.client_id = ? AND c.is_active = 1
         ORDER BY w.added_at DESC'
    );
    if (!$stmt) {
        return $courses;
    }

    $stmt->bind_param('i', $clientId);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result?->fetch_assoc()) {
            $courses[] = $row;
        }
    }
    $stmt->close();

    return $courses;
}
