<?php

require_once __DIR__ . '/db.php';

// ============================================================
//  Schema bootstrap
// ============================================================

function ensure_lesson_comments_table(mysqli $mysqli): void
{
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS lesson_comments (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lesson_id  INT            NOT NULL,
            client_id  BIGINT UNSIGNED NOT NULL,
            parent_id  BIGINT UNSIGNED NULL DEFAULT NULL,
            body       TEXT           NOT NULL,
            status     VARCHAR(20)    NOT NULL DEFAULT 'approved',
            created_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_lesson (lesson_id),
            KEY idx_parent (parent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// ============================================================
//  Public API
// ============================================================

/**
 * Returns top-level comments for a lesson, each with a nested 'replies' array.
 * JOINs with clients for commenter name/email.
 * Non-admin callers only see status='approved' comments.
 *
 * @param  bool $isAdmin  Pass true to include non-approved comments.
 * @return array<int, array<string, mixed>>
 */
function get_lesson_comments(mysqli $mysqli, int $lessonId, bool $isAdmin = false): array
{
    $statusClause = $isAdmin ? '' : "AND lc.status = 'approved'";

    $stmt = $mysqli->prepare("
        SELECT
            lc.id,
            lc.lesson_id,
            lc.client_id,
            lc.parent_id,
            lc.body,
            lc.status,
            lc.created_at,
            c.full_name  AS commenter_name,
            c.email      AS commenter_email
        FROM lesson_comments lc
        LEFT JOIN clients c ON c.id = lc.client_id
        WHERE lc.lesson_id = ?
          AND lc.parent_id IS NULL
          $statusClause
        ORDER BY lc.created_at ASC
    ");

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $lessonId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if (!$result) {
        return [];
    }

    $topLevel = [];
    while ($row = $result->fetch_assoc()) {
        $row['replies'] = [];
        $topLevel[(int)$row['id']] = $row;
    }

    if (empty($topLevel)) {
        return array_values($topLevel);
    }

    // Fetch all replies for these parent comments in one query
    $parentIds = implode(',', array_keys($topLevel));

    $replyStmt = $mysqli->prepare("
        SELECT
            lc.id,
            lc.lesson_id,
            lc.client_id,
            lc.parent_id,
            lc.body,
            lc.status,
            lc.created_at,
            c.full_name  AS commenter_name,
            c.email      AS commenter_email
        FROM lesson_comments lc
        LEFT JOIN clients c ON c.id = lc.client_id
        WHERE lc.lesson_id = ?
          AND lc.parent_id IN ($parentIds)
          $statusClause
        ORDER BY lc.created_at ASC
    ");

    if ($replyStmt) {
        $replyStmt->bind_param('i', $lessonId);
        $replyStmt->execute();
        $replyResult = $replyStmt->get_result();
        $replyStmt->close();

        if ($replyResult) {
            while ($reply = $replyResult->fetch_assoc()) {
                $pid = (int)$reply['parent_id'];
                if (isset($topLevel[$pid])) {
                    $topLevel[$pid]['replies'][] = $reply;
                }
            }
        }
    }

    return array_values($topLevel);
}

/**
 * Inserts a new comment and returns the new row id.
 */
function add_comment(mysqli $mysqli, int $lessonId, int $clientId, string $body, ?int $parentId = null): int
{
    $status = 'approved';

    if ($parentId !== null) {
        $stmt = $mysqli->prepare('
            INSERT INTO lesson_comments (lesson_id, client_id, parent_id, body, status)
            VALUES (?, ?, ?, ?, ?)
        ');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('iiiss', $lessonId, $clientId, $parentId, $body, $status);
    } else {
        $stmt = $mysqli->prepare('
            INSERT INTO lesson_comments (lesson_id, client_id, body, status)
            VALUES (?, ?, ?, ?)
        ');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('iiss', $lessonId, $clientId, $body, $status);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return 0;
    }

    $newId = (int)$mysqli->insert_id;
    $stmt->close();

    return $newId;
}

/**
 * Deletes a comment and all its direct replies.
 */
function delete_comment(mysqli $mysqli, int $commentId): bool
{
    // Delete replies first
    $stmtReplies = $mysqli->prepare('DELETE FROM lesson_comments WHERE parent_id = ?');
    if ($stmtReplies) {
        $stmtReplies->bind_param('i', $commentId);
        $stmtReplies->execute();
        $stmtReplies->close();
    }

    $stmt = $mysqli->prepare('DELETE FROM lesson_comments WHERE id = ?');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $commentId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

/**
 * Returns paginated comment list for the admin panel.
 * Joins with clients and course_lessons/courses_catalog for context.
 *
 * @return array{items: array<int, array<string, mixed>>, total: int}
 */
function get_all_comments_paginated(mysqli $mysqli, int $page = 1, int $perPage = 30, string $search = ''): array
{
    $offset = ($page - 1) * $perPage;

    $whereClause = '1=1';
    $searchParam = null;

    if ($search !== '') {
        $whereClause .= " AND (c.full_name LIKE ? OR c.email LIKE ? OR lc.body LIKE ? OR cl.title LIKE ?)";
        $likeVal = '%' . $search . '%';
        $searchParam = $likeVal;
    }

    // Count total
    if ($searchParam !== null) {
        $countStmt = $mysqli->prepare("
            SELECT COUNT(*) AS total
            FROM lesson_comments lc
            LEFT JOIN clients c  ON c.id = lc.client_id
            LEFT JOIN course_lessons cl ON cl.id = lc.lesson_id
            WHERE $whereClause
        ");
        if (!$countStmt) {
            return ['items' => [], 'total' => 0];
        }
        $countStmt->bind_param('ssss', $searchParam, $searchParam, $searchParam, $searchParam);
    } else {
        $countStmt = $mysqli->prepare("
            SELECT COUNT(*) AS total
            FROM lesson_comments lc
            LEFT JOIN clients c  ON c.id = lc.client_id
            LEFT JOIN course_lessons cl ON cl.id = lc.lesson_id
            WHERE $whereClause
        ");
        if (!$countStmt) {
            return ['items' => [], 'total' => 0];
        }
    }

    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult ? (int)($countResult->fetch_assoc()['total'] ?? 0) : 0;
    $countStmt->close();

    // Fetch items
    if ($searchParam !== null) {
        $itemStmt = $mysqli->prepare("
            SELECT
                lc.id,
                lc.lesson_id,
                lc.client_id,
                lc.parent_id,
                lc.body,
                lc.status,
                lc.created_at,
                c.full_name    AS commenter_name,
                c.email        AS commenter_email,
                cl.title       AS lesson_title,
                cc.title       AS course_title
            FROM lesson_comments lc
            LEFT JOIN clients c         ON c.id  = lc.client_id
            LEFT JOIN course_lessons cl ON cl.id = lc.lesson_id
            LEFT JOIN courses_catalog cc ON cc.id = cl.course_id
            WHERE $whereClause
            ORDER BY lc.created_at DESC
            LIMIT ? OFFSET ?
        ");
        if (!$itemStmt) {
            return ['items' => [], 'total' => $total];
        }
        $itemStmt->bind_param('ssssii', $searchParam, $searchParam, $searchParam, $searchParam, $perPage, $offset);
    } else {
        $itemStmt = $mysqli->prepare("
            SELECT
                lc.id,
                lc.lesson_id,
                lc.client_id,
                lc.parent_id,
                lc.body,
                lc.status,
                lc.created_at,
                c.full_name    AS commenter_name,
                c.email        AS commenter_email,
                cl.title       AS lesson_title,
                cc.title       AS course_title
            FROM lesson_comments lc
            LEFT JOIN clients c         ON c.id  = lc.client_id
            LEFT JOIN course_lessons cl ON cl.id = lc.lesson_id
            LEFT JOIN courses_catalog cc ON cc.id = cl.course_id
            WHERE $whereClause
            ORDER BY lc.created_at DESC
            LIMIT ? OFFSET ?
        ");
        if (!$itemStmt) {
            return ['items' => [], 'total' => $total];
        }
        $itemStmt->bind_param('ii', $perPage, $offset);
    }

    $itemStmt->execute();
    $itemResult = $itemStmt->get_result();
    $itemStmt->close();

    $items = [];
    if ($itemResult) {
        while ($row = $itemResult->fetch_assoc()) {
            $items[] = $row;
        }
    }

    return ['items' => $items, 'total' => $total];
}

/**
 * Updates the status of a comment (approved / rejected / pending).
 */
function update_comment_status(mysqli $mysqli, int $commentId, string $status): bool
{
    $stmt = $mysqli->prepare('UPDATE lesson_comments SET status = ?, updated_at = NOW() WHERE id = ?');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('si', $status, $commentId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}
