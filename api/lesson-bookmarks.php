<?php

if (!defined('BASE')) define('BASE', '');
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// ─── Table bootstrap ─────────────────────────────────────────────────────────

function ensure_bookmark_table(mysqli $mysqli): void
{
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS lesson_bookmarks (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id  BIGINT UNSIGNED NOT NULL,
            lesson_id  INT NOT NULL,
            note       TEXT NOT NULL,
            seconds_at INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_client_lesson (client_id, lesson_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// ─── DB helper functions ──────────────────────────────────────────────────────

/**
 * Return all bookmarks for a given client + lesson, ordered by created_at ASC.
 */
function get_bookmarks(mysqli $mysqli, int $clientId, int $lessonId): array
{
    $stmt = $mysqli->prepare(
        'SELECT id, client_id, lesson_id, note, seconds_at, created_at
           FROM lesson_bookmarks
          WHERE client_id = ? AND lesson_id = ?
          ORDER BY created_at ASC'
    );
    if (!$stmt) return [];
    $stmt->bind_param('ii', $clientId, $lessonId);
    $stmt->execute();
    $res  = $stmt->get_result();
    $stmt->close();

    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['id']         = (int)$row['id'];
            $row['client_id']  = (int)$row['client_id'];
            $row['lesson_id']  = (int)$row['lesson_id'];
            $row['seconds_at'] = (int)$row['seconds_at'];
            $rows[] = $row;
        }
    }
    return $rows;
}

/**
 * Insert a new bookmark. Returns the new id, or 0 on failure.
 */
function add_bookmark(mysqli $mysqli, int $clientId, int $lessonId, string $note, int $secondsAt): int
{
    $stmt = $mysqli->prepare(
        'INSERT INTO lesson_bookmarks (client_id, lesson_id, note, seconds_at)
         VALUES (?, ?, ?, ?)'
    );
    if (!$stmt) return 0;
    $stmt->bind_param('iisi', $clientId, $lessonId, $note, $secondsAt);
    $stmt->execute();
    $newId = (int)$mysqli->insert_id;
    $stmt->close();
    return $newId;
}

/**
 * Delete a bookmark only if it belongs to the given client.
 * Returns true on success.
 */
function delete_bookmark(mysqli $mysqli, int $bookmarkId, int $clientId): bool
{
    $stmt = $mysqli->prepare(
        'DELETE FROM lesson_bookmarks WHERE id = ? AND client_id = ?'
    );
    if (!$stmt) return false;
    $stmt->bind_param('ii', $bookmarkId, $clientId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok && $mysqli->affected_rows > 0;
}

// ─── Bootstrap table ─────────────────────────────────────────────────────────

ensure_bookmark_table($mysqli);

// ─── Auth required for all actions ───────────────────────────────────────────

$user = auth_current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth_required']);
    exit;
}

$clientId = (int)$user['id'];
$method   = $_SERVER['REQUEST_METHOD'];
$action   = trim((string)($_GET['action'] ?? ''));

// ─── Helper ───────────────────────────────────────────────────────────────────

function bm_json(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ─── GET ?action=list&lesson_id=X ────────────────────────────────────────────

if ($method === 'GET' && $action === 'list') {
    $lessonId = (int)($_GET['lesson_id'] ?? 0);
    if ($lessonId <= 0) {
        bm_json(['ok' => false, 'error' => 'missing_lesson_id'], 400);
    }
    $bookmarks = get_bookmarks($mysqli, $clientId, $lessonId);
    bm_json(['ok' => true, 'bookmarks' => $bookmarks]);
}

// ─── POST ?action=add  body: {lesson_id, note, seconds_at} ───────────────────

if ($method === 'POST' && $action === 'add') {
    $raw  = (string)file_get_contents('php://input');
    $body = json_decode($raw, true);

    $lessonId  = (int)($body['lesson_id'] ?? 0);
    $note      = trim((string)($body['note'] ?? ''));
    $secondsAt = (int)($body['seconds_at'] ?? 0);

    if ($lessonId <= 0) bm_json(['ok' => false, 'error' => 'missing_lesson_id'], 400);
    if ($note === '')   bm_json(['ok' => false, 'error' => 'empty_note'], 400);

    // Sanitise
    $note = strip_tags($note);
    if ($note === '') bm_json(['ok' => false, 'error' => 'empty_note_after_sanitise'], 400);

    $newId = add_bookmark($mysqli, $clientId, $lessonId, $note, max(0, $secondsAt));
    if ($newId === 0) bm_json(['ok' => false, 'error' => 'insert_failed'], 500);

    bm_json([
        'ok'       => true,
        'bookmark' => [
            'id'         => $newId,
            'client_id'  => $clientId,
            'lesson_id'  => $lessonId,
            'note'       => $note,
            'seconds_at' => max(0, $secondsAt),
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ]);
}

// ─── POST ?action=delete  body: {bookmark_id} ────────────────────────────────

if ($method === 'POST' && $action === 'delete') {
    $raw  = (string)file_get_contents('php://input');
    $body = json_decode($raw, true);

    $bookmarkId = (int)($body['bookmark_id'] ?? 0);
    if ($bookmarkId <= 0) bm_json(['ok' => false, 'error' => 'missing_bookmark_id'], 400);

    $ok = delete_bookmark($mysqli, $bookmarkId, $clientId);
    bm_json(['ok' => $ok]);
}

// ─── Fallback ─────────────────────────────────────────────────────────────────

bm_json(['ok' => false, 'error' => 'unknown_action'], 400);
