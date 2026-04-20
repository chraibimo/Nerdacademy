<?php

if (!defined('BASE')) define('BASE', '');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/courses-repo.php';
require_once __DIR__ . '/includes/purchases-repo.php';
require_once __DIR__ . '/includes/course-content-repo.php';
require_once __DIR__ . '/includes/quiz-repo.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ensure_course_content_tables($mysqli);
ensure_quiz_tables($mysqli);

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * Format seconds as "mm:ss" or "h:mm:ss".
 */
function format_duration(int $seconds): string
{
    if ($seconds <= 0) return '';
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    if ($h > 0) {
        return sprintf('%d:%02d:%02d', $h, $m, $s);
    }
    return sprintf('%d:%02d', $m, $s);
}

/**
 * Extract a YouTube video ID from common URL formats.
 * Handles: youtu.be/ID, youtube.com/watch?v=ID, youtube.com/embed/ID, plain ID.
 */
function extract_youtube_id(string $url): string
{
    if (empty($url)) return '';

    // Already a bare ID (11 chars, no slashes)?
    if (preg_match('/^[A-Za-z0-9_\-]{11}$/', trim($url))) {
        return trim($url);
    }

    // youtu.be/ID
    if (preg_match('/youtu\.be\/([A-Za-z0-9_\-]{11})/i', $url, $m)) {
        return $m[1];
    }

    // youtube.com/watch?v=ID or youtube.com/embed/ID or youtube.com/v/ID
    if (preg_match('/youtube\.com\/(?:watch\?v=|embed\/|v\/)([A-Za-z0-9_\-]{11})/i', $url, $m)) {
        return $m[1];
    }

    // ?v= anywhere
    if (preg_match('/[?&]v=([A-Za-z0-9_\-]{11})/i', $url, $m)) {
        return $m[1];
    }

    return '';
}

/**
 * Extract a Vimeo video ID from common URL formats.
 */
function extract_vimeo_id(string $url): string
{
    if (empty($url)) return '';

    // Already bare numeric ID
    if (preg_match('/^\d+$/', trim($url))) {
        return trim($url);
    }

    if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/i', $url, $m)) {
        return $m[1];
    }

    return '';
}

  function media_mime_from_url(string $url, bool $audio = false): string
  {
    $path = parse_url($url, PHP_URL_PATH);
    $ext  = strtolower((string)pathinfo((string)$path, PATHINFO_EXTENSION));

    if ($audio) {
      return match ($ext) {
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg', 'oga' => 'audio/ogg',
        'm4a', 'aac' => 'audio/mp4',
        'webm' => 'audio/webm',
        default => 'audio/mpeg',
      };
    }

    return match ($ext) {
      'webm' => 'video/webm',
      'ogv' => 'video/ogg',
      'mov' => 'video/quicktime',
      'm4v' => 'video/mp4',
      default => 'video/mp4',
    };
  }

/**
 * Build a video embed HTML string for the given lesson.
 * Returns empty string if no video is available.
 */
function build_video_embed(array $lesson): string
{
    $type = (string)($lesson['video_type'] ?? 'youtube');
    $url  = (string)($lesson['video_url'] ?? '');

    if ($url === '') {
        return '';
    }

    if ($type === 'youtube') {
        $vid = extract_youtube_id($url);
        if ($vid === '') return '';
        $src = 'https://www.youtube-nocookie.com/embed/' . htmlspecialchars($vid, ENT_QUOTES)
             . '?rel=0&modestbranding=1&showinfo=0';
        return '<iframe src="' . $src . '" '
             . 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" '
             . 'allowfullscreen title="' . htmlspecialchars((string)($lesson['title'] ?? ''), ENT_QUOTES) . '"></iframe>';
    }

    if ($type === 'vimeo') {
        $vid = extract_vimeo_id($url);
        if ($vid === '') return '';
        $src = 'https://player.vimeo.com/video/' . htmlspecialchars($vid, ENT_QUOTES)
             . '?dnt=1&title=0&byline=0&portrait=0';
        return '<iframe src="' . $src . '" '
             . 'allow="autoplay; fullscreen; picture-in-picture" '
             . 'allowfullscreen title="' . htmlspecialchars((string)($lesson['title'] ?? ''), ENT_QUOTES) . '"></iframe>';
    }

    if ($type === 'mp4') {
        $safeUrl    = htmlspecialchars($url, ENT_QUOTES);
        $mime       = htmlspecialchars(media_mime_from_url($url), ENT_QUOTES);
        $subtitleTrack = '';
        $subtitleUrl = (string)($lesson['subtitle_url'] ?? '');
        if ($subtitleUrl !== '') {
            $safeSubUrl = htmlspecialchars($subtitleUrl, ENT_QUOTES);
            $subtitleTrack = '<track kind="subtitles" src="' . $safeSubUrl . '" srclang="en" label="English">';
        }
        return '<video id="cp-video" controls controlsList="nodownload noremoteplayback" disablePictureInPicture '
             . 'oncontextmenu="return false" preload="metadata">'
           . '<source src="' . $safeUrl . '" type="' . $mime . '">'
           . $subtitleTrack
             . 'Your browser does not support HTML5 video.'
             . '</video>';
    }

        if ($type === 'audio') {
         $safeUrl = htmlspecialchars($url, ENT_QUOTES);
         $mime    = htmlspecialchars(media_mime_from_url($url, true), ENT_QUOTES);
         return '<audio id="cp-video" controls preload="metadata" style="width:100%;max-width:900px;display:block;margin:0 auto;">'
           . '<source src="' . $safeUrl . '" type="' . $mime . '">'
           . 'Your browser does not support HTML5 audio.'
           . '</audio>';
        }

    if ($type === 'gdrive') {
        $embedUrl = normalize_gdrive_url($url);
        if ($embedUrl === '') return '';
        $src = htmlspecialchars($embedUrl, ENT_QUOTES);
        return '<iframe src="' . $src . '" '
             . 'allow="autoplay; fullscreen; picture-in-picture" '
             . 'allowfullscreen title="' . htmlspecialchars((string)($lesson['title'] ?? ''), ENT_QUOTES) . '"></iframe>';
    }

    return '';
}

/**
 * Count total lessons across all modules.
 */
function count_total_lessons(array $modules): int
{
    $total = 0;
    foreach ($modules as $mod) {
        $total += count($mod['lessons'] ?? []);
    }
    return $total;
}

/**
 * Count how many lessons the user has completed (from progress map).
 */
function count_completed_lessons(array $progressMap): int
{
    $count = 0;
    foreach ($progressMap as $entry) {
        if (!empty($entry['completed'])) {
            $count++;
        }
    }
    return $count;
}

/**
 * Flat list of all lessons in order from modules array.
 */
function flatten_lessons(array $modules): array
{
    $lessons = [];
    foreach ($modules as $mod) {
        foreach (($mod['lessons'] ?? []) as $lesson) {
            $lessons[] = $lesson;
        }
    }
    return $lessons;
}

// ─── Request setup ──────────────────────────────────────────────────────────

$courseId = (int)($_GET['course'] ?? 0);
$lessonId = (int)($_GET['lesson'] ?? 0);

if ($courseId <= 0) {
    header('Location: ' . BASE . '/courses.php');
    exit;
}

// Load course
$course = find_course_by_id($mysqli, $courseId);
if (!$course) {
    header('Location: ' . BASE . '/courses.php');
    exit;
}

// Auth check
$user = auth_current_user();
if (!$user) {
    $returnUrl = urlencode($_SERVER['REQUEST_URI'] ?? (BASE . '/course-player.php?course=' . $courseId));
    header('Location: ' . BASE . '/login.php?redirect=' . $returnUrl);
    exit;
}

$clientId  = (int)$user['id'];
$isEnrolled = has_user_enrolled_course($mysqli, $clientId, $courseId);
$courseProgressMap = get_user_progress_map($mysqli, $clientId);
$savedCourseProgress = $courseProgressMap[$courseId] ?? ['progress_percent' => 0, 'last_lesson' => ''];

// Load modules (with lessons)
$modules = get_course_modules($mysqli, $courseId);

// ─── POST handlers ──────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'mark_complete') {
        $postLessonId = (int)($_POST['lesson_id'] ?? 0);
        if ($postLessonId > 0 && $isEnrolled) {
            mark_lesson_complete($mysqli, $clientId, $postLessonId);

            // Update aggregate course progress
            $progressMap = get_user_lesson_progress($mysqli, $clientId, $courseId);
            $totalLessons = count_total_lessons($modules);
            $completedCount = count_completed_lessons($progressMap);
            if ($totalLessons > 0) {
                $pct = (int)round($completedCount / $totalLessons * 100);
                set_course_progress($mysqli, $clientId, $courseId, $pct, (string)$postLessonId);
            }
        }
        header('Location: ' . BASE . '/course-player.php?course=' . $courseId . '&lesson=' . $postLessonId . '&done=1');
        exit;
    }

    if ($action === 'save_note') {
        $postLessonId = (int)($_POST['lesson_id'] ?? 0);
        $noteText     = (string)($_POST['note'] ?? '');
        if ($postLessonId > 0) {
            if (!isset($_SESSION['notes']) || !is_array($_SESSION['notes'])) {
                $_SESSION['notes'] = [];
            }
            $_SESSION['notes'][$postLessonId] = $noteText;
        }
        header('Location: ' . BASE . '/course-player.php?course=' . $courseId . '&lesson=' . $postLessonId . '&tab=notes&saved=1');
        exit;
    }
}

// ─── Resolve current lesson ─────────────────────────────────────────────────

// Build flat ordered list for easy prev/next and lookup
$allLessons = flatten_lessons($modules);

if ($lessonId <= 0) {
    // No lesson in URL: resume the last opened lesson when possible.
    $resumeLessonId = (int)($savedCourseProgress['last_lesson'] ?? 0);
    if ($resumeLessonId > 0) {
        foreach ($allLessons as $candidateLesson) {
            if ((int)$candidateLesson['id'] === $resumeLessonId) {
                header('Location: ' . BASE . '/course-player.php?course=' . $courseId . '&lesson=' . $resumeLessonId . '&resume=1');
                exit;
            }
        }
    }

    if (!empty($allLessons)) {
        $firstLesson = $allLessons[0];
        header('Location: ' . BASE . '/course-player.php?course=' . $courseId . '&lesson=' . (int)$firstLesson['id']);
    } else {
        header('Location: ' . BASE . '/course.php?id=' . $courseId);
    }
    exit;
}

// Find current lesson in flat list
$currentLesson = null;
foreach ($allLessons as $l) {
    if ((int)$l['id'] === $lessonId) {
        $currentLesson = $l;
        break;
    }
}

// Lesson not found
if (!$currentLesson) {
    header('Location: ' . BASE . '/course-player.php?course=' . $courseId);
    exit;
}

// Access guard: if not enrolled, lesson must be a preview
if (!$isEnrolled && !$currentLesson['is_preview']) {
    header('Location: ' . BASE . '/course.php?id=' . $courseId . '&msg=enroll_required');
    exit;
}

// ─── Load supporting data ────────────────────────────────────────────────────

$transcript   = get_lesson_transcript($mysqli, $lessonId);
$adjacent     = get_adjacent_lessons($mysqli, $lessonId);
$progressMap  = get_user_lesson_progress($mysqli, $clientId, $courseId);

$prevLesson   = $adjacent['prev'];
$nextLesson   = $adjacent['next'];

$totalLessons     = count_total_lessons($modules);
$completedCount   = count_completed_lessons($progressMap);
$progressPercent  = $totalLessons > 0 ? (int)round($completedCount / $totalLessons * 100) : 0;

$isCurrentCompleted = !empty($progressMap[$lessonId]['completed']);
$savedWatchSeconds  = (int)($progressMap[$lessonId]['watched_seconds'] ?? 0);
$resumeTimeLabel    = $savedWatchSeconds > 0 ? format_duration($savedWatchSeconds) : '';
$courseCompleted    = $progressPercent >= 100;

// Notes from session
$savedNote = (string)(($_SESSION['notes'][$lessonId] ?? ''));

// Flash state
$justCompleted    = isset($_GET['done']) && $_GET['done'] === '1';
$noteSaved        = isset($_GET['saved']) && $_GET['saved'] === '1';
$resumedFromSaved = isset($_GET['resume']) && $_GET['resume'] === '1';
$activeTab     = in_array(($_GET['tab'] ?? ''), ['transcript', 'notes', 'overview', 'qa'], true)
                    ? $_GET['tab']
                    : 'transcript';

// Build video HTML
$videoEmbed = build_video_embed($currentLesson);
$isMP4      = (string)($currentLesson['video_type'] ?? '') === 'mp4';
$hasSubtitles = $isMP4 && !empty($currentLesson['subtitle_url']);

// Quiz data
$lessonQuiz      = get_quiz_by_lesson($mysqli, $lessonId);
$quizBestAttempt = null;
$quizAlreadyPassed = false;
if ($lessonQuiz && $isEnrolled) {
    $quizBestAttempt   = get_best_attempt($mysqli, (int)$lessonQuiz['id'], $clientId);
    $quizAlreadyPassed = $quizBestAttempt && (bool)$quizBestAttempt['passed'];
}

// Find which module the current lesson belongs to (for auto-opening in sidebar)
$currentModuleId = (int)($currentLesson['module_id'] ?? 0);

// Page title
$pageTitle = htmlspecialchars((string)($currentLesson['title'] ?? 'Lesson'), ENT_QUOTES)
           . ' — '
           . htmlspecialchars((string)($course['title'] ?? 'Course'), ENT_QUOTES);

// Detect saved theme preference (will be applied via JS; default to dark server-side)
// We output a small inline script so there's no flash of wrong theme
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $pageTitle ?> | NerdAcademy</title>
  <meta name="robots" content="noindex">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE ?>/assets/css/player.css">
  <!-- Prevent theme flash -->
  <script>
    (function(){
      var t = localStorage.getItem('na_theme');
      if (t === 'light' || t === 'dark') {
        document.documentElement.setAttribute('data-theme', t);
      }
    })();
  </script>
  <style>
/* ─── Playback speed control ─────────────────────────────────────────────── */
.cp-speed-row {
  display: flex;
  align-items: center;
  gap: .4rem;
  padding: .5rem .75rem;
  background: var(--bg-secondary, #1e1e2e);
  flex-wrap: wrap;
}
.cp-speed-label {
  font-size: .78rem;
  color: var(--text-muted, #888);
  margin-right: .25rem;
  white-space: nowrap;
}
.cp-speed-btn {
  padding: .25rem .55rem;
  border-radius: .35rem;
  border: 1px solid var(--border, #333);
  background: transparent;
  color: var(--text-secondary, #ccc);
  font-size: .78rem;
  cursor: pointer;
  transition: background .15s, color .15s;
  font-family: inherit;
}
.cp-speed-btn:hover { background: var(--bg-hover, #2a2a3a); }
.cp-speed-btn.active {
  background: #6366f1;
  color: #fff;
  border-color: #6366f1;
}
/* Subtitle toggle */
.cp-subtitle-btn {
  padding: .25rem .6rem;
  border-radius: .35rem;
  border: 1px solid var(--border, #333);
  background: transparent;
  color: var(--text-secondary, #ccc);
  font-size: .78rem;
  cursor: pointer;
  transition: background .15s, color .15s;
  font-family: inherit;
  margin-left: auto;
}
.cp-subtitle-btn:hover { background: var(--bg-hover, #2a2a3a); }
.cp-subtitle-btn.active {
  background: #6366f1;
  color: #fff;
  border-color: #6366f1;
}

/* ─── Quiz modal ─────────────────────────────────────────────────────────── */
.cp-quiz-btn {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .5rem 1rem;
  background: #f59e0b;
  color: #1a1a1a;
  border: none;
  border-radius: .5rem;
  font-size: .875rem;
  font-weight: 600;
  cursor: pointer;
  margin-top: .5rem;
  font-family: inherit;
  transition: background .15s;
}
.cp-quiz-btn:hover { background: #d97706; }
.cp-quiz-passed-badge {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  padding: .4rem .85rem;
  background: rgba(16,185,129,.15);
  color: #10b981;
  border: 1px solid rgba(16,185,129,.3);
  border-radius: .5rem;
  font-size: .83rem;
  font-weight: 600;
  margin-top: .5rem;
}

/* Modal overlay */
.cp-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.75);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
.cp-modal-overlay.hidden { display: none; }
.cp-modal-box {
  background: var(--bg-primary, #12121e);
  border: 1px solid var(--border, #333);
  border-radius: .85rem;
  width: 100%;
  max-width: 640px;
  max-height: 85vh;
  overflow-y: auto;
  padding: 1.75rem;
  position: relative;
}
.cp-modal-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--text-primary, #fff);
  margin: 0 0 .25rem;
}
.cp-modal-meta {
  font-size: .82rem;
  color: var(--text-muted, #888);
  margin-bottom: 1.25rem;
}
.cp-modal-close {
  position: absolute;
  top: 1rem;
  right: 1rem;
  background: transparent;
  border: none;
  color: var(--text-muted, #888);
  cursor: pointer;
  padding: .25rem;
  border-radius: .35rem;
}
.cp-modal-close:hover { color: var(--text-primary, #fff); }

/* Timer */
.cp-quiz-timer {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  font-size: .85rem;
  font-weight: 600;
  color: var(--text-secondary, #ccc);
  background: var(--bg-secondary, #1e1e2e);
  padding: .3rem .7rem;
  border-radius: .4rem;
  margin-bottom: 1rem;
}
.cp-quiz-timer.danger { color: #f87171; }

/* Questions */
.cp-quiz-question {
  margin-bottom: 1.25rem;
}
.cp-quiz-question-text {
  font-size: .9rem;
  font-weight: 600;
  color: var(--text-primary, #fff);
  margin-bottom: .6rem;
}
.cp-quiz-option {
  display: flex;
  align-items: center;
  gap: .55rem;
  padding: .5rem .75rem;
  border-radius: .45rem;
  border: 1px solid var(--border, #333);
  margin-bottom: .35rem;
  cursor: pointer;
  font-size: .875rem;
  color: var(--text-secondary, #ccc);
  transition: background .12s, border-color .12s;
}
.cp-quiz-option:hover { background: var(--bg-hover, #2a2a3a); }
.cp-quiz-option.selected {
  border-color: #6366f1;
  background: rgba(99,102,241,.12);
  color: var(--text-primary, #fff);
}
.cp-quiz-option.correct-ans {
  border-color: #10b981;
  background: rgba(16,185,129,.12);
  color: #10b981;
}
.cp-quiz-option.wrong-ans {
  border-color: #f87171;
  background: rgba(248,113,113,.1);
  color: #f87171;
}
.cp-quiz-option input[type="radio"] { accent-color: #6366f1; }

.cp-quiz-submit-btn {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .6rem 1.25rem;
  background: #6366f1;
  color: #fff;
  border: none;
  border-radius: .5rem;
  font-size: .875rem;
  font-weight: 600;
  cursor: pointer;
  margin-top: .75rem;
  font-family: inherit;
  transition: background .15s;
}
.cp-quiz-submit-btn:hover { background: #4f46e5; }
.cp-quiz-submit-btn:disabled { opacity: .5; cursor: not-allowed; }

/* Results */
.cp-quiz-result {
  text-align: center;
  padding: .5rem 0 1rem;
}
.cp-quiz-result-score {
  font-size: 2.5rem;
  font-weight: 800;
  color: var(--text-primary, #fff);
}
.cp-quiz-result-label {
  font-size: .9rem;
  color: var(--text-muted, #888);
  margin-bottom: .75rem;
}
.cp-quiz-result-pass { color: #10b981; }
.cp-quiz-result-fail { color: #f87171; }
.cp-quiz-try-again {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .5rem 1rem;
  background: #6366f1;
  color: #fff;
  border: none;
  border-radius: .5rem;
  font-size: .875rem;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
}

/* Confetti */
.cp-confetti-wrap {
  position: fixed;
  inset: 0;
  pointer-events: none;
  z-index: 1100;
  overflow: hidden;
}
.cp-confetti-piece {
  position: absolute;
  top: -10px;
  width: 10px;
  height: 10px;
  border-radius: 2px;
  animation: confettiFall linear forwards;
}
@keyframes confettiFall {
  0%   { transform: translateY(0) rotate(0deg); opacity: 1; }
  100% { transform: translateY(110vh) rotate(720deg); opacity: 0; }
}

/* ─── Q&A tab ────────────────────────────────────────────────────────────── */
.cp-qa-ask-form { margin-bottom: 1.5rem; }
.cp-qa-ask-label {
  display: block;
  font-size: .82rem;
  font-weight: 600;
  color: var(--text-secondary, #ccc);
  margin-bottom: .4rem;
}
.cp-qa-textarea {
  width: 100%;
  min-height: 80px;
  padding: .6rem .75rem;
  border-radius: .5rem;
  border: 1px solid var(--border, #333);
  background: var(--bg-secondary, #1e1e2e);
  color: var(--text-primary, #fff);
  font-family: inherit;
  font-size: .875rem;
  resize: vertical;
  box-sizing: border-box;
}
.cp-qa-textarea:focus { outline: none; border-color: #6366f1; }
.cp-qa-submit-btn {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  padding: .45rem .9rem;
  background: #6366f1;
  color: #fff;
  border: none;
  border-radius: .45rem;
  font-size: .82rem;
  font-weight: 600;
  cursor: pointer;
  margin-top: .5rem;
  font-family: inherit;
}
.cp-qa-submit-btn:hover { background: #4f46e5; }
.cp-qa-submit-btn:disabled { opacity: .5; cursor: not-allowed; }

.cp-qa-comment {
  display: flex;
  gap: .75rem;
  margin-bottom: 1.1rem;
}
.cp-qa-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #6366f1;
  color: #fff;
  font-size: .78rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  text-transform: uppercase;
}
.cp-qa-comment-body { flex: 1; min-width: 0; }
.cp-qa-comment-meta {
  font-size: .78rem;
  color: var(--text-muted, #888);
  margin-bottom: .25rem;
}
.cp-qa-comment-name {
  font-weight: 600;
  color: var(--text-secondary, #ccc);
  margin-right: .4rem;
}
.cp-qa-comment-text {
  font-size: .875rem;
  color: var(--text-secondary, #ccc);
  line-height: 1.55;
  word-break: break-word;
}
.cp-qa-reply-btn {
  background: transparent;
  border: none;
  color: #6366f1;
  font-size: .78rem;
  font-weight: 600;
  cursor: pointer;
  padding: .2rem 0;
  margin-top: .35rem;
  font-family: inherit;
}
.cp-qa-reply-btn:hover { text-decoration: underline; }
.cp-qa-replies { margin-top: .6rem; padding-left: 1rem; border-left: 2px solid var(--border, #333); }
.cp-qa-inline-reply { margin-top: .6rem; }
.cp-qa-empty {
  color: var(--text-muted, #888);
  font-size: .875rem;
  text-align: center;
  padding: 2rem 0;
}
/* Skeleton loader */
.cp-skeleton {
  background: linear-gradient(90deg, var(--bg-secondary,#1e1e2e) 25%, var(--bg-hover,#2a2a3a) 50%, var(--bg-secondary,#1e1e2e) 75%);
  background-size: 200% 100%;
  animation: skeletonWave 1.4s infinite;
  border-radius: .35rem;
}
@keyframes skeletonWave {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
.cp-skeleton-row { height: 14px; margin-bottom: .5rem; }

/* ─── Bookmarks section ──────────────────────────────────────────────────── */
.cp-bookmarks-section { margin-top: 1.75rem; border-top: 1px solid var(--border, #333); padding-top: 1.25rem; }
.cp-bookmarks-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: .85rem;
}
.cp-bookmarks-title {
  font-size: .9rem;
  font-weight: 700;
  color: var(--text-primary, #fff);
}
.cp-add-bookmark-btn {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .3rem .7rem;
  background: var(--bg-secondary, #1e1e2e);
  border: 1px solid var(--border, #333);
  border-radius: .4rem;
  color: var(--text-secondary, #ccc);
  font-size: .78rem;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
}
.cp-add-bookmark-btn:hover { background: var(--bg-hover, #2a2a3a); }

.cp-bookmark-form {
  background: var(--bg-secondary, #1e1e2e);
  border: 1px solid var(--border, #333);
  border-radius: .5rem;
  padding: .85rem;
  margin-bottom: .9rem;
}
.cp-bookmark-form.hidden { display: none; }
.cp-bm-note-input {
  width: 100%;
  padding: .5rem .7rem;
  border-radius: .4rem;
  border: 1px solid var(--border, #333);
  background: var(--bg-primary, #12121e);
  color: var(--text-primary, #fff);
  font-family: inherit;
  font-size: .85rem;
  box-sizing: border-box;
  margin-bottom: .5rem;
}
.cp-bm-note-input:focus { outline: none; border-color: #6366f1; }
.cp-bm-time-row {
  display: flex;
  align-items: center;
  gap: .5rem;
  margin-bottom: .5rem;
  flex-wrap: wrap;
}
.cp-bm-time-input {
  width: 90px;
  padding: .35rem .55rem;
  border-radius: .4rem;
  border: 1px solid var(--border, #333);
  background: var(--bg-primary, #12121e);
  color: var(--text-primary, #fff);
  font-family: inherit;
  font-size: .82rem;
}
.cp-bm-time-input:focus { outline: none; border-color: #6366f1; }
.cp-bm-capture-btn {
  padding: .3rem .65rem;
  border-radius: .35rem;
  border: 1px solid var(--border, #333);
  background: transparent;
  color: var(--text-secondary, #ccc);
  font-size: .78rem;
  cursor: pointer;
  font-family: inherit;
}
.cp-bm-capture-btn:hover { background: var(--bg-hover, #2a2a3a); }
.cp-bm-save-btn {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .35rem .8rem;
  background: #6366f1;
  color: #fff;
  border: none;
  border-radius: .4rem;
  font-size: .82rem;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
}
.cp-bm-save-btn:hover { background: #4f46e5; }

/* Bookmark list */
.cp-bookmark-item {
  display: flex;
  align-items: flex-start;
  gap: .6rem;
  padding: .55rem .6rem;
  border-radius: .45rem;
  border: 1px solid var(--border, #333);
  margin-bottom: .5rem;
  background: var(--bg-secondary, #1e1e2e);
}
.cp-bm-time-pill {
  padding: .18rem .5rem;
  background: rgba(99,102,241,.2);
  color: #6366f1;
  border-radius: .3rem;
  font-size: .75rem;
  font-weight: 700;
  white-space: nowrap;
  flex-shrink: 0;
  cursor: pointer;
  border: none;
  font-family: inherit;
}
.cp-bm-time-pill:not([data-seconds]) { cursor: default; }
.cp-bm-note-text {
  flex: 1;
  font-size: .85rem;
  color: var(--text-secondary, #ccc);
  word-break: break-word;
}
.cp-bm-delete-btn {
  background: transparent;
  border: none;
  color: var(--text-muted, #888);
  cursor: pointer;
  padding: .1rem .25rem;
  border-radius: .25rem;
  flex-shrink: 0;
}
.cp-bm-delete-btn:hover { color: #f87171; }
  </style>
</head>
<body class="cp-body">

<!-- ─── Header ──────────────────────────────────────────────────────────── -->
<header class="cp-header">

  <!-- Left: back + logo -->
  <a href="<?= BASE ?>/my-courses.php" class="cp-header-logo" title="Back to My Courses">
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path d="M19 12H5M12 19l-7-7 7-7"/>
    </svg>
    <div class="cp-logo-icon">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
        <path d="M2 17l10 5 10-5"/>
        <path d="M2 12l10 5 10-5"/>
      </svg>
    </div>
    <span class="cp-logo-text">Nerd<span class="cp-logo-accent">Academy</span></span>
  </a>

  <div class="cp-header-divider"></div>

  <!-- Center: course + lesson title -->
  <div class="cp-header-title">
    <div class="cp-header-course-name"><?= htmlspecialchars((string)($course['title'] ?? ''), ENT_QUOTES) ?></div>
    <div class="cp-header-lesson-name"><?= htmlspecialchars((string)($currentLesson['title'] ?? ''), ENT_QUOTES) ?></div>
  </div>

  <!-- Right: progress + mobile menu -->
  <div class="cp-header-right">
    <div class="cp-progress-wrap">
      <div class="cp-progress-bar-track">
        <div class="cp-progress-bar-fill" id="cpProgressFill" style="width:<?= $progressPercent ?>%"></div>
      </div>
      <span class="cp-progress-pct" id="cpProgressPct"><?= $progressPercent ?>%</span>
    </div>
    <button class="cp-menu-btn" id="sidebarToggle" aria-label="Toggle course contents" aria-expanded="false">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M3 12h18M3 6h18M3 18h18"/>
      </svg>
    </button>
  </div>

</header>

<!-- ─── Overlay (mobile) ─────────────────────────────────────────────────── -->
<div class="cp-overlay" id="sidebarOverlay"></div>

<!-- ─── Body wrap ────────────────────────────────────────────────────────── -->
<div class="cp-body-wrap">

  <!-- ─── Sidebar ─────────────────────────────────────────────────────── -->
  <aside class="cp-sidebar" id="sidebar" aria-label="Course contents">

    <div class="cp-sidebar-header">
      <div class="cp-sidebar-course-title"><?= htmlspecialchars((string)($course['title'] ?? ''), ENT_QUOTES) ?></div>
      <div class="cp-sidebar-sub">
        <?= $completedCount ?> / <?= $totalLessons ?> lessons completed
      </div>
    </div>

    <?php if (empty($modules)): ?>
      <div style="padding:1.5rem 1.1rem;color:var(--text-muted);font-size:.85rem;text-align:center">
        No lessons available yet.
      </div>
    <?php else: ?>
      <?php foreach ($modules as $module):
        $moduleId       = (int)($module['id'] ?? 0);
        $moduleLessons  = (array)($module['lessons'] ?? []);
        $lessonCount    = count($moduleLessons);
        $isOpenModule   = ($moduleId === $currentModuleId);
      ?>
      <div class="cp-module<?= $isOpenModule ? ' open' : '' ?>" id="module-<?= $moduleId ?>">

        <button class="cp-module-header" type="button"
                aria-expanded="<?= $isOpenModule ? 'true' : 'false' ?>"
                onclick="toggleModule(<?= $moduleId ?>)">
          <svg class="cp-module-arrow" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path d="M9 18l6-6-6-6"/>
          </svg>
          <div class="cp-module-info">
            <div class="cp-module-title"><?= htmlspecialchars((string)($module['title'] ?? ''), ENT_QUOTES) ?></div>
            <div class="cp-module-meta"><?= $lessonCount ?> lesson<?= $lessonCount === 1 ? '' : 's' ?></div>
          </div>
          <span class="cp-lesson-count-pill"><?= $lessonCount ?></span>
        </button>

        <div class="cp-module-body" id="module-body-<?= $moduleId ?>">
          <?php foreach ($moduleLessons as $lesson):
            $lid         = (int)($lesson['id'] ?? 0);
            $isActive    = ($lid === $lessonId);
            $isCompleted = !empty($progressMap[$lid]['completed']);
            $isPreview   = !empty($lesson['is_preview']);
            $isLocked    = !$isEnrolled && !$isPreview;
            $duration    = format_duration((int)($lesson['duration_seconds'] ?? 0));

            // Build item classes
            $itemClass = 'cp-lesson-item';
            if ($isActive)    $itemClass .= ' active';
            if ($isCompleted) $itemClass .= ' completed';

            $href = $isLocked
                  ? BASE . '/course.php?id=' . $courseId . '&msg=enroll_required'
                  : BASE . '/course-player.php?course=' . $courseId . '&lesson=' . $lid;
          ?>
          <a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>"
             class="<?= $itemClass ?>"
             <?= $isActive ? 'aria-current="page"' : '' ?>
             title="<?= htmlspecialchars((string)($lesson['title'] ?? ''), ENT_QUOTES) ?>">

            <!-- Icon: lock / play / check -->
            <div class="cp-lesson-icon">
              <?php if ($isLocked): ?>
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <rect x="3" y="11" width="18" height="11" rx="2"/>
                  <path d="M7 11V7a5 5 0 0110 0v4"/>
                </svg>
              <?php elseif ($isCompleted): ?>
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path d="M20 6L9 17l-5-5"/>
                </svg>
              <?php else: ?>
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <polygon points="5 3 19 12 5 21 5 3" fill="currentColor" stroke="none"/>
                </svg>
              <?php endif; ?>
            </div>

            <div class="cp-lesson-info">
              <div class="cp-lesson-title"><?= htmlspecialchars((string)($lesson['title'] ?? ''), ENT_QUOTES) ?></div>
              <?php if ($duration !== ''): ?>
                <div class="cp-lesson-duration"><?= htmlspecialchars($duration, ENT_QUOTES) ?></div>
              <?php endif; ?>
            </div>

            <div class="cp-lesson-badges">
              <?php if ($isPreview && !$isActive): ?>
                <span class="cp-badge-preview">Preview</span>
              <?php endif; ?>
              <?php if ($isLocked): ?>
                <span class="cp-badge-lock">
                  <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect x="3" y="11" width="18" height="11" rx="2"/>
                    <path d="M7 11V7a5 5 0 0110 0v4"/>
                  </svg>
                </span>
              <?php elseif ($isCompleted): ?>
                <span class="cp-badge-check">
                  <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path d="M20 6L9 17l-5-5"/>
                  </svg>
                </span>
              <?php endif; ?>
            </div>

          </a>
          <?php endforeach; ?>
        </div><!-- .cp-module-body -->

      </div><!-- .cp-module -->
      <?php endforeach; ?>
    <?php endif; ?>

  </aside><!-- .cp-sidebar -->

  <!-- ─── Main content ──────────────────────────────────────────────────── -->
  <main class="cp-main" id="cpMain">

    <!-- Video -->
    <div class="cp-video-container">
      <div class="cp-video-wrap">
        <?php if ($videoEmbed !== ''): ?>
          <?= $videoEmbed ?>
        <?php else: ?>
          <div class="cp-no-video">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <rect x="2" y="3" width="20" height="14" rx="2"/>
              <path d="M8 21h8M12 17v4"/>
            </svg>
            <span>No video available for this lesson.</span>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($isMP4): ?>
      <!-- Playback speed + subtitle controls (MP4 only) -->
      <div class="cp-speed-row" id="cp-speed-row">
        <span class="cp-speed-label">Speed:</span>
        <button class="cp-speed-btn" data-speed="0.75" onclick="setPlaybackSpeed(0.75, this)">0.75x</button>
        <button class="cp-speed-btn active" data-speed="1" onclick="setPlaybackSpeed(1, this)" id="speed-btn-1">1x</button>
        <button class="cp-speed-btn" data-speed="1.25" onclick="setPlaybackSpeed(1.25, this)">1.25x</button>
        <button class="cp-speed-btn" data-speed="1.5" onclick="setPlaybackSpeed(1.5, this)">1.5x</button>
        <button class="cp-speed-btn" data-speed="2" onclick="setPlaybackSpeed(2, this)">2x</button>
        <?php if ($hasSubtitles): ?>
        <button class="cp-subtitle-btn" id="subtitleToggleBtn" onclick="toggleSubtitles(this)">CC Off</button>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Content body -->
    <div class="cp-content-body">

      <?php if ($justCompleted): ?>
        <div class="cp-complete-banner" role="status">
          <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path d="M20 6L9 17l-5-5"/>
          </svg>
          Lesson marked as complete!
        </div>
      <?php endif; ?>

      <?php if ($resumedFromSaved && $resumeTimeLabel !== ''): ?>
        <div class="cp-complete-banner" role="status" style="background:rgba(59,130,246,.12);color:#bfdbfe;border:1px solid rgba(59,130,246,.28)">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M12 6v6l4 2"/>
            <circle cx="12" cy="12" r="10"/>
          </svg>
          Resumed from your last saved point at <?= htmlspecialchars($resumeTimeLabel, ENT_QUOTES) ?>.
        </div>
      <?php endif; ?>

      <?php if ($isEnrolled && $courseCompleted): ?>
        <div class="cp-complete-banner" role="status" style="justify-content:space-between;gap:.75rem;flex-wrap:wrap">
          <span style="display:inline-flex;align-items:center;gap:.5rem">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path d="M20 6L9 17l-5-5"/>
            </svg>
            Course completed — your certificate is ready.
          </span>
          <a href="<?= BASE ?>/certificate.php?course_id=<?= $courseId ?>" class="cp-quiz-btn" style="text-decoration:none">Open Certificate</a>
        </div>
      <?php endif; ?>

      <!-- Lesson heading + meta -->
      <div class="cp-lesson-header">
        <div class="cp-lesson-header-left">
          <h1 class="cp-lesson-heading"><?= htmlspecialchars((string)($currentLesson['title'] ?? ''), ENT_QUOTES) ?></h1>
          <div class="cp-lesson-meta-row">
            <?php if ((int)($currentLesson['duration_seconds'] ?? 0) > 0): ?>
            <span class="cp-lesson-meta-item">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 6v6l4 2"/>
              </svg>
              <?= htmlspecialchars(format_duration((int)$currentLesson['duration_seconds']), ENT_QUOTES) ?>
            </span>
            <?php endif; ?>
            <?php if (!empty($currentLesson['is_preview'])): ?>
            <span class="cp-lesson-meta-item">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              Free Preview
            </span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Actions: mark complete + prev/next -->
      <div class="cp-actions-row">
        <div>
          <?php if ($isEnrolled): ?>
            <div id="cpAutoSaveStatus" style="margin-bottom:.6rem;font-size:.84rem;color:var(--text-muted)">
              <?php if ($resumeTimeLabel !== '' && !$isCurrentCompleted): ?>
                Resume point saved at <?= htmlspecialchars($resumeTimeLabel, ENT_QUOTES) ?>.
              <?php else: ?>
                Your lesson progress saves automatically.
              <?php endif; ?>
            </div>
            <a href="<?= BASE ?>/certificate.php?course_id=<?= $courseId ?>" id="cpCertificateLink" class="cp-quiz-passed-badge" style="margin-bottom:.6rem;<?= $courseCompleted ? '' : 'display:none;' ?>">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M20 6L9 17l-5-5"/>
              </svg>
              Certificate Ready
            </a>
            <br>
            <?php if ($isCurrentCompleted): ?>
              <span class="cp-completed-badge">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path d="M20 6L9 17l-5-5"/>
                </svg>
                Completed
              </span>
            <?php else: ?>
              <form method="post" action="<?= BASE ?>/course-player.php?course=<?= $courseId ?>&amp;lesson=<?= $lessonId ?>">
                <input type="hidden" name="action" value="mark_complete">
                <input type="hidden" name="lesson_id" value="<?= $lessonId ?>">
                <button type="submit" class="cp-mark-complete-btn">
                  <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path d="M20 6L9 17l-5-5"/>
                  </svg>
                  Mark as Complete
                </button>
              </form>
            <?php endif; ?>

            <?php if ($lessonQuiz): ?>
              <?php if ($quizAlreadyPassed): ?>
                <span class="cp-quiz-passed-badge">
                  <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path d="M20 6L9 17l-5-5"/>
                  </svg>
                  Quiz Passed (<?= (int)$quizBestAttempt['score'] ?>%)
                </span>
              <?php else: ?>
                <button class="cp-quiz-btn" onclick="openQuizModal()" type="button">
                  <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                  </svg>
                  Take Quiz
                </button>
              <?php endif; ?>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <div class="cp-nav-btns">
          <?php if ($prevLesson): ?>
            <a href="<?= BASE ?>/course-player.php?course=<?= $courseId ?>&amp;lesson=<?= (int)$prevLesson['id'] ?>"
               class="cp-nav-btn">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
              </svg>
              Previous
            </a>
          <?php else: ?>
            <span class="cp-nav-btn disabled">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
              </svg>
              Previous
            </span>
          <?php endif; ?>

          <?php if ($nextLesson): ?>
            <a href="<?= BASE ?>/course-player.php?course=<?= $courseId ?>&amp;lesson=<?= (int)$nextLesson['id'] ?>"
               class="cp-nav-btn">
              Next
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M5 12h14M12 5l7 7-7 7"/>
              </svg>
            </a>
          <?php else: ?>
            <span class="cp-nav-btn disabled">
              Next
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M5 12h14M12 5l7 7-7 7"/>
              </svg>
            </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Tab bar -->
      <div class="cp-tabs" role="tablist">
        <button class="cp-tab-btn<?= $activeTab === 'transcript' ? ' active' : '' ?>"
                role="tab"
                aria-selected="<?= $activeTab === 'transcript' ? 'true' : 'false' ?>"
                aria-controls="tab-transcript"
                onclick="switchTab('transcript', this)">
          Transcript
        </button>
        <button class="cp-tab-btn<?= $activeTab === 'notes' ? ' active' : '' ?>"
                role="tab"
                aria-selected="<?= $activeTab === 'notes' ? 'true' : 'false' ?>"
                aria-controls="tab-notes"
                onclick="switchTab('notes', this)">
          Notes
        </button>
        <button class="cp-tab-btn<?= $activeTab === 'overview' ? ' active' : '' ?>"
                role="tab"
                aria-selected="<?= $activeTab === 'overview' ? 'true' : 'false' ?>"
                aria-controls="tab-overview"
                onclick="switchTab('overview', this)">
          Overview
        </button>
        <button class="cp-tab-btn<?= $activeTab === 'qa' ? ' active' : '' ?>"
                role="tab"
                aria-selected="<?= $activeTab === 'qa' ? 'true' : 'false' ?>"
                aria-controls="tab-qa"
                onclick="switchTab('qa', this); loadQA();">
          Q&amp;A
        </button>
      </div>

      <!-- Transcript panel -->
      <div class="cp-tab-panel<?= $activeTab === 'transcript' ? ' active' : '' ?>"
           id="tab-transcript" role="tabpanel">
        <?php if (trim($transcript) !== ''): ?>
          <div class="cp-transcript">
            <?php
            // Convert double newlines to paragraphs, single newlines to <br>
            $paragraphs = preg_split('/\n{2,}/', trim($transcript));
            foreach ($paragraphs as $para) {
                $para = trim($para);
                if ($para !== '') {
                    echo '<p>' . nl2br(htmlspecialchars($para, ENT_QUOTES)) . '</p>';
                }
            }
            ?>
          </div>
        <?php else: ?>
          <div class="cp-transcript-empty">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="16" y1="13" x2="8" y2="13"/>
              <line x1="16" y1="17" x2="8" y2="17"/>
              <polyline points="10 9 9 9 8 9"/>
            </svg>
            <p>No transcript is available for this lesson.</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Notes panel -->
      <div class="cp-tab-panel<?= $activeTab === 'notes' ? ' active' : '' ?>"
           id="tab-notes" role="tabpanel">
        <form method="post"
              action="<?= BASE ?>/course-player.php?course=<?= $courseId ?>&amp;lesson=<?= $lessonId ?>&amp;tab=notes"
              class="cp-notes-form"
              id="notesForm">
          <input type="hidden" name="action" value="save_note">
          <input type="hidden" name="lesson_id" value="<?= $lessonId ?>">
          <label class="cp-notes-label" for="noteTextarea">Your Notes</label>
          <textarea id="noteTextarea"
                    name="note"
                    class="cp-notes-area"
                    placeholder="Jot down your thoughts, key takeaways, or questions about this lesson…"><?= htmlspecialchars($savedNote, ENT_QUOTES) ?></textarea>
          <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
            <button type="submit" class="cp-notes-save-btn">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/>
                <polyline points="17 21 17 13 7 13 7 21"/>
                <polyline points="7 3 7 8 15 8"/>
              </svg>
              Save Notes
            </button>
            <span class="cp-notes-saved-msg<?= $noteSaved ? ' visible' : '' ?>" id="notesSavedMsg">
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M20 6L9 17l-5-5"/>
              </svg>
              Saved!
            </span>
          </div>
        </form>

        <!-- ─── Bookmarks section ──────────────────────────────────────── -->
        <div class="cp-bookmarks-section">
          <div class="cp-bookmarks-header">
            <span class="cp-bookmarks-title">Bookmarks</span>
            <button class="cp-add-bookmark-btn" onclick="toggleBookmarkForm()" type="button">
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
              Add Bookmark
            </button>
          </div>

          <!-- Add bookmark form -->
          <div class="cp-bookmark-form hidden" id="bookmarkForm">
            <input type="text"
                   class="cp-bm-note-input"
                   id="bmNoteInput"
                   placeholder="Bookmark note (required)…">
            <?php if ($isMP4): ?>
            <div class="cp-bm-time-row">
              <label style="font-size:.78rem;color:var(--text-muted,#888);">Time (mm:ss):</label>
              <input type="text"
                     class="cp-bm-time-input"
                     id="bmTimeInput"
                     placeholder="0:00"
                     pattern="\d+:\d{2}">
              <button class="cp-bm-capture-btn" type="button" onclick="captureVideoTime()">Capture current time</button>
            </div>
            <?php endif; ?>
            <button class="cp-bm-save-btn" type="button" onclick="saveBookmark()">
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/>
                <polyline points="17 21 17 13 7 13 7 21"/>
              </svg>
              Save Bookmark
            </button>
          </div>

          <!-- Bookmark list -->
          <div id="bookmarkList"></div>
        </div>
      </div>

      <!-- Overview panel -->
      <div class="cp-tab-panel<?= $activeTab === 'overview' ? ' active' : '' ?>"
           id="tab-overview" role="tabpanel">
        <div class="cp-overview">
          <div class="cp-overview-meta">
            <?php if (!empty($course['level'])): ?>
            <div class="cp-overview-meta-item">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M18 20V10M12 20V4M6 20v-6"/>
              </svg>
              <?= htmlspecialchars((string)$course['level'], ENT_QUOTES) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($course['duration'])): ?>
            <div class="cp-overview-meta-item">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 6v6l4 2"/>
              </svg>
              <?= htmlspecialchars((string)$course['duration'], ENT_QUOTES) ?>
            </div>
            <?php endif; ?>
            <?php if ($totalLessons > 0): ?>
            <div class="cp-overview-meta-item">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <polygon points="23 7 16 12 23 17 23 7"/>
                <rect x="1" y="5" width="15" height="14" rx="2"/>
              </svg>
              <?= $totalLessons ?> lesson<?= $totalLessons === 1 ? '' : 's' ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($course['instructor'])): ?>
            <div class="cp-overview-meta-item">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
              </svg>
              <?= htmlspecialchars((string)$course['instructor'], ENT_QUOTES) ?>
            </div>
            <?php endif; ?>
          </div>

          <?php if (!empty($course['description'])): ?>
            <h3>About This Course</h3>
            <?php
            $descParagraphs = preg_split('/\n{2,}/', trim((string)$course['description']));
            foreach ($descParagraphs as $dp) {
                $dp = trim($dp);
                if ($dp !== '') {
                    echo '<p>' . nl2br(htmlspecialchars($dp, ENT_QUOTES)) . '</p>';
                }
            }
            ?>
          <?php endif; ?>

          <?php if (!empty($course['outcomes']) && is_array($course['outcomes'])): ?>
            <h3>What You'll Learn</h3>
            <ul style="padding-left:0;list-style:none;display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:.55rem .75rem;margin-bottom:1rem">
              <?php foreach ($course['outcomes'] as $outcome): ?>
                <li style="display:flex;align-items:flex-start;gap:.5rem;font-size:.88rem;color:var(--text-secondary)">
                  <svg width="16" height="16" style="flex-shrink:0;margin-top:2px;color:var(--accent-green)" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path d="M20 6L9 17l-5-5"/>
                  </svg>
                  <?= htmlspecialchars((string)$outcome, ENT_QUOTES) ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

        </div>
      </div>

      <!-- Q&A panel -->
      <div class="cp-tab-panel<?= $activeTab === 'qa' ? ' active' : '' ?>"
           id="tab-qa" role="tabpanel">
        <?php if ($isEnrolled): ?>
        <div class="cp-qa-ask-form" id="qaAskForm">
          <label class="cp-qa-ask-label" for="qaTextarea">Ask a question</label>
          <textarea id="qaTextarea" class="cp-qa-textarea" placeholder="Type your question about this lesson…"></textarea>
          <button class="cp-qa-submit-btn" id="qaSubmitBtn" onclick="submitTopLevelComment()" type="button">Submit Question</button>
        </div>
        <?php else: ?>
        <div class="cp-qa-empty" style="padding:1.5rem 0">
          <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin-bottom:.5rem;color:var(--text-muted,#888)">
            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
          </svg>
          <p>Enroll to participate in discussions.</p>
        </div>
        <?php endif; ?>
        <div id="qaList"></div>
      </div>

    </div><!-- .cp-content-body -->

  </main><!-- .cp-main -->

</div><!-- .cp-body-wrap -->

<?php if ($lessonQuiz && $isEnrolled && !$quizAlreadyPassed): ?>
<!-- ─── Quiz modal ─────────────────────────────────────────────────────────── -->
<div class="cp-modal-overlay hidden" id="quizModal" role="dialog" aria-modal="true" aria-labelledby="quizModalTitle">
  <div class="cp-modal-box">
    <button class="cp-modal-close" onclick="closeQuizModal()" aria-label="Close quiz">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
      </svg>
    </button>

    <!-- Quiz intro view -->
    <div id="quizIntroView">
      <div class="cp-modal-title" id="quizModalTitle"><?= htmlspecialchars((string)($lessonQuiz['title'] ?? 'Quiz'), ENT_QUOTES) ?></div>
      <div class="cp-modal-meta">
        Pass score: <?= (int)$lessonQuiz['pass_score'] ?>%
        <?php if ((int)$lessonQuiz['time_limit_seconds'] > 0): ?>
        &nbsp;·&nbsp; Time limit: <?= format_duration((int)$lessonQuiz['time_limit_seconds']) ?>
        <?php endif; ?>
        &nbsp;·&nbsp; <?= count($lessonQuiz['questions']) ?> question<?= count($lessonQuiz['questions']) !== 1 ? 's' : '' ?>
      </div>
      <button class="cp-quiz-submit-btn" onclick="startQuiz()" type="button">Start Quiz</button>
    </div>

    <!-- Quiz questions view (hidden initially) -->
    <div id="quizQuestionsView" style="display:none">
      <?php if ((int)$lessonQuiz['time_limit_seconds'] > 0): ?>
      <div class="cp-quiz-timer" id="quizTimer">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
        </svg>
        <span id="quizTimerDisplay">--:--</span>
      </div>
      <?php endif; ?>

      <form id="quizForm">
        <?php foreach ($lessonQuiz['questions'] as $qi => $question): ?>
        <div class="cp-quiz-question" data-question-id="<?= (int)$question['id'] ?>">
          <div class="cp-quiz-question-text">
            <?= ($qi + 1) ?>. <?= htmlspecialchars((string)$question['question_text'], ENT_QUOTES) ?>
          </div>
          <?php foreach ($question['options'] as $option): ?>
          <label class="cp-quiz-option" data-option-id="<?= (int)$option['id'] ?>">
            <input type="radio"
                   name="q_<?= (int)$question['id'] ?>"
                   value="<?= (int)$option['id'] ?>"
                   data-correct="<?= $option['is_correct'] ? '1' : '0' ?>">
            <?= htmlspecialchars((string)$option['option_text'], ENT_QUOTES) ?>
          </label>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <button type="button" class="cp-quiz-submit-btn" id="quizSubmitBtn" onclick="submitQuiz()">
          Submit Quiz
        </button>
      </form>
    </div>

    <!-- Quiz results view (hidden initially) -->
    <div id="quizResultsView" style="display:none">
      <div class="cp-quiz-result">
        <div class="cp-quiz-result-score" id="quizResultScore">0%</div>
        <div class="cp-quiz-result-label" id="quizResultLabel"></div>
        <div id="quizResultStatus" style="font-size:1.1rem;font-weight:700;margin-bottom:1rem"></div>
        <button type="button" class="cp-quiz-try-again" id="quizTryAgainBtn" onclick="resetQuiz()" style="display:none">
          Try Again
        </button>
      </div>
      <div id="quizAnswerReview"></div>
    </div>
  </div>
</div>

<!-- Confetti container (pure CSS, no library) -->
<div class="cp-confetti-wrap hidden" id="confettiWrap"></div>
<?php endif; ?>

<script>
// ─── Theme ───────────────────────────────────────────────────────────────────
(function () {
  var t = localStorage.getItem('na_theme');
  if (t === 'light' || t === 'dark') {
    document.documentElement.setAttribute('data-theme', t);
  }
})();

// ─── Sidebar toggle (mobile) ─────────────────────────────────────────────────
var sidebar       = document.getElementById('sidebar');
var overlay       = document.getElementById('sidebarOverlay');
var sidebarToggle = document.getElementById('sidebarToggle');

function openSidebar() {
  sidebar.classList.add('open');
  overlay.classList.add('visible');
  if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'true');
  document.body.style.overflow = 'hidden';
}

function closeSidebar() {
  sidebar.classList.remove('open');
  overlay.classList.remove('visible');
  if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'false');
  document.body.style.overflow = '';
}

if (sidebarToggle) {
  sidebarToggle.addEventListener('click', function () {
    if (sidebar.classList.contains('open')) {
      closeSidebar();
    } else {
      openSidebar();
    }
  });
}

if (overlay) {
  overlay.addEventListener('click', closeSidebar);
}

// Close sidebar on Escape
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape' && sidebar.classList.contains('open')) {
    closeSidebar();
  }
});

// ─── Module accordion ────────────────────────────────────────────────────────
function toggleModule(moduleId) {
  var el   = document.getElementById('module-' + moduleId);
  var body = document.getElementById('module-body-' + moduleId);
  var btn  = el ? el.querySelector('.cp-module-header') : null;
  if (!el || !body) return;

  var isOpen = el.classList.contains('open');
  el.classList.toggle('open', !isOpen);
  if (btn) btn.setAttribute('aria-expanded', String(!isOpen));
}

// ─── Tabs ─────────────────────────────────────────────────────────────────────
function switchTab(name, btnEl) {
  // Hide all panels and deactivate all buttons
  var panels = document.querySelectorAll('.cp-tab-panel');
  var btns   = document.querySelectorAll('.cp-tab-btn');

  panels.forEach(function (p) { p.classList.remove('active'); });
  btns.forEach(function (b) {
    b.classList.remove('active');
    b.setAttribute('aria-selected', 'false');
  });

  // Activate selected
  var panel = document.getElementById('tab-' + name);
  if (panel) panel.classList.add('active');
  if (btnEl) {
    btnEl.classList.add('active');
    btnEl.setAttribute('aria-selected', 'true');
  }
}

// ─── Auto-scroll sidebar to active lesson ────────────────────────────────────
(function () {
  var activeItem = document.querySelector('.cp-lesson-item.active');
  if (activeItem && sidebar) {
    // Small delay so module is expanded first
    setTimeout(function () {
      var itemTop    = activeItem.offsetTop;
      var sidebarH   = sidebar.clientHeight;
      var scrollTarget = itemTop - sidebarH / 3;
      sidebar.scrollTo({ top: scrollTarget, behavior: 'smooth' });
    }, 120);
  }
})();

// ─── Notes save feedback ──────────────────────────────────────────────────────
<?php if ($noteSaved): ?>
(function () {
  var msg = document.getElementById('notesSavedMsg');
  if (msg) {
    msg.classList.add('visible');
    setTimeout(function () { msg.classList.remove('visible'); }, 3000);
  }
})();
<?php endif; ?>

// ─── Keyboard navigation (left/right arrow) ───────────────────────────────────
document.addEventListener('keydown', function (e) {
  // Only when not typing in a text field
  var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
  if (tag === 'textarea' || tag === 'input' || tag === 'select') return;

  <?php if ($prevLesson): ?>
  if (e.key === 'ArrowLeft') {
    window.location.href = '<?= BASE ?>/course-player.php?course=<?= $courseId ?>&lesson=<?= (int)$prevLesson['id'] ?>';
  }
  <?php endif; ?>
  <?php if ($nextLesson): ?>
  if (e.key === 'ArrowRight') {
    window.location.href = '<?= BASE ?>/course-player.php?course=<?= $courseId ?>&lesson=<?= (int)$nextLesson['id'] ?>';
  }
  <?php endif; ?>
});

// ─── Playback speed (MP4 only) ────────────────────────────────────────────────
<?php if ($isMP4): ?>
(function () {
  var video = document.getElementById('cp-video');
  if (!video) return;

  var saved = parseFloat(localStorage.getItem('na_playback_speed') || '1');
  if ([0.75, 1, 1.25, 1.5, 2].indexOf(saved) === -1) saved = 1;
  video.playbackRate = saved;

  // Highlight the saved speed button
  document.querySelectorAll('.cp-speed-btn').forEach(function (btn) {
    var s = parseFloat(btn.dataset.speed);
    btn.classList.toggle('active', s === saved);
  });
})();

function setPlaybackSpeed(speed, btnEl) {
  var video = document.getElementById('cp-video');
  if (video) video.playbackRate = speed;
  localStorage.setItem('na_playback_speed', String(speed));
  document.querySelectorAll('.cp-speed-btn').forEach(function (b) {
    b.classList.remove('active');
  });
  if (btnEl) btnEl.classList.add('active');
}
<?php endif; ?>

// ─── Subtitle toggle (MP4 + subtitles only) ───────────────────────────────────
<?php if ($hasSubtitles): ?>
function toggleSubtitles(btnEl) {
  var video = document.getElementById('cp-video');
  if (!video) return;
  var track = video.textTracks[0];
  if (!track) return;
  if (track.mode === 'showing') {
    track.mode = 'disabled';
    btnEl.classList.remove('active');
    btnEl.textContent = 'CC Off';
  } else {
    track.mode = 'showing';
    btnEl.classList.add('active');
    btnEl.textContent = 'CC On';
  }
}
<?php endif; ?>

// ─── Auto-save lesson progress + resume playback ─────────────────────────────
(function () {
  var media = document.getElementById('cp-video');
  if (!media) return;

  var lessonId = <?= $lessonId ?>;
  var savedWatchSeconds = <?= $savedWatchSeconds ?>;
  var isCompleted = <?= $isCurrentCompleted ? 'true' : 'false' ?>;
  var lastSentSecond = -1;
  var saveInFlight = false;
  var statusEl = document.getElementById('cpAutoSaveStatus');
  var progressFill = document.getElementById('cpProgressFill');
  var progressPct = document.getElementById('cpProgressPct');
  var certLink = document.getElementById('cpCertificateLink');

  function setStatus(text) {
    if (statusEl) statusEl.textContent = text;
  }

  function updateProgressUI(percent) {
    if (progressFill) progressFill.style.width = percent + '%';
    if (progressPct) progressPct.textContent = percent + '%';
    if (certLink && percent >= 100) certLink.style.display = 'inline-flex';
  }

  function saveProgress(force, completedFlag) {
    var currentSecond = Math.max(0, Math.floor(media.currentTime || 0));
    if (!force && Math.abs(currentSecond - lastSentSecond) < 15) {
      return;
    }
    if (saveInFlight) {
      return;
    }

    lastSentSecond = currentSecond;
    saveInFlight = true;

    fetch('<?= BASE ?>/api/lesson-progress.php?action=save', {
      method: 'POST',
      credentials: 'include',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        lesson_id: lessonId,
        watched_seconds: currentSecond,
        completed: !!completedFlag
      })
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      saveInFlight = false;
      if (!data || !data.ok) return;

      if (typeof data.course_progress_percent === 'number') {
        updateProgressUI(data.course_progress_percent);
      }

      var completedNow = !!(data.progress && data.progress.completed);
      if (completedNow) {
        isCompleted = true;
        setStatus('Lesson completed and saved.');
      } else if (currentSecond > 0) {
        setStatus('Progress saved at ' + formatSeconds(currentSecond) + '.');
      }
    })
    .catch(function () {
      saveInFlight = false;
    });
  }

  function resumePlayback() {
    if (savedWatchSeconds <= 10 || isCompleted) return;

    var applyResume = function () {
      var duration = Number.isFinite(media.duration) ? media.duration : 0;
      var target = Math.max(0, savedWatchSeconds - 2);
      if (target > 0 && (duration === 0 || target < Math.max(5, duration - 5))) {
        media.currentTime = target;
        setStatus('Resumed from ' + formatSeconds(target) + '.');
      }
    };

    if (media.readyState >= 1) {
      applyResume();
    } else {
      media.addEventListener('loadedmetadata', applyResume, { once: true });
    }
  }

  media.addEventListener('timeupdate', function () {
    var currentSecond = Math.max(0, Math.floor(media.currentTime || 0));
    var duration = Number.isFinite(media.duration) ? media.duration : 0;

    if (!isCompleted && duration > 0 && currentSecond >= Math.max(30, Math.floor(duration * 0.9))) {
      isCompleted = true;
      saveProgress(true, true);
      return;
    }

    saveProgress(false, false);
  });

  media.addEventListener('pause', function () {
    saveProgress(true, isCompleted);
  });

  media.addEventListener('ended', function () {
    isCompleted = true;
    saveProgress(true, true);
  });

  window.addEventListener('beforeunload', function () {
    saveProgress(true, isCompleted);
  });

  resumePlayback();
})();

// ─── Q&A ─────────────────────────────────────────────────────────────────────
var _qaLoaded = false;
var LESSON_ID = <?= $lessonId ?>;
var IS_ENROLLED = <?= $isEnrolled ? 'true' : 'false' ?>;

function loadQA() {
  if (_qaLoaded) return;
  _qaLoaded = true;
  var list = document.getElementById('qaList');
  if (!list) return;

  // Show skeleton
  list.innerHTML = [1,2,3].map(function() {
    return '<div style="display:flex;gap:.75rem;margin-bottom:1.1rem">'
      + '<div class="cp-skeleton" style="width:36px;height:36px;border-radius:50%;flex-shrink:0"></div>'
      + '<div style="flex:1">'
      + '<div class="cp-skeleton cp-skeleton-row" style="width:40%"></div>'
      + '<div class="cp-skeleton cp-skeleton-row" style="width:80%"></div>'
      + '<div class="cp-skeleton cp-skeleton-row" style="width:60%"></div>'
      + '</div></div>';
  }).join('');

  fetch('<?= BASE ?>/api/lesson-comments.php?action=list&lesson_id=' + LESSON_ID, {
    credentials: 'include'
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (!data.ok) {
      list.innerHTML = '<div class="cp-qa-empty"><p>' + (data.error === 'enrollment_required'
        ? 'Enroll to participate in discussions.'
        : 'Could not load comments.') + '</p></div>';
      return;
    }
    renderComments(data.comments || [], list);
  })
  .catch(function() {
    list.innerHTML = '<div class="cp-qa-empty"><p>Failed to load comments. Please try again.</p></div>';
  });
}

function getInitials(name) {
  if (!name) return '?';
  var parts = name.trim().split(/\s+/);
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

function formatDate(dt) {
  if (!dt) return '';
  var d = new Date(dt);
  return d.toLocaleDateString(undefined, {year:'numeric',month:'short',day:'numeric'});
}

function renderComments(comments, container) {
  if (!comments || comments.length === 0) {
    container.innerHTML = '<div class="cp-qa-empty"><p>No questions yet. Be the first to ask!</p></div>';
    return;
  }

  // Separate top-level and replies
  var topLevel = [];
  var replies  = {};
  comments.forEach(function(c) {
    if (!c.parent_id) {
      topLevel.push(c);
    } else {
      var pid = String(c.parent_id);
      if (!replies[pid]) replies[pid] = [];
      replies[pid].push(c);
    }
  });

  var html = '';
  topLevel.forEach(function(c) {
    html += renderComment(c, false);
    var cid = String(c.id);
    if (replies[cid]) {
      html += '<div class="cp-qa-replies" id="replies-' + c.id + '">';
      replies[cid].forEach(function(r) {
        html += renderComment(r, true);
      });
      html += '</div>';
    } else {
      html += '<div class="cp-qa-replies" id="replies-' + c.id + '"></div>';
    }
    html += '<div id="reply-form-' + c.id + '"></div>';
  });

  container.innerHTML = html;
}

function renderComment(c, isReply) {
  var name = c.commenter_name || 'User';
  var initials = getInitials(name);
  return '<div class="cp-qa-comment" id="comment-' + c.id + '">'
    + '<div class="cp-qa-avatar">' + initials + '</div>'
    + '<div class="cp-qa-comment-body">'
    + '<div class="cp-qa-comment-meta">'
    + '<span class="cp-qa-comment-name">' + escHtml(name) + '</span>'
    + '<span>' + formatDate(c.created_at) + '</span>'
    + '</div>'
    + '<div class="cp-qa-comment-text">' + escHtml(c.body || '') + '</div>'
    + (IS_ENROLLED && !isReply
      ? '<button class="cp-qa-reply-btn" onclick="toggleReplyForm(' + c.id + ')">Reply</button>'
      : '')
    + '</div>'
    + '</div>';
}

function toggleReplyForm(commentId) {
  var container = document.getElementById('reply-form-' + commentId);
  if (!container) return;
  if (container.querySelector('.cp-qa-inline-reply')) {
    container.innerHTML = '';
    return;
  }
  container.innerHTML = '<div class="cp-qa-inline-reply">'
    + '<textarea class="cp-qa-textarea" id="reply-textarea-' + commentId + '" placeholder="Write a reply…" style="min-height:60px"></textarea>'
    + '<button class="cp-qa-submit-btn" onclick="submitReply(' + commentId + ')" type="button" style="margin-top:.35rem">Post Reply</button>'
    + '</div>';
}

function submitTopLevelComment() {
  var ta   = document.getElementById('qaTextarea');
  var btn  = document.getElementById('qaSubmitBtn');
  if (!ta || !btn) return;
  var text = ta.value.trim();
  if (!text) return;

  btn.disabled = true;
  fetch('<?= BASE ?>/api/lesson-comments.php?action=add', {
    method: 'POST',
    credentials: 'include',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({lesson_id: LESSON_ID, body: text})
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    btn.disabled = false;
    if (data.ok) {
      ta.value = '';
      _qaLoaded = false;
      loadQA();
    }
  })
  .catch(function() { btn.disabled = false; });
}

function submitReply(parentId) {
  var ta  = document.getElementById('reply-textarea-' + parentId);
  if (!ta) return;
  var text = ta.value.trim();
  if (!text) return;

  fetch('<?= BASE ?>/api/lesson-comments.php?action=add', {
    method: 'POST',
    credentials: 'include',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({lesson_id: LESSON_ID, body: text, parent_id: parentId})
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.ok) {
      _qaLoaded = false;
      loadQA();
    }
  });
}

function escHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

// Load Q&A immediately if that tab is active on page load
<?php if ($activeTab === 'qa'): ?>
loadQA();
<?php endif; ?>

// ─── Quiz modal ───────────────────────────────────────────────────────────────
<?php if ($lessonQuiz && $isEnrolled && !$quizAlreadyPassed): ?>
var _quizTimerInterval = null;
var _quizTimeLeft = <?= (int)$lessonQuiz['time_limit_seconds'] ?>;
var _quizTimeLimitSec = <?= (int)$lessonQuiz['time_limit_seconds'] ?>;
var QUIZ_ID = <?= (int)$lessonQuiz['id'] ?>;
var QUIZ_PASS_SCORE = <?= (int)$lessonQuiz['pass_score'] ?>;

function openQuizModal() {
  var modal = document.getElementById('quizModal');
  if (modal) modal.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeQuizModal() {
  var modal = document.getElementById('quizModal');
  if (modal) modal.classList.add('hidden');
  document.body.style.overflow = '';
  stopTimer();
}

function startQuiz() {
  document.getElementById('quizIntroView').style.display = 'none';
  document.getElementById('quizQuestionsView').style.display = '';
  if (_quizTimeLimitSec > 0) {
    _quizTimeLeft = _quizTimeLimitSec;
    updateTimerDisplay();
    _quizTimerInterval = setInterval(function() {
      _quizTimeLeft--;
      updateTimerDisplay();
      if (_quizTimeLeft <= 0) {
        stopTimer();
        submitQuiz();
      }
    }, 1000);
  }
}

function stopTimer() {
  if (_quizTimerInterval) {
    clearInterval(_quizTimerInterval);
    _quizTimerInterval = null;
  }
}

function updateTimerDisplay() {
  var el = document.getElementById('quizTimerDisplay');
  var timerWrap = document.getElementById('quizTimer');
  if (!el) return;
  var m = Math.floor(_quizTimeLeft / 60);
  var s = _quizTimeLeft % 60;
  el.textContent = m + ':' + (s < 10 ? '0' : '') + s;
  if (_quizTimeLeft <= 30 && timerWrap) timerWrap.classList.add('danger');
}

function submitQuiz() {
  stopTimer();
  var btn = document.getElementById('quizSubmitBtn');
  if (btn) btn.disabled = true;

  // Collect answers
  var answers = {};
  document.querySelectorAll('#quizForm .cp-quiz-question').forEach(function(qEl) {
    var qid = parseInt(qEl.dataset.questionId, 10);
    var sel = qEl.querySelector('input[type="radio"]:checked');
    if (sel) answers[qid] = parseInt(sel.value, 10);
  });

  fetch('<?= BASE ?>/api/quiz-attempt.php', {
    method: 'POST',
    credentials: 'include',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({quiz_id: QUIZ_ID, answers: answers})
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    showQuizResults(data, answers);
  })
  .catch(function() {
    if (btn) btn.disabled = false;
  });
}

function showQuizResults(data, answers) {
  document.getElementById('quizQuestionsView').style.display = 'none';
  var rv = document.getElementById('quizResultsView');
  rv.style.display = '';

  var score   = data.score || 0;
  var passed  = !!data.passed;
  var correct = data.correct || 0;
  var total   = data.total || 0;

  document.getElementById('quizResultScore').textContent = score + '%';
  document.getElementById('quizResultLabel').textContent = correct + ' of ' + total + ' correct';

  var statusEl = document.getElementById('quizResultStatus');
  if (passed) {
    statusEl.textContent = 'Passed!';
    statusEl.className = 'cp-quiz-result-status cp-quiz-result-pass';
    launchConfetti();
  } else {
    statusEl.textContent = 'Not passed — keep it up!';
    statusEl.className = 'cp-quiz-result-status cp-quiz-result-fail';
    document.getElementById('quizTryAgainBtn').style.display = 'inline-flex';
  }

  // Show answer review with correct answers highlighted
  var reviewHtml = '';
  document.querySelectorAll('#quizForm .cp-quiz-question').forEach(function(qEl) {
    var qid = parseInt(qEl.dataset.questionId, 10);
    var selectedOid = answers[qid] || 0;
    reviewHtml += '<div class="cp-quiz-question">';
    reviewHtml += '<div class="cp-quiz-question-text">' + qEl.querySelector('.cp-quiz-question-text').textContent + '</div>';
    qEl.querySelectorAll('.cp-quiz-option').forEach(function(optEl) {
      var oid = parseInt(optEl.dataset.optionId, 10);
      var isCorrect = optEl.querySelector('input').dataset.correct === '1';
      var isSelected = oid === selectedOid;
      var cls = 'cp-quiz-option';
      if (isCorrect) cls += ' correct-ans';
      else if (isSelected && !isCorrect) cls += ' wrong-ans';
      reviewHtml += '<div class="' + cls + '">' + optEl.textContent.trim() + '</div>';
    });
    reviewHtml += '</div>';
  });
  document.getElementById('quizAnswerReview').innerHTML = reviewHtml;
}

function resetQuiz() {
  // Reset form
  document.getElementById('quizForm').reset();
  document.getElementById('quizResultsView').style.display = 'none';
  document.getElementById('quizTryAgainBtn').style.display = 'none';
  document.getElementById('quizAnswerReview').innerHTML = '';
  // Re-enable submit button
  var btn = document.getElementById('quizSubmitBtn');
  if (btn) btn.disabled = false;
  // Start again
  startQuiz();
}

// Confetti (pure CSS, no library)
function launchConfetti() {
  var wrap = document.getElementById('confettiWrap');
  if (!wrap) return;
  wrap.classList.remove('hidden');
  wrap.innerHTML = '';
  var colors = ['#6366f1','#f59e0b','#10b981','#ec4899','#3b82f6','#f87171'];
  for (var i = 0; i < 80; i++) {
    var piece = document.createElement('div');
    piece.className = 'cp-confetti-piece';
    piece.style.left = Math.random() * 100 + 'vw';
    piece.style.width = (Math.random() * 8 + 6) + 'px';
    piece.style.height = (Math.random() * 8 + 6) + 'px';
    piece.style.background = colors[Math.floor(Math.random() * colors.length)];
    piece.style.animationDuration = (Math.random() * 2 + 2) + 's';
    piece.style.animationDelay = (Math.random() * 1.5) + 's';
    wrap.appendChild(piece);
  }
  setTimeout(function() {
    wrap.classList.add('hidden');
    wrap.innerHTML = '';
  }, 5000);
}
<?php endif; ?>

// ─── Bookmarks ────────────────────────────────────────────────────────────────
var _bookmarksLoaded = false;

function toggleBookmarkForm() {
  var form = document.getElementById('bookmarkForm');
  if (!form) return;
  form.classList.toggle('hidden');
}

function captureVideoTime() {
  var video = document.getElementById('cp-video');
  var input = document.getElementById('bmTimeInput');
  if (!video || !input) return;
  var t = Math.floor(video.currentTime);
  var m = Math.floor(t / 60);
  var s = t % 60;
  input.value = m + ':' + (s < 10 ? '0' : '') + s;
}

function parseTimeToSeconds(timeStr) {
  if (!timeStr) return 0;
  var parts = timeStr.split(':');
  if (parts.length === 2) {
    return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
  }
  if (parts.length === 3) {
    return parseInt(parts[0], 10) * 3600 + parseInt(parts[1], 10) * 60 + parseInt(parts[2], 10);
  }
  return 0;
}

function formatSeconds(sec) {
  sec = parseInt(sec, 10) || 0;
  var m = Math.floor(sec / 60);
  var s = sec % 60;
  return m + ':' + (s < 10 ? '0' : '') + s;
}

function saveBookmark() {
  var noteInput = document.getElementById('bmNoteInput');
  var timeInput = document.getElementById('bmTimeInput');
  if (!noteInput) return;

  var note = noteInput.value.trim();
  if (!note) { noteInput.focus(); return; }

  var seconds = 0;
  if (timeInput && timeInput.value.trim()) {
    seconds = parseTimeToSeconds(timeInput.value.trim());
  }

  fetch('<?= BASE ?>/api/lesson-bookmarks.php?action=add', {
    method: 'POST',
    credentials: 'include',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({lesson_id: LESSON_ID, note: note, seconds_at: seconds})
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.ok) {
      noteInput.value = '';
      if (timeInput) timeInput.value = '';
      document.getElementById('bookmarkForm').classList.add('hidden');
      loadBookmarks(true);
    }
  });
}

function loadBookmarks(force) {
  if (_bookmarksLoaded && !force) return;
  _bookmarksLoaded = true;
  var list = document.getElementById('bookmarkList');
  if (!list) return;

  fetch('<?= BASE ?>/api/lesson-bookmarks.php?action=list&lesson_id=' + LESSON_ID, {
    credentials: 'include'
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (!data.ok || !data.bookmarks || data.bookmarks.length === 0) {
      list.innerHTML = '<p style="font-size:.82rem;color:var(--text-muted,#888);margin:.5rem 0">No bookmarks yet.</p>';
      return;
    }
    renderBookmarks(data.bookmarks, list);
  })
  .catch(function() {
    list.innerHTML = '<p style="font-size:.82rem;color:var(--text-muted,#888)">Failed to load bookmarks.</p>';
  });
}

function renderBookmarks(bookmarks, container) {
  var html = '';
  bookmarks.forEach(function(bm) {
    var hasSec = bm.seconds_at > 0;
    html += '<div class="cp-bookmark-item" id="bm-' + bm.id + '">';
    if (hasSec) {
      html += '<button class="cp-bm-time-pill" data-seconds="' + bm.seconds_at + '" onclick="seekToBookmark(' + bm.seconds_at + ')" title="Seek to ' + formatSeconds(bm.seconds_at) + '">'
            + formatSeconds(bm.seconds_at) + '</button>';
    }
    html += '<span class="cp-bm-note-text">' + escHtml(bm.note || '') + '</span>';
    html += '<button class="cp-bm-delete-btn" onclick="deleteBookmark(' + bm.id + ')" title="Delete bookmark">'
          + '<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">'
          + '<polyline points="3 6 5 6 21 6"/>'
          + '<path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>'
          + '<path d="M10 11v6M14 11v6"/>'
          + '<path d="M9 6V4h6v2"/>'
          + '</svg></button>';
    html += '</div>';
  });
  container.innerHTML = html;
}

function seekToBookmark(seconds) {
  var video = document.getElementById('cp-video');
  if (video) {
    video.currentTime = seconds;
    video.play();
  }
}

function deleteBookmark(bookmarkId) {
  fetch('<?= BASE ?>/api/lesson-bookmarks.php?action=delete', {
    method: 'POST',
    credentials: 'include',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({bookmark_id: bookmarkId})
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.ok) {
      var el = document.getElementById('bm-' + bookmarkId);
      if (el) el.remove();
    }
  });
}

// Load bookmarks on page load (Notes tab pre-loaded or visited)
(function() {
  // Always load bookmarks when notes tab is active, or lazily on tab switch
  var notesPanel = document.getElementById('tab-notes');
  if (notesPanel && notesPanel.classList.contains('active')) {
    loadBookmarks(false);
  }
})();

// Hook into tab switch to load bookmarks when Notes tab is opened
var _origSwitchTab = switchTab;
switchTab = function(name, btnEl) {
  _origSwitchTab(name, btnEl);
  if (name === 'notes') loadBookmarks(false);
};
</script>

</body>
</html>
