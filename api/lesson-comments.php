<?php

if (!defined('BASE')) define('BASE', '');
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/lesson-comments-repo.php';
require_once __DIR__ . '/../includes/course-content-repo.php';
require_once __DIR__ . '/../includes/purchases-repo.php';

ensure_lesson_comments_table($mysqli);

$user   = auth_current_user();
$method = $_SERVER['REQUEST_METHOD'];
$action = trim((string)($_GET['action'] ?? ''));

// ----------------------------------------------------------------
//  Helper: send JSON response and exit
// ----------------------------------------------------------------
function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ================================================================
//  GET ?action=list&lesson_id=X
// ================================================================
if ($method === 'GET' && $action === 'list') {
    $lessonId = (int)($_GET['lesson_id'] ?? 0);
    if ($lessonId <= 0) {
        json_out(['ok' => false, 'error' => 'missing_lesson_id'], 400);
    }

    ensure_course_content_tables($mysqli);

    // Determine whether the requester may view this lesson's comments.
    // Preview lessons are open to all; otherwise enrollment (or admin) is required.
    $lesson = get_lesson($mysqli, $lessonId);

    if (!$lesson) {
        json_out(['ok' => false, 'error' => 'lesson_not_found'], 404);
    }

    $isPreview = (bool)($lesson['is_preview'] ?? false);
    $isAdmin   = $user && auth_is_admin($user);

    if (!$isPreview && !$isAdmin) {
        if (!$user) {
            json_out(['ok' => false, 'error' => 'auth_required'], 401);
        }
        ensure_purchases_table($mysqli);
        $courseId = (int)($lesson['course_id'] ?? 0);
        if (!has_user_enrolled_course($mysqli, (int)$user['id'], $courseId)) {
            json_out(['ok' => false, 'error' => 'enrollment_required'], 403);
        }
    }

    $comments = get_lesson_comments($mysqli, $lessonId, $isAdmin);
    json_out(['ok' => true, 'comments' => $comments]);
}

// ================================================================
//  POST ?action=add
// ================================================================
if ($method === 'POST' && $action === 'add') {
    if (!$user) {
        json_out(['ok' => false, 'error' => 'auth_required'], 401);
    }

    $raw  = (string)file_get_contents('php://input');
    $body = json_decode($raw, true);

    $lessonId = (int)($body['lesson_id'] ?? 0);
    $rawText  = trim((string)($body['body'] ?? ''));
    $parentId = isset($body['parent_id']) && $body['parent_id'] !== null
        ? (int)$body['parent_id']
        : null;

    if ($lessonId <= 0) {
        json_out(['ok' => false, 'error' => 'missing_lesson_id'], 400);
    }
    if ($rawText === '') {
        json_out(['ok' => false, 'error' => 'empty_body'], 400);
    }

    // Sanitise: strip all HTML tags
    $cleanText = strip_tags($rawText);
    if ($cleanText === '') {
        json_out(['ok' => false, 'error' => 'empty_body_after_sanitise'], 400);
    }

    // Verify lesson exists and user has access
    ensure_course_content_tables($mysqli);
    $lesson = get_lesson($mysqli, $lessonId);
    if (!$lesson) {
        json_out(['ok' => false, 'error' => 'lesson_not_found'], 404);
    }

    $isAdmin  = auth_is_admin($user);
    $isPreview = (bool)($lesson['is_preview'] ?? false);

    if (!$isPreview && !$isAdmin) {
        ensure_purchases_table($mysqli);
        $courseId = (int)($lesson['course_id'] ?? 0);
        if (!has_user_enrolled_course($mysqli, (int)$user['id'], $courseId)) {
            json_out(['ok' => false, 'error' => 'enrollment_required'], 403);
        }
    }

    // If a parent_id is provided, verify it belongs to the same lesson
    if ($parentId !== null) {
        $checkStmt = $mysqli->prepare('SELECT id FROM lesson_comments WHERE id = ? AND lesson_id = ? LIMIT 1');
        if ($checkStmt) {
            $checkStmt->bind_param('ii', $parentId, $lessonId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $parentExists = $checkResult && $checkResult->fetch_assoc();
            $checkStmt->close();
            if (!$parentExists) {
                json_out(['ok' => false, 'error' => 'invalid_parent_id'], 400);
            }
        }
    }

    $clientId = (int)$user['id'];
    $newId    = add_comment($mysqli, $lessonId, $clientId, $cleanText, $parentId);

    if ($newId === 0) {
        json_out(['ok' => false, 'error' => 'insert_failed'], 500);
    }

    // Return the newly created comment
    $stmt = $mysqli->prepare('
        SELECT
            lc.id, lc.lesson_id, lc.client_id, lc.parent_id, lc.body, lc.status, lc.created_at,
            c.full_name AS commenter_name,
            c.email     AS commenter_email
        FROM lesson_comments lc
        LEFT JOIN clients c ON c.id = lc.client_id
        WHERE lc.id = ?
        LIMIT 1
    ');

    $newComment = null;
    if ($stmt) {
        $stmt->bind_param('i', $newId);
        $stmt->execute();
        $r = $stmt->get_result();
        $newComment = $r ? $r->fetch_assoc() : null;
        $stmt->close();
    }

    json_out(['ok' => true, 'comment' => $newComment]);
}

// ================================================================
//  POST ?action=delete
// ================================================================
if ($method === 'POST' && $action === 'delete') {
    if (!$user) {
        json_out(['ok' => false, 'error' => 'auth_required'], 401);
    }

    $raw  = (string)file_get_contents('php://input');
    $body = json_decode($raw, true);

    $commentId = (int)($body['comment_id'] ?? 0);
    if ($commentId <= 0) {
        json_out(['ok' => false, 'error' => 'missing_comment_id'], 400);
    }

    // Load comment to verify ownership
    $stmt = $mysqli->prepare('SELECT id, client_id FROM lesson_comments WHERE id = ? LIMIT 1');
    if (!$stmt) {
        json_out(['ok' => false, 'error' => 'db_error'], 500);
    }

    $stmt->bind_param('i', $commentId);
    $stmt->execute();
    $r       = $stmt->get_result();
    $comment = $r ? $r->fetch_assoc() : null;
    $stmt->close();

    if (!$comment) {
        json_out(['ok' => false, 'error' => 'comment_not_found'], 404);
    }

    $isAdmin = auth_is_admin($user);
    $isOwner = (int)$comment['client_id'] === (int)$user['id'];

    if (!$isOwner && !$isAdmin) {
        json_out(['ok' => false, 'error' => 'forbidden'], 403);
    }

    $ok = delete_comment($mysqli, $commentId);
    json_out(['ok' => $ok]);
}

// ================================================================
//  Fallback: unknown action
// ================================================================
json_out(['ok' => false, 'error' => 'unknown_action'], 400);
