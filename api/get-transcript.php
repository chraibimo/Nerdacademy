<?php
if (!defined('BASE')) define('BASE', '');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/course-content-repo.php';

header('Content-Type: application/json');

$user = auth_current_user();
if (!$user || !auth_can_access_admin_panel($user)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$lessonId = (int)($_GET['lesson_id'] ?? 0);
if (!$lessonId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing lesson_id']);
    exit;
}

ensure_course_content_tables($mysqli);
$content = get_lesson_transcript($mysqli, $lessonId);

echo json_encode(['content' => $content]);
