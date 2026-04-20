<?php

if (!defined('BASE')) define('BASE', '');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/courses-repo.php';
require_once __DIR__ . '/../includes/course-content-repo.php';
require_once __DIR__ . '/../includes/purchases-repo.php';

ensure_course_content_tables($mysqli);
ensure_purchases_table($mysqli);

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function contains_any(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if ($needle !== '' && str_contains($haystack, $needle)) {
            return true;
        }
    }
    return false;
}

function extract_relevant_snippets(string $text, string $question, int $limit = 3): array
{
    $plainText = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
    if ($plainText === '') {
        return [];
    }

    $stopWords = ['what','which','this','that','with','from','into','about','would','could','should','there','their','them','have','has','will','your','lesson','course'];
    $keywords = preg_split('/[^a-z0-9]+/i', strtolower($question)) ?: [];
    $keywords = array_values(array_filter($keywords, static function ($word) use ($stopWords) {
        return strlen($word) >= 4 && !in_array($word, $stopWords, true);
    }));

    $sentences = preg_split('/(?<=[.!?])\s+/', $plainText) ?: [];
    $matches = [];

    foreach ($sentences as $sentence) {
        $score = 0;
        $lowerSentence = strtolower($sentence);
        foreach ($keywords as $keyword) {
            if (str_contains($lowerSentence, $keyword)) {
                $score++;
            }
        }
        if ($score > 0) {
            $matches[] = ['score' => $score, 'text' => trim($sentence)];
        }
    }

    usort($matches, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    $matches = array_slice($matches, 0, $limit);

    return array_values(array_map(static fn(array $item): string => $item['text'], $matches));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_out(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$raw = (string)file_get_contents('php://input');
$body = json_decode($raw, true);

$courseId = (int)($body['course_id'] ?? 0);
$lessonId = (int)($body['lesson_id'] ?? 0);
$question = trim((string)($body['question'] ?? ''));

if ($courseId <= 0 || $question === '') {
    json_out(['ok' => false, 'error' => 'missing_input'], 400);
}

$course = find_course_by_id($mysqli, $courseId);
if (!$course) {
    json_out(['ok' => false, 'error' => 'course_not_found'], 404);
}

$user = auth_current_user();
$enrolled = $user ? has_user_enrolled_course($mysqli, (int)$user['id'], $courseId) : false;
$modules = get_course_modules($mysqli, $courseId);

$lesson = null;
$transcript = '';
if ($lessonId > 0) {
    $lesson = get_lesson($mysqli, $lessonId);
    if ($lesson && (int)($lesson['course_id'] ?? 0) === $courseId) {
        if (!empty($lesson['is_preview']) || $enrolled || ($user && auth_is_admin($user))) {
            $transcript = get_lesson_transcript($mysqli, $lessonId);
        }
    }
}

$questionLower = strtolower($question);
$answerParts = [];
$suggestions = ['Summarize this lesson', 'What should I focus on next?', 'How do I earn the certificate?'];

if ($lesson) {
    $answerParts[] = 'You are currently in **' . (string)($lesson['title'] ?? 'this lesson') . '** from **' . (string)($course['title'] ?? 'this course') . '**.';
}

if (contains_any($questionLower, ['certificate', 'certification', 'completion'])) {
    $answerParts[] = 'Finish the course to **100% progress** and the certificate becomes available from **My Courses** or the **certificate page**.';
}

if (contains_any($questionLower, ['prerequisite', 'requirements', 'before starting', 'need to know'])) {
    $answerParts[] = 'This course is marked as **' . (string)($course['level'] ?? 'Beginner') . '** level. A good starting point is basic programming knowledge and consistency with hands-on practice.';
}

if (contains_any($questionLower, ['curriculum', 'modules', 'lessons', 'roadmap'])) {
    $moduleTitles = [];
    foreach (array_slice($modules, 0, 5) as $module) {
        $moduleTitles[] = (string)($module['title'] ?? 'Module');
    }
    if ($moduleTitles !== []) {
        $answerParts[] = 'The main learning path covers: ' . implode(', ', $moduleTitles) . '.';
    }
}

if (contains_any($questionLower, ['learn', 'outcomes', 'skills', 'gain'])) {
    $outcomes = array_slice((array)($course['outcomes'] ?? []), 0, 4);
    if ($outcomes !== []) {
        $answerParts[] = "Key outcomes include:\n- " . implode("\n- ", array_map('strval', $outcomes));
    }
}

if (contains_any($questionLower, ['next', 'focus', 'continue', 'study plan'])) {
    if ($modules !== []) {
        $nextItems = [];
        foreach ($modules as $module) {
            foreach (array_slice((array)($module['lessons'] ?? []), 0, 2) as $moduleLesson) {
                $nextItems[] = (string)($moduleLesson['title'] ?? 'Lesson');
                if (count($nextItems) >= 3) {
                    break 2;
                }
            }
        }
        if ($nextItems !== []) {
            $answerParts[] = 'A good next focus is: ' . implode(' → ', $nextItems) . '.';
        }
    }
}

$knowledgeBase = trim((string)($transcript . "\n\n" . ($course['description'] ?? '') . "\n\n" . implode("\n", (array)($course['outcomes'] ?? []))));
$snippets = extract_relevant_snippets($knowledgeBase, $question, 3);
if ($snippets !== []) {
    $answerParts[] = "Relevant guidance from the course content:\n- " . implode("\n- ", $snippets);
}

if ($answerParts === []) {
    $answerParts[] = 'This course focuses on **' . (string)($course['category'] ?? 'AI') . '** and is designed to help you build practical skills through the curriculum, outcomes, and lesson materials.';
    $answerParts[] = 'Try asking about the curriculum, prerequisites, certificate, or a summary of the current lesson.';
}

if (!$enrolled && $lesson && empty($lesson['is_preview'])) {
    $answerParts[] = 'For deeper lesson-specific help, enroll in the course so the assistant can use the full lesson transcript and progress context.';
}

json_out([
    'ok' => true,
    'answer' => implode("\n\n", $answerParts),
    'suggestions' => $suggestions,
]);
