<?php

if (!defined('BASE')) define('BASE', '');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/course-content-repo.php';
require_once __DIR__ . '/../includes/purchases-repo.php';

ensure_course_content_tables($mysqli);
ensure_purchases_table($mysqli);
ensure_course_progress_table($mysqli);

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function lesson_total_count(array $modules): int
{
    $count = 0;
    foreach ($modules as $module) {
        $count += count($module['lessons'] ?? []);
    }
    return $count;
}

function lesson_completed_count(array $progressMap): int
{
    $count = 0;
    foreach ($progressMap as $entry) {
        if (!empty($entry['completed'])) {
            $count++;
        }
    }
    return $count;
}

$user = auth_current_user();
if (!$user) {
    json_out(['ok' => false, 'error' => 'auth_required'], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = trim((string)($_GET['action'] ?? 'save'));

if ($method === 'GET' && $action === 'get') {
    $lessonId = (int)($_GET['lesson_id'] ?? 0);
    if ($lessonId <= 0) {
        json_out(['ok' => false, 'error' => 'missing_lesson_id'], 400);
    }

    $lesson = get_lesson($mysqli, $lessonId);
    if (!$lesson) {
        json_out(['ok' => false, 'error' => 'lesson_not_found'], 404);
    }

    $courseId = (int)($lesson['course_id'] ?? 0);
    if (empty($lesson['is_preview']) && !has_user_enrolled_course($mysqli, (int)$user['id'], $courseId) && !auth_is_admin($user)) {
        json_out(['ok' => false, 'error' => 'enrollment_required'], 403);
    }

    $progressMap = get_user_lesson_progress($mysqli, (int)$user['id'], $courseId);
    $entry = $progressMap[$lessonId] ?? ['completed' => false, 'watched_seconds' => 0];
    json_out(['ok' => true, 'progress' => $entry]);
}

if ($method === 'POST' && $action === 'save') {
    $raw = (string)file_get_contents('php://input');
    $body = json_decode($raw, true);

    $lessonId = (int)($body['lesson_id'] ?? 0);
    $watchedSeconds = max(0, (int)($body['watched_seconds'] ?? 0));
    $completed = !empty($body['completed']);

    if ($lessonId <= 0) {
        json_out(['ok' => false, 'error' => 'missing_lesson_id'], 400);
    }

    $lesson = get_lesson($mysqli, $lessonId);
    if (!$lesson) {
        json_out(['ok' => false, 'error' => 'lesson_not_found'], 404);
    }

    $courseId = (int)($lesson['course_id'] ?? 0);
    if (empty($lesson['is_preview']) && !has_user_enrolled_course($mysqli, (int)$user['id'], $courseId) && !auth_is_admin($user)) {
        json_out(['ok' => false, 'error' => 'enrollment_required'], 403);
    }

    $durationSeconds = max(0, (int)($lesson['duration_seconds'] ?? 0));
    if (!$completed && $durationSeconds > 0 && $watchedSeconds >= max(30, (int)floor($durationSeconds * 0.9))) {
        $completed = true;
    }

    $ok = save_lesson_progress($mysqli, (int)$user['id'], $lessonId, $watchedSeconds, $completed);
    if (!$ok) {
        json_out(['ok' => false, 'error' => 'save_failed'], 500);
    }

    $modules = get_course_modules($mysqli, $courseId);
    $progressMap = get_user_lesson_progress($mysqli, (int)$user['id'], $courseId);
    $totalLessons = lesson_total_count($modules);
    $completedCount = lesson_completed_count($progressMap);
    $progressPercent = $totalLessons > 0 ? (int)round($completedCount / $totalLessons * 100) : 0;
    set_course_progress($mysqli, (int)$user['id'], $courseId, $progressPercent, (string)$lessonId);

    $entry = $progressMap[$lessonId] ?? ['completed' => $completed, 'watched_seconds' => $watchedSeconds];
    json_out([
        'ok' => true,
        'progress' => $entry,
        'course_progress_percent' => $progressPercent,
        'course_completed' => $progressPercent >= 100,
    ]);
}

json_out(['ok' => false, 'error' => 'unknown_action'], 400);
