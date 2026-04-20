<?php
require_once __DIR__ . '/db.php';

// ============================================================
//  Schema bootstrap
// ============================================================

function ensure_course_content_tables(mysqli $mysqli): void
{
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS course_modules (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            course_id   INT NOT NULL,
            title       VARCHAR(255) NOT NULL DEFAULT '',
            sort_order  INT NOT NULL DEFAULT 0,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_cm_course_sort (course_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $mysqli->query("
        CREATE TABLE IF NOT EXISTS course_lessons (
            id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
            module_id        INT UNSIGNED NOT NULL,
            course_id        INT NOT NULL,
            title            VARCHAR(255) NOT NULL DEFAULT '',
            video_url        VARCHAR(1024) NOT NULL DEFAULT '',
            video_type       ENUM('youtube','vimeo','mp4') NOT NULL DEFAULT 'youtube',
            duration_seconds INT NOT NULL DEFAULT 0,
            is_preview       TINYINT(1) NOT NULL DEFAULT 0,
            sort_order       INT NOT NULL DEFAULT 0,
            created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_cl_module_sort  (module_id, sort_order),
            KEY idx_cl_course_sort  (course_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Extend video_type ENUM to include 'gdrive' and 'audio' (safe to run repeatedly)
    $mysqli->query("ALTER TABLE course_lessons MODIFY COLUMN video_type ENUM('youtube','vimeo','mp4','gdrive','audio') NOT NULL DEFAULT 'youtube'");

    try {
        $mysqli->query("ALTER TABLE course_lessons ADD COLUMN subtitle_url VARCHAR(500) NULL AFTER duration_seconds");
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() !== 1060) throw $e; // 1060 = duplicate column
    }

    $mysqli->query("
        CREATE TABLE IF NOT EXISTS lesson_transcripts (
            lesson_id  INT UNSIGNED NOT NULL,
            content    TEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (lesson_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $mysqli->query("
        CREATE TABLE IF NOT EXISTS lesson_progress (
            client_id       BIGINT UNSIGNED NOT NULL,
            lesson_id       INT UNSIGNED NOT NULL,
            completed       TINYINT(1) NOT NULL DEFAULT 0,
            watched_seconds INT NOT NULL DEFAULT 0,
            updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (client_id, lesson_id),
            KEY idx_lp_lesson (lesson_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// ============================================================
//  Modules
// ============================================================

/**
 * Returns all modules for a course, ordered by sort_order.
 * Each module has a `lessons` key containing its ordered lessons.
 */
function get_course_modules(mysqli $mysqli, int $courseId): array
{
    $stmt = $mysqli->prepare(
        'SELECT id, course_id, title, sort_order, created_at
           FROM course_modules
          WHERE course_id = ?
          ORDER BY sort_order ASC, id ASC'
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    $modules = [];
    while ($row = $res->fetch_assoc()) {
        $row['id']         = (int)$row['id'];
        $row['course_id']  = (int)$row['course_id'];
        $row['sort_order'] = (int)$row['sort_order'];
        $row['lessons']    = [];
        $modules[$row['id']] = $row;
    }

    if (empty($modules)) {
        return [];
    }

    // Fetch all lessons for this course in one query
    $stmt2 = $mysqli->prepare(
        'SELECT id, module_id, course_id, title, video_url, video_type,
                duration_seconds, subtitle_url, is_preview, sort_order, created_at
           FROM course_lessons
          WHERE course_id = ?
          ORDER BY sort_order ASC, id ASC'
    );
    if (!$stmt2) {
        return array_values($modules);
    }
    $stmt2->bind_param('i', $courseId);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $stmt2->close();

    while ($lesson = $res2->fetch_assoc()) {
        $lesson = _cast_lesson($lesson);
        $mid = $lesson['module_id'];
        if (isset($modules[$mid])) {
            $modules[$mid]['lessons'][] = $lesson;
        }
    }

    return array_values($modules);
}

function get_module(mysqli $mysqli, int $moduleId): array|null
{
    $stmt = $mysqli->prepare(
        'SELECT id, course_id, title, sort_order, created_at
           FROM course_modules
          WHERE id = ?
          LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $moduleId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return null;
    }
    $row['id']         = (int)$row['id'];
    $row['course_id']  = (int)$row['course_id'];
    $row['sort_order'] = (int)$row['sort_order'];
    return $row;
}

/**
 * Insert or update a module. Returns the module id.
 */
function save_module(
    mysqli $mysqli,
    int $courseId,
    string $title,
    int $sortOrder,
    ?int $moduleId = null
): int {
    if ($moduleId !== null) {
        $stmt = $mysqli->prepare(
            'UPDATE course_modules SET course_id = ?, title = ?, sort_order = ?
              WHERE id = ?'
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('isii', $courseId, $title, $sortOrder, $moduleId);
        $stmt->execute();
        $stmt->close();
        return $moduleId;
    }

    $stmt = $mysqli->prepare(
        'INSERT INTO course_modules (course_id, title, sort_order) VALUES (?, ?, ?)'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('isi', $courseId, $title, $sortOrder);
    $stmt->execute();
    $newId = (int)$mysqli->insert_id;
    $stmt->close();
    return $newId;
}

/**
 * Delete a module and cascade-delete its lessons, transcripts, and progress.
 */
function delete_module(mysqli $mysqli, int $moduleId): bool
{
    // Collect lesson IDs first
    $stmt = $mysqli->prepare('SELECT id FROM course_lessons WHERE module_id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $moduleId);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    $lessonIds = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $lessonIds[] = (int)$row['id'];
        }
    }

    foreach ($lessonIds as $lid) {
        delete_lesson($mysqli, $lid);
    }

    $stmt2 = $mysqli->prepare('DELETE FROM course_modules WHERE id = ?');
    if (!$stmt2) {
        return false;
    }
    $stmt2->bind_param('i', $moduleId);
    $ok = $stmt2->execute();
    $stmt2->close();
    return $ok;
}

// ============================================================
//  Lessons
// ============================================================

function get_lesson(mysqli $mysqli, int $lessonId): array|null
{
    $stmt = $mysqli->prepare(
        'SELECT id, module_id, course_id, title, video_url, video_type,
                duration_seconds, subtitle_url, is_preview, sort_order, created_at
           FROM course_lessons
          WHERE id = ?
          LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $lessonId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return $row ? _cast_lesson($row) : null;
}

/**
 * Insert or update a lesson. Returns the lesson id.
 */
function save_lesson(
    mysqli $mysqli,
    int $moduleId,
    int $courseId,
    string $title,
    string $videoUrl,
    string $videoType,
    int $durationSeconds,
    bool $isPreview,
    int $sortOrder,
    ?int $lessonId = null,
    string $subtitleUrl = ''
): int {
    $isPreviewInt = $isPreview ? 1 : 0;

    if ($lessonId !== null) {
        $stmt = $mysqli->prepare(
            'UPDATE course_lessons
                SET module_id = ?, course_id = ?, title = ?, video_url = ?,
                    video_type = ?, duration_seconds = ?, subtitle_url = ?, is_preview = ?, sort_order = ?
              WHERE id = ?'
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param(
            'iisssisiii',
            $moduleId, $courseId, $title, $videoUrl,
            $videoType, $durationSeconds, $subtitleUrl, $isPreviewInt, $sortOrder,
            $lessonId
        );
        $stmt->execute();
        $stmt->close();
        return $lessonId;
    }

    $stmt = $mysqli->prepare(
        'INSERT INTO course_lessons
            (module_id, course_id, title, video_url, video_type, duration_seconds, subtitle_url, is_preview, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param(
        'iissssiii',
        $moduleId, $courseId, $title, $videoUrl,
        $videoType, $durationSeconds, $subtitleUrl, $isPreviewInt, $sortOrder
    );
    $stmt->execute();
    $newId = (int)$mysqli->insert_id;
    $stmt->close();
    return $newId;
}

/**
 * Delete a lesson and its transcript + progress rows.
 */
function delete_lesson(mysqli $mysqli, int $lessonId): bool
{
    // Delete transcript
    $s1 = $mysqli->prepare('DELETE FROM lesson_transcripts WHERE lesson_id = ?');
    if ($s1) {
        $s1->bind_param('i', $lessonId);
        $s1->execute();
        $s1->close();
    }

    // Delete progress
    $s2 = $mysqli->prepare('DELETE FROM lesson_progress WHERE lesson_id = ?');
    if ($s2) {
        $s2->bind_param('i', $lessonId);
        $s2->execute();
        $s2->close();
    }

    // Delete lesson
    $s3 = $mysqli->prepare('DELETE FROM course_lessons WHERE id = ?');
    if (!$s3) {
        return false;
    }
    $s3->bind_param('i', $lessonId);
    $ok = $s3->execute();
    $s3->close();
    return $ok;
}

// ============================================================
//  Transcripts
// ============================================================

function get_lesson_transcript(mysqli $mysqli, int $lessonId): string
{
    $stmt = $mysqli->prepare(
        'SELECT content FROM lesson_transcripts WHERE lesson_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return '';
    }
    $stmt->bind_param('i', $lessonId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return (string)($row['content'] ?? '');
}

function save_lesson_transcript(mysqli $mysqli, int $lessonId, string $content): bool
{
    $stmt = $mysqli->prepare(
        'INSERT INTO lesson_transcripts (lesson_id, content)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = CURRENT_TIMESTAMP'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('is', $lessonId, $content);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// ============================================================
//  Progress
// ============================================================

function mark_lesson_complete(
    mysqli $mysqli,
    int $clientId,
    int $lessonId,
    int $watchedSeconds = 0
): bool {
    $stmt = $mysqli->prepare(
        'INSERT INTO lesson_progress (client_id, lesson_id, completed, watched_seconds)
         VALUES (?, ?, 1, ?)
         ON DUPLICATE KEY UPDATE
             completed       = 1,
             watched_seconds = GREATEST(watched_seconds, VALUES(watched_seconds)),
             updated_at      = CURRENT_TIMESTAMP'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iii', $clientId, $lessonId, $watchedSeconds);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function save_lesson_progress(
    mysqli $mysqli,
    int $clientId,
    int $lessonId,
    int $watchedSeconds,
    bool $completed = false
): bool {
    $watchedSeconds = max(0, $watchedSeconds);
    $completedInt = $completed ? 1 : 0;

    $stmt = $mysqli->prepare(
        'INSERT INTO lesson_progress (client_id, lesson_id, completed, watched_seconds)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             completed = GREATEST(completed, VALUES(completed)),
             watched_seconds = GREATEST(watched_seconds, VALUES(watched_seconds)),
             updated_at = CURRENT_TIMESTAMP'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iiii', $clientId, $lessonId, $completedInt, $watchedSeconds);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Returns a map of lessonId => ['completed' => bool, 'watched_seconds' => int]
 * for all lessons belonging to courses this client has progress on.
 */
function get_user_lesson_progress(mysqli $mysqli, int $clientId, int $courseId): array
{
    $stmt = $mysqli->prepare(
        'SELECT lp.lesson_id, lp.completed, lp.watched_seconds
           FROM lesson_progress lp
           JOIN course_lessons cl ON cl.id = lp.lesson_id
          WHERE lp.client_id = ?
            AND cl.course_id = ?'
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ii', $clientId, $courseId);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    $map = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $map[(int)$row['lesson_id']] = [
                'completed'      => (bool)(int)$row['completed'],
                'watched_seconds' => (int)$row['watched_seconds'],
            ];
        }
    }
    return $map;
}

// ============================================================
//  Navigation helpers
// ============================================================

function get_first_lesson(mysqli $mysqli, int $courseId): array|null
{
    $stmt = $mysqli->prepare(
        'SELECT cl.id, cl.module_id, cl.course_id, cl.title, cl.video_url, cl.video_type,
                cl.duration_seconds, cl.subtitle_url, cl.is_preview, cl.sort_order, cl.created_at
           FROM course_lessons cl
           JOIN course_modules cm ON cm.id = cl.module_id
          WHERE cl.course_id = ?
          ORDER BY cm.sort_order ASC, cm.id ASC, cl.sort_order ASC, cl.id ASC
          LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ? _cast_lesson($row) : null;
}

/**
 * Returns ['prev' => lesson|null, 'next' => lesson|null].
 * Ordering is by module sort_order, then lesson sort_order (both ascending).
 */
function get_adjacent_lessons(mysqli $mysqli, int $lessonId): array
{
    // Get the current lesson to know its course
    $current = get_lesson($mysqli, $lessonId);
    if (!$current) {
        return ['prev' => null, 'next' => null];
    }

    $courseId = $current['course_id'];

    // Fetch ALL lesson IDs in order for this course
    $stmt = $mysqli->prepare(
        'SELECT cl.id
           FROM course_lessons cl
           JOIN course_modules cm ON cm.id = cl.module_id
          WHERE cl.course_id = ?
          ORDER BY cm.sort_order ASC, cm.id ASC, cl.sort_order ASC, cl.id ASC'
    );
    if (!$stmt) {
        return ['prev' => null, 'next' => null];
    }
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    $ids = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int)$row['id'];
        }
    }

    $pos = array_search($lessonId, $ids, true);
    if ($pos === false) {
        return ['prev' => null, 'next' => null];
    }

    $prevLesson = ($pos > 0) ? get_lesson($mysqli, $ids[$pos - 1]) : null;
    $nextLesson = ($pos < count($ids) - 1) ? get_lesson($mysqli, $ids[$pos + 1]) : null;

    return ['prev' => $prevLesson, 'next' => $nextLesson];
}

// ============================================================
//  Internal helpers
// ============================================================

/**
 * Normalise any Google Drive sharing URL to the embeddable /preview form.
 * Non-Drive URLs are returned unchanged.
 */
function normalize_gdrive_url(string $url): string
{
    if (strpos($url, 'drive.google.com') === false) {
        return $url;
    }
    // .../file/d/{ID}/view... → .../file/d/{ID}/preview
    if (preg_match('|drive\.google\.com/file/d/([^/?#]+)|', $url, $m)) {
        return 'https://drive.google.com/file/d/' . rawurlencode($m[1]) . '/preview';
    }
    // ?id={ID} (open?id=, uc?id=) → .../file/d/{ID}/preview
    if (preg_match('/[?&]id=([^&#]+)/', $url, $m)) {
        return 'https://drive.google.com/file/d/' . rawurlencode($m[1]) . '/preview';
    }
    return $url;
}

/**
 * Extract a Google Drive file id from common share URLs.
 */
function gdrive_file_id_from_url(string $url): string
{
    if (preg_match('|drive\.google\.com/file/d/([^/?#]+)|', $url, $m)) {
        return (string)$m[1];
    }
    if (preg_match('/[?&]id=([^&#]+)/', $url, $m)) {
        return (string)$m[1];
    }
    return '';
}

/**
 * Build a direct media URL for public Google Drive files.
 * Returns empty string when no file id can be extracted.
 */
function gdrive_direct_media_url(string $url): string
{
    $id = gdrive_file_id_from_url($url);
    if ($id === '') {
        return '';
    }
    return 'https://drive.usercontent.google.com/uc?id=' . rawurlencode($id) . '&export=download&confirm=t';
}

function _cast_lesson(array $row): array
{
    $row['id']               = (int)$row['id'];
    $row['module_id']        = (int)$row['module_id'];
    $row['course_id']        = (int)$row['course_id'];
    $row['duration_seconds'] = (int)$row['duration_seconds'];
    $row['is_preview']       = (bool)(int)$row['is_preview'];
    $row['sort_order']       = (int)$row['sort_order'];
    return $row;
}
