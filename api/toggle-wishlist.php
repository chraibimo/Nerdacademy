<?php


if (!defined('BASE')) define('BASE', '');

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/wishlist-repo.php';

ensure_wishlist_table($mysqli);

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Must be logged in
$user = auth_current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$courseId = (int)($_POST['course_id'] ?? 0);
if ($courseId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid course_id']);
    exit;
}

$clientId = (int)$user['id'];
$added    = toggle_wishlist($mysqli, $clientId, $courseId);
$count    = get_wishlist_count($mysqli, $courseId);

echo json_encode([
    'added' => $added,
    'count' => $count,
]);
