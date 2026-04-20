<?php

if (!defined('BASE')) define('BASE', '');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/course-content-repo.php';
require_once __DIR__ . '/../includes/purchases-repo.php';

$lessonId = (int)($_GET['lesson_id'] ?? 0);
if ($lessonId <= 0) {
    http_response_code(404);
    exit;
}

$user = auth_current_user();
if (!$user) {
    http_response_code(403);
    exit;
}

ensure_course_content_tables($mysqli);
ensure_purchases_table($mysqli);

$lesson = get_lesson($mysqli, $lessonId);
if (!$lesson) {
    http_response_code(404);
    exit;
}

$courseId    = (int)($lesson['course_id'] ?? 0);
$isPreview   = !empty($lesson['is_preview']);
$isEnrolled  = has_user_enrolled_course($mysqli, (int)$user['id'], $courseId);
if (!$isEnrolled && !$isPreview) {
    http_response_code(403);
    exit;
}

$videoType = (string)($lesson['video_type'] ?? '');
$videoUrl  = (string)($lesson['video_url'] ?? '');

if ($videoType === 'gdrive') {
    $videoUrl = gdrive_direct_media_url($videoUrl);
}

if ($videoUrl === '' || !in_array($videoType, ['mp4', 'gdrive'], true)) {
    http_response_code(404);
    exit;
}

ignore_user_abort(true);
set_time_limit(0);

while (ob_get_level() > 0) {
    ob_end_clean();
}

$remoteHeaders = [];
$statusCode = 200;
$headersCommitted = false;

$forwardHeaders = static function(array $headerLines, int $code): void {
    http_response_code(in_array($code, [200, 206], true) ? $code : 200);

    $allowed = ['content-type', 'content-length', 'content-range', 'accept-ranges', 'etag', 'last-modified'];
    $seenAcceptRanges = false;

    foreach ($headerLines as $line) {
        $parts = explode(':', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $name  = strtolower(trim($parts[0]));
        $value = trim($parts[1]);
        if (!in_array($name, $allowed, true) || $value === '') {
            continue;
        }
        if ($name === 'accept-ranges') {
            $seenAcceptRanges = true;
        }
        header($parts[0] . ': ' . $value, true);
    }

    if (!$seenAcceptRanges) {
        header('Accept-Ranges: bytes', true);
    }
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
    header('Pragma: no-cache', true);
};

// Detect if Google returned an HTML confirmation page instead of video bytes.
$isHtmlResponse = static function(array $headerLines): bool {
    foreach ($headerLines as $line) {
        $parts = explode(':', $line, 2);
        if (count($parts) === 2 && strtolower(trim($parts[0])) === 'content-type') {
            return str_contains(strtolower($parts[1]), 'text/html');
        }
    }
    return false;
};

$ch = curl_init($videoUrl);
$requestHeaders = ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'];
if (!empty($_SERVER['HTTP_RANGE'])) {
    $requestHeaders[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
}

curl_setopt_array($ch, [
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 10,
    CURLOPT_TIMEOUT        => 0,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_HTTPHEADER     => $requestHeaders,
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_FAILONERROR    => false,
    CURLOPT_BUFFERSIZE     => 65536,
    CURLOPT_COOKIEFILE     => '', // enable in-memory cookie engine (carries cookies across redirects)
]);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
    curl_setopt($ch, CURLOPT_NOBODY, true);
}

curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function($curl, string $header) use (&$remoteHeaders, &$statusCode, &$headersCommitted, $forwardHeaders, $isHtmlResponse): int {
    $len = strlen($header);
    $trim = trim($header);

    if ($trim === '') {
        if (!$headersCommitted) {
            if ($isHtmlResponse($remoteHeaders)) {
                // Google returned a confirmation HTML page instead of the video.
                http_response_code(502);
                header('Content-Type: text/plain; charset=UTF-8');
                $headersCommitted = true;
                curl_setopt($curl, CURLOPT_NOBODY, true); // abort body transfer
                return $len;
            }
            $forwardHeaders($remoteHeaders, $statusCode);
            $headersCommitted = true;
        }
        return $len;
    }

    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $trim, $m)) {
        $statusCode = (int)$m[1];
        $remoteHeaders = [];
        return $len;
    }

    $remoteHeaders[] = $trim;
    return $len;
});

curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function($curl, string $chunk) use (&$headersCommitted, &$remoteHeaders, &$statusCode, $forwardHeaders): int {
    if (!$headersCommitted) {
        $forwardHeaders($remoteHeaders, $statusCode);
        $headersCommitted = true;
    }
    echo $chunk;
    flush();
    return strlen($chunk);
});

$ok = curl_exec($ch);
$err = curl_error($ch);
$code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($ok === false && !$headersCommitted) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Video proxy failed: ' . ($err !== '' ? $err : ('HTTP ' . $code));
}
