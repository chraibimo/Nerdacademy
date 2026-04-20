<?php
if (!defined('BASE')) define('BASE', '');

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/quiz-repo.php';

ensure_quiz_tables($mysqli);

// ── Auth check ────────────────────────────────────────────────────────────────
$user = auth_current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// ── Method check ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ── Parse JSON body ───────────────────────────────────────────────────────────
$rawBody = (string)file_get_contents('php://input');
$body    = json_decode($rawBody, true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$quizId  = isset($body['quiz_id'])  ? (int)$body['quiz_id']  : 0;
$answers = isset($body['answers'])  && is_array($body['answers']) ? $body['answers'] : [];

if ($quizId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'quiz_id is required']);
    exit;
}

// ── Verify quiz exists ────────────────────────────────────────────────────────
$quiz = get_quiz_by_id($mysqli, $quizId);
if (!$quiz) {
    http_response_code(404);
    echo json_encode(['error' => 'Quiz not found']);
    exit;
}

// ── Sanitise answers: keys and values must be integer-castable ────────────────
$sanitisedAnswers = [];
foreach ($answers as $questionId => $optionId) {
    $qid = (int)$questionId;
    $oid = (int)$optionId;
    if ($qid > 0 && $oid > 0) {
        $sanitisedAnswers[$qid] = $oid;
    }
}

// ── Submit attempt ────────────────────────────────────────────────────────────
$clientId = (int)$user['id'];
$result   = submit_quiz_attempt($mysqli, $quizId, $clientId, $sanitisedAnswers);

// ── Return result ─────────────────────────────────────────────────────────────
echo json_encode([
    'score'      => $result['score'],
    'passed'     => $result['passed'],
    'correct'    => $result['correct'],
    'total'      => $result['total'],
    'pass_score' => $quiz['pass_score'],
]);
