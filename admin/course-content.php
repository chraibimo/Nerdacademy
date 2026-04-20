<?php
if (!defined('BASE')) define('BASE', '');

// Load dependencies BEFORE _head.php so POST handlers can redirect
// without triggering "headers already sent" errors.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/course-content-repo.php';
require_once __DIR__ . '/../includes/courses-repo.php';

$user = auth_current_user();
if (!$user || !auth_can_access_admin_panel($user)) {
    header('Location: ' . BASE . '/index.php');
    exit;
}

// ============================================================
//  Bootstrap tables
// ============================================================
ensure_course_content_tables($mysqli);

// ============================================================
//  Flash message helpers
// ============================================================
$flash = '';
$flashType = 'success';

function set_flash(string &$flash, string &$flashType, string $msg, string $type = 'success'): void
{
    $flash     = $msg;
    $flashType = $type;
}

function lesson_storage_path(int $courseId, int $lessonId): string
{
  $projectRoot = realpath(__DIR__ . '/..');
  if ($projectRoot === false) {
    return '';
  }

  return $projectRoot
    . DIRECTORY_SEPARATOR . 'storage'
    . DIRECTORY_SEPARATOR . 'courses'
    . DIRECTORY_SEPARATOR . (string)$courseId
    . DIRECTORY_SEPARATOR . 'lessons'
    . DIRECTORY_SEPARATOR . (string)$lessonId;
}

function ensure_lesson_storage_folder(int $courseId, int $lessonId): bool
{
  $path = lesson_storage_path($courseId, $lessonId);
  if ($path === '') {
    return false;
  }

  if (is_dir($path)) {
    return true;
  }

  return @mkdir($path, 0775, true);
}

  function find_command_path(array $candidates): string
  {
    foreach ($candidates as $candidate) {
      $candidate = trim((string)$candidate);
      if ($candidate === '') {
        continue;
      }

      if (strpbrk($candidate, '/\\') !== false && is_file($candidate)) {
        return $candidate;
      }

      $probe = 'where ' . escapeshellarg($candidate) . ' 2>NUL';
      $lines = [];
      $code  = 1;
      @exec($probe, $lines, $code);
      if ($code === 0 && !empty($lines[0])) {
        return trim((string)$lines[0]);
      }
    }
    return '';
  }

  function detect_media_duration_seconds(string $mediaPath): int
  {
    if (!is_file($mediaPath)) {
      return 0;
    }

    $ffprobe = find_command_path([
      getenv('NERDACADEMY_FFPROBE_CMD') ?: '',
      'ffprobe',
    ]);

    if ($ffprobe === '') {
      $localAppData = (string)getenv('LOCALAPPDATA');
      if ($localAppData !== '') {
        $matches = glob($localAppData . '\\Microsoft\\WinGet\\Packages\\*FFmpeg*\\ffmpeg-*\\bin\\ffprobe.exe');
        if (is_array($matches) && !empty($matches[0]) && is_file($matches[0])) {
          $ffprobe = (string)$matches[0];
        }
      }
    }

    if ($ffprobe === '') {
      return 0;
    }

    $cmd = escapeshellarg($ffprobe)
      . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '
      . escapeshellarg($mediaPath);

    $out = [];
    $code = 1;
    @exec($cmd . ' 2>NUL', $out, $code);
    if ($code !== 0 || empty($out[0])) {
      return 0;
    }

    $duration = (float)trim((string)$out[0]);
    if ($duration <= 0) {
      return 0;
    }

    return (int)max(1, round($duration));
  }

// ============================================================
//  Permission guard
// ============================================================
$canManage = auth_has_permission($user, 'manage_courses') || !empty($user['is_admin']);

// ============================================================
//  POST handlers
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    $action = trim($_POST['action'] ?? '');

    // ── Save Module ──────────────────────────────────────────
    if ($action === 'save_module') {
        $courseId  = (int)($_POST['course_id']  ?? 0);
        $title     = trim($_POST['title']       ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $moduleId  = (int)($_POST['module_id']  ?? 0) ?: null;

        if ($courseId > 0 && $title !== '') {
            $newId = save_module($mysqli, $courseId, $title, $sortOrder, $moduleId);
            if ($newId > 0) {
                set_flash($flash, $flashType, $moduleId ? 'Module updated.' : 'Module created.');
            } else {
                set_flash($flash, $flashType, 'Failed to save module.', 'danger');
            }
        } else {
            set_flash($flash, $flashType, 'Title and course are required.', 'danger');
        }
        $redirectCourseId = $courseId;
    }

    // ── Delete Module ────────────────────────────────────────
    elseif ($action === 'delete_module') {
        $moduleId        = (int)($_POST['module_id'] ?? 0);
        $redirectCourseId = (int)($_POST['course_id'] ?? 0);
        if ($moduleId > 0) {
            $ok = delete_module($mysqli, $moduleId);
            set_flash($flash, $flashType, $ok ? 'Module deleted.' : 'Failed to delete module.', $ok ? 'success' : 'danger');
        }
    }

    // ── Save Lesson ──────────────────────────────────────────
    elseif ($action === 'save_lesson') {
        $moduleId        = (int)($_POST['module_id']        ?? 0);
        $courseId        = (int)($_POST['course_id']        ?? 0);
        $title           = trim($_POST['title']             ?? '');
        $durationSeconds = (int)($_POST['duration_seconds'] ?? 0);
        $isPreview       = !empty($_POST['is_preview']);
        $sortOrder       = (int)($_POST['sort_order']       ?? 0);
        $lessonId        = (int)($_POST['lesson_id']        ?? 0) ?: null;
        $mediaFile       = $_FILES['lesson_media']          ?? null;
        $subtitleUrl     = trim($_POST['subtitle_url']      ?? '');
        $redirectCourseId = $courseId;

        if ($moduleId > 0 && $courseId > 0 && $title !== '') {
        $existingLesson = $lessonId !== null ? get_lesson($mysqli, $lessonId) : null;
        if ($lessonId !== null && !$existingLesson) {
          set_flash($flash, $flashType, 'Lesson not found.', 'danger');
          $existingLesson = null;
        }

        $hasUpload = is_array($mediaFile)
          && (int)($mediaFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if (!$hasUpload && $lessonId === null) {
          set_flash($flash, $flashType, 'Please upload a video or audio file for the lesson.', 'danger');
          $redirectCourseId = $courseId;
          goto save_lesson_done;
        }

        $videoUrl = (string)($existingLesson['video_url'] ?? '');
        $videoType = (string)($existingLesson['video_type'] ?? 'mp4');
        $effectiveDuration = (int)($existingLesson['duration_seconds'] ?? max(0, $durationSeconds));

            $newId = save_lesson(
                $mysqli, $moduleId, $courseId, $title, $videoUrl,
          $videoType, $effectiveDuration, $isPreview, $sortOrder, $lessonId, $subtitleUrl
            );

            if ($newId > 0) {
          $folderOk = ensure_lesson_storage_folder($courseId, $newId);
          if (!$folderOk) {
            set_flash($flash, $flashType, 'Lesson saved, but the storage folder could not be created.', 'warning');
          } else {
                $uploadMessage = '';

          if ($hasUpload) {
                  $uploadError = (int)($mediaFile['error'] ?? UPLOAD_ERR_NO_FILE);
                  if ($uploadError !== UPLOAD_ERR_OK || empty($mediaFile['tmp_name']) || !is_uploaded_file((string)$mediaFile['tmp_name'])) {
                    $uploadMessage = 'Lesson saved, but media upload failed.';
                  } else {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime  = $finfo ? (string)finfo_file($finfo, (string)$mediaFile['tmp_name']) : '';
                    if ($finfo) {
                      finfo_close($finfo);
                    }

                    $isAudio = str_starts_with($mime, 'audio/');
                    $isVideo = str_starts_with($mime, 'video/');
                    if (!$isAudio && !$isVideo) {
                      $uploadMessage = 'Lesson saved, but uploaded file must be audio or video.';
                    } else {
                      $ext = strtolower((string)pathinfo((string)($mediaFile['name'] ?? ''), PATHINFO_EXTENSION));
                      if (!preg_match('/^[a-z0-9]{1,10}$/', $ext)) {
                        $ext = $isAudio ? 'mp3' : 'mp4';
                      }

                      $storagePath = lesson_storage_path($courseId, $newId);
                      $targetName  = 'media.' . $ext;
                      $targetPath  = $storagePath . DIRECTORY_SEPARATOR . $targetName;

                      foreach ((array)glob($storagePath . DIRECTORY_SEPARATOR . 'media.*') as $oldMedia) {
                        if (is_file($oldMedia)) {
                          @unlink($oldMedia);
                        }
                      }

                      if (!@move_uploaded_file((string)$mediaFile['tmp_name'], $targetPath)) {
                        $uploadMessage = 'Lesson saved, but media file could not be stored.';
                      } else {
                        $newVideoUrl  = BASE . '/storage/courses/' . $courseId . '/lessons/' . $newId . '/' . rawurlencode($targetName);
                        $newVideoType = $isAudio ? 'audio' : 'mp4';
                        $autoDuration = detect_media_duration_seconds($targetPath);
                        if ($autoDuration > 0) {
                          $effectiveDuration = $autoDuration;
                        }

                        save_lesson(
                          $mysqli,
                          $moduleId,
                          $courseId,
                          $title,
                          $newVideoUrl,
                          $newVideoType,
                          $effectiveDuration,
                          $isPreview,
                          $sortOrder,
                          $newId,
                          $subtitleUrl
                        );

                        $uploadMessage = $autoDuration > 0
                          ? ('Lesson and media uploaded successfully. Duration set to ' . $effectiveDuration . ' seconds.')
                          : 'Lesson and media uploaded successfully. Duration could not be detected automatically.';
                      }
                    }
                  }
                }

                if ($uploadMessage !== '') {
                  set_flash($flash, $flashType, $uploadMessage, str_contains(strtolower($uploadMessage), 'failed') || str_contains(strtolower($uploadMessage), 'must be') || str_contains(strtolower($uploadMessage), 'could not') ? 'warning' : 'success');
                } else {
                  set_flash($flash, $flashType, $lessonId ? 'Lesson updated.' : 'Lesson created.');
                }
                }
            } else {
                set_flash($flash, $flashType, 'Failed to save lesson.', 'danger');
            }
        } else {
            set_flash($flash, $flashType, 'Module, course, and title are required.', 'danger');
        }
save_lesson_done:
    }

    // ── Delete Lesson ────────────────────────────────────────
    elseif ($action === 'delete_lesson') {
        $lessonId        = (int)($_POST['lesson_id']  ?? 0);
        $redirectCourseId = (int)($_POST['course_id'] ?? 0);
        if ($lessonId > 0) {
            $ok = delete_lesson($mysqli, $lessonId);
            set_flash($flash, $flashType, $ok ? 'Lesson deleted.' : 'Failed to delete lesson.', $ok ? 'success' : 'danger');
        }
    }

    // ── Save Transcript ──────────────────────────────────────
    elseif ($action === 'save_transcript') {
        $lessonId        = (int)($_POST['lesson_id']  ?? 0);
        $content         = $_POST['content']          ?? '';
        $redirectCourseId = (int)($_POST['course_id'] ?? 0);
        if ($lessonId > 0) {
            $ok = save_lesson_transcript($mysqli, $lessonId, $content);
            set_flash($flash, $flashType, $ok ? 'Transcript saved.' : 'Failed to save transcript.', $ok ? 'success' : 'danger');
        }
    }

    // Redirect to avoid form re-submission
    $redir = BASE . '/admin/course-content.php';
    if (!empty($redirectCourseId)) {
        $redir .= '?course_id=' . (int)$redirectCourseId;
    }
    if ($flash !== '') {
        $redir .= (str_contains($redir, '?') ? '&' : '?') . 'msg=' . urlencode($flash) . '&msg_type=' . urlencode($flashType);
    }
    header('Location: ' . $redir);
    exit;
}

// ============================================================
//  Flash from redirect
// ============================================================
if (isset($_GET['msg'])) {
    $flash     = htmlspecialchars(strip_tags($_GET['msg']), ENT_QUOTES);
    $flashType = in_array($_GET['msg_type'] ?? '', ['success','danger','warning'], true)
        ? $_GET['msg_type']
        : 'success';
}

// ============================================================
//  Data for the page
// ============================================================
$allCourses = load_all_courses($mysqli, false);  // include inactive
$selectedCourseId = (int)($_GET['course_id'] ?? 0);
$selectedCourse   = null;
$modules          = [];

if ($selectedCourseId > 0) {
    foreach ($allCourses as $c) {
        if ((int)$c['id'] === $selectedCourseId) {
            $selectedCourse = $c;
            break;
        }
    }
    if ($selectedCourse) {
        $modules = get_course_modules($mysqli, $selectedCourseId);
    }
}

// Now it is safe to output HTML — include the shared admin shell
$admin_page_title  = 'Course Content';
$admin_active_page = 'course-content';
require_once __DIR__ . '/_head.php';

// Helper: format seconds to mm:ss or hh:mm:ss
function fmt_duration(int $s): string
{
    if ($s <= 0) return '—';
    $h = intdiv($s, 3600);
    $m = intdiv($s % 3600, 60);
    $sec = $s % 60;
    if ($h > 0) {
        return sprintf('%d:%02d:%02d', $h, $m, $sec);
    }
    return sprintf('%d:%02d', $m, $sec);
}
?>

<!-- ============================================================
     Page header
     ============================================================ -->
<div class="a-page-header">
  <div>
    <h1 class="a-page-title">Course Content</h1>
    <p class="a-page-sub">Manage modules, lessons, and transcripts for your courses.</p>
  </div>
  <?php if ($selectedCourseId > 0 && $canManage): ?>
  <button class="a-btn a-btn--primary" onclick="openModuleModal()">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
         style="vertical-align:middle;margin-right:.35rem;" aria-hidden="true">
      <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
    </svg>
    Add Module
  </button>
  <?php endif; ?>
</div>

<!-- Flash -->
<?php if ($flash !== ''): ?>
<div class="a-alert a-alert--<?= $flashType === 'danger' ? 'danger' : 'success' ?>" role="alert"
     style="margin-bottom:1.25rem;">
  <?= $flash ?>
</div>
<?php endif; ?>

<!-- ============================================================
     Course selector card
     ============================================================ -->
<div class="a-card" style="margin-bottom:1.5rem;">
  <div class="a-card-body" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
    <label for="courseSelector" style="font-weight:600;white-space:nowrap;color:var(--a-text-sub);">
      Select Course
    </label>
    <form method="get" action="<?= BASE ?>/admin/course-content.php"
          style="display:flex;gap:.75rem;align-items:center;flex:1;min-width:220px;">
      <select id="courseSelector" name="course_id"
              onchange="this.form.submit()"
              style="flex:1;padding:.5rem .75rem;border:1px solid var(--a-border);border-radius:var(--a-radius);
                     font-size:.9rem;background:#fff;color:var(--a-text);outline:none;cursor:pointer;">
        <option value="">— Choose a course —</option>
        <?php foreach ($allCourses as $c): ?>
          <option value="<?= (int)$c['id'] ?>"
            <?= (int)$c['id'] === $selectedCourseId ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['title'], ENT_QUOTES) ?>
            <?= $c['is_active'] ? '' : ' (inactive)' ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="a-btn a-btn--ghost a-btn--sm">Go</button>
    </form>
    <?php if ($selectedCourse): ?>
      <span class="a-badge a-badge--info" style="font-size:.8rem;">
        <?= htmlspecialchars($selectedCourse['category'], ENT_QUOTES) ?>
      </span>
      <span class="a-badge a-badge--<?= $selectedCourse['is_active'] ? 'success' : 'warning' ?>" style="font-size:.8rem;">
        <?= $selectedCourse['is_active'] ? 'Active' : 'Inactive' ?>
      </span>
    <?php endif; ?>
  </div>
</div>

<?php if ($selectedCourseId === 0): ?>
<!-- No course selected state -->
<div class="a-card" style="text-align:center;padding:3.5rem 2rem;">
  <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
       stroke="var(--a-text-light)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
       style="margin-bottom:1rem;" aria-hidden="true">
    <polygon points="23 7 16 12 23 17 23 7"/>
    <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
  </svg>
  <h3 style="color:var(--a-text-muted);font-weight:600;margin:0 0 .5rem;">No Course Selected</h3>
  <p style="color:var(--a-text-light);margin:0;">Choose a course above to view and manage its content.</p>
</div>

<?php elseif (empty($modules)): ?>
<!-- Course selected but no modules -->
<div class="a-card" style="text-align:center;padding:3.5rem 2rem;">
  <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
       stroke="var(--a-text-light)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
       style="margin-bottom:1rem;" aria-hidden="true">
    <rect x="3" y="3" width="18" height="18" rx="2"/>
    <line x1="12" y1="8" x2="12" y2="16"/>
    <line x1="8" y1="12" x2="16" y2="12"/>
  </svg>
  <h3 style="color:var(--a-text-muted);font-weight:600;margin:0 0 .5rem;">No Modules Yet</h3>
  <p style="color:var(--a-text-light);margin:0 0 1.25rem;">
    This course has no modules. Click <strong>Add Module</strong> to get started.
  </p>
  <?php if ($canManage): ?>
  <button class="a-btn a-btn--primary" onclick="openModuleModal()">
    + Add First Module
  </button>
  <?php endif; ?>
</div>

<?php else: ?>
<!-- ============================================================
     Modules list
     ============================================================ -->
<div style="display:flex;flex-direction:column;gap:1.25rem;" id="modulesList">

  <?php foreach ($modules as $module): ?>
  <div class="a-card module-card" data-module-id="<?= (int)$module['id'] ?>">

    <!-- Module header -->
    <div class="a-card-header" style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;cursor:pointer;"
         onclick="toggleModule(<?= (int)$module['id'] ?>)">

      <!-- Drag hint icon -->
      <span title="Drag to reorder (update sort_order to reorder)" style="color:var(--a-text-light);cursor:grab;flex-shrink:0;" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="9"  cy="5"  r="1" fill="currentColor"/><circle cx="15" cy="5"  r="1" fill="currentColor"/>
          <circle cx="9"  cy="12" r="1" fill="currentColor"/><circle cx="15" cy="12" r="1" fill="currentColor"/>
          <circle cx="9"  cy="19" r="1" fill="currentColor"/><circle cx="15" cy="19" r="1" fill="currentColor"/>
        </svg>
      </span>

      <!-- Sort badge -->
      <span class="a-badge a-badge--info" style="font-size:.72rem;min-width:2rem;text-align:center;">
        #<?= (int)$module['sort_order'] ?>
      </span>

      <!-- Title -->
      <span style="font-weight:700;font-size:1rem;color:var(--a-text);flex:1;">
        <?= htmlspecialchars($module['title'], ENT_QUOTES) ?>
      </span>

      <!-- Lesson count -->
      <span class="a-badge a-badge--success" style="font-size:.72rem;">
        <?= count($module['lessons']) ?> lesson<?= count($module['lessons']) !== 1 ? 's' : '' ?>
      </span>

      <!-- Actions -->
      <?php if ($canManage): ?>
      <div style="display:flex;gap:.4rem;flex-shrink:0;" onclick="event.stopPropagation()">
        <button class="a-btn a-btn--ghost a-btn--sm"
                onclick="openModuleModal(<?= (int)$module['id'] ?>, <?= htmlspecialchars(json_encode($module['title']), ENT_QUOTES) ?>, <?= (int)$module['sort_order'] ?>)"
                title="Edit module">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 1 2-2v-7"/>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
          </svg>
          Edit
        </button>
        <button class="a-btn a-btn--ghost a-btn--sm"
                onclick="openLessonModal(<?= (int)$module['id'] ?>, null, null)"
                title="Add lesson to this module">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          Add Lesson
        </button>
        <button class="a-btn a-btn--danger a-btn--sm"
                onclick="confirmDeleteModule(<?= (int)$module['id'] ?>, <?= htmlspecialchars(json_encode($module['title']), ENT_QUOTES) ?>, <?= (int)$selectedCourseId ?>)"
                title="Delete module">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
            <path d="M10 11v6"/><path d="M14 11v6"/>
            <path d="M9 6V4h6v2"/>
          </svg>
          Delete
        </button>
      </div>
      <?php endif; ?>

      <!-- Chevron -->
      <span class="module-chevron" id="chevron-<?= (int)$module['id'] ?>"
            style="transition:transform .2s;flex-shrink:0;color:var(--a-text-muted);" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"/>
        </svg>
      </span>
    </div><!-- /module-header -->

    <!-- Lessons table -->
    <div class="module-body" id="module-body-<?= (int)$module['id'] ?>" style="display:block;">
      <?php if (empty($module['lessons'])): ?>
        <div style="padding:1.25rem 1.5rem;color:var(--a-text-light);font-size:.9rem;border-top:1px solid var(--a-border);">
          No lessons in this module yet.
          <?php if ($canManage): ?>
          <button class="a-btn a-btn--ghost a-btn--sm" style="margin-left:.5rem;"
                  onclick="openLessonModal(<?= (int)$module['id'] ?>, null, null)">
            + Add First Lesson
          </button>
          <?php endif; ?>
        </div>
      <?php else: ?>
      <div class="a-table-card" style="border-top:1px solid var(--a-border);border-radius:0 0 var(--a-radius-lg) var(--a-radius-lg);overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;">
          <thead>
            <tr style="background:var(--a-bg);border-bottom:1px solid var(--a-border);">
              <th style="padding:.6rem 1rem;text-align:left;font-size:.75rem;font-weight:600;color:var(--a-text-muted);text-transform:uppercase;letter-spacing:.05em;">#</th>
              <th style="padding:.6rem 1rem;text-align:left;font-size:.75rem;font-weight:600;color:var(--a-text-muted);text-transform:uppercase;letter-spacing:.05em;">Title</th>
              <th style="padding:.6rem 1rem;text-align:left;font-size:.75rem;font-weight:600;color:var(--a-text-muted);text-transform:uppercase;letter-spacing:.05em;">Type</th>
              <th style="padding:.6rem 1rem;text-align:left;font-size:.75rem;font-weight:600;color:var(--a-text-muted);text-transform:uppercase;letter-spacing:.05em;">Duration</th>
              <th style="padding:.6rem 1rem;text-align:left;font-size:.75rem;font-weight:600;color:var(--a-text-muted);text-transform:uppercase;letter-spacing:.05em;">Preview</th>
              <?php if ($canManage): ?>
              <th style="padding:.6rem 1rem;text-align:right;font-size:.75rem;font-weight:600;color:var(--a-text-muted);text-transform:uppercase;letter-spacing:.05em;">Actions</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($module['lessons'] as $lesson): ?>
            <tr style="border-bottom:1px solid var(--a-border);transition:background .15s;"
                onmouseover="this.style.background='var(--a-bg)'"
                onmouseout="this.style.background=''">
              <td style="padding:.75rem 1rem;font-size:.8rem;color:var(--a-text-muted);">
                <?= (int)$lesson['sort_order'] ?>
              </td>
              <td style="padding:.75rem 1rem;">
                <span style="font-weight:600;font-size:.9rem;color:var(--a-text);">
                  <?= htmlspecialchars($lesson['title'], ENT_QUOTES) ?>
                </span>
              </td>
              <td style="padding:.75rem 1rem;">
                <?php
                $typeColors = ['youtube' => 'a-badge--danger', 'vimeo' => 'a-badge--info', 'mp4' => 'a-badge--warning', 'gdrive' => 'a-badge--success', 'audio' => 'a-badge--info'];
                $typeBadge  = $typeColors[$lesson['video_type']] ?? 'a-badge--info';
                ?>
                <span class="a-badge <?= $typeBadge ?>" style="font-size:.72rem;text-transform:uppercase;">
                  <?= htmlspecialchars($lesson['video_type'], ENT_QUOTES) ?>
                </span>
              </td>
              <td style="padding:.75rem 1rem;font-size:.85rem;color:var(--a-text-sub);">
                <?= fmt_duration((int)$lesson['duration_seconds']) ?>
              </td>
              <td style="padding:.75rem 1rem;">
                <?php if ($lesson['is_preview']): ?>
                  <span class="a-badge a-badge--success" style="font-size:.72rem;">Free Preview</span>
                <?php else: ?>
                  <span style="font-size:.8rem;color:var(--a-text-light);">—</span>
                <?php endif; ?>
              </td>
              <?php if ($canManage): ?>
              <td style="padding:.75rem 1rem;text-align:right;white-space:nowrap;">
                <div style="display:inline-flex;gap:.35rem;">
                  <button class="a-btn a-btn--ghost a-btn--sm"
                          onclick="openTranscriptModal(<?= (int)$lesson['id'] ?>, <?= htmlspecialchars(json_encode($lesson['title']), ENT_QUOTES) ?>, <?= (int)$selectedCourseId ?>)"
                          title="Edit transcript">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                      <polyline points="14 2 14 8 20 8"/>
                      <line x1="16" y1="13" x2="8" y2="13"/>
                      <line x1="16" y1="17" x2="8" y2="17"/>
                      <polyline points="10 9 9 9 8 9"/>
                    </svg>
                    Transcript
                  </button>
                  <button class="a-btn a-btn--ghost a-btn--sm"
                          onclick="openLessonModal(<?= (int)$module['id'] ?>, <?= (int)$lesson['id'] ?>, <?= htmlspecialchars(json_encode($lesson), ENT_QUOTES) ?>)"
                          title="Edit lesson">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 1 2-2v-7"/>
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    Edit
                  </button>
                  <button class="a-btn a-btn--danger a-btn--sm"
                          onclick="confirmDeleteLesson(<?= (int)$lesson['id'] ?>, <?= htmlspecialchars(json_encode($lesson['title']), ENT_QUOTES) ?>, <?= (int)$selectedCourseId ?>)"
                          title="Delete lesson">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                      <path d="M10 11v6"/><path d="M14 11v6"/>
                      <path d="M9 6V4h6v2"/>
                    </svg>
                    Delete
                  </button>
                </div>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div><!-- /module-body -->

  </div><!-- /module-card -->
  <?php endforeach; ?>

</div><!-- /modulesList -->

<p style="margin-top:.75rem;font-size:.8rem;color:var(--a-text-light);text-align:center;">
  Tip: Update the <strong>Sort Order</strong> value when editing a module or lesson to reorder them.
</p>

<?php endif; // end modules display ?>


<!-- ============================================================
     MODALS
     ============================================================ -->
<?php if ($canManage): ?>

<!-- ── Module Modal ──────────────────────────────────────────── -->
<div class="a-modal-bg" id="moduleModalBg" onclick="closeModal('moduleModalBg')" role="dialog" aria-modal="true" aria-labelledby="moduleModalTitle" style="display:none;">
  <div class="a-modal" onclick="event.stopPropagation()">
    <div class="a-modal-header">
      <h3 class="a-modal-title" id="moduleModalTitle">Add Module</h3>
      <button class="a-modal-close" onclick="closeModal('moduleModalBg')" aria-label="Close">&times;</button>
    </div>
    <form method="post" action="<?= BASE ?>/admin/course-content.php<?= $selectedCourseId ? '?course_id=' . $selectedCourseId : '' ?>">
      <input type="hidden" name="action" value="save_module">
      <input type="hidden" name="course_id" value="<?= $selectedCourseId ?>">
      <input type="hidden" name="module_id" id="moduleModalId" value="">
      <div class="a-modal-body">
        <div class="a-form-grid" style="gap:1rem;">
          <div class="a-field" style="grid-column:1/-1;">
            <label class="a-label" for="moduleTitle">Module Title <span style="color:var(--a-danger)">*</span></label>
            <input class="a-input" type="text" id="moduleTitle" name="title" required
                   placeholder="e.g. Introduction to Python" maxlength="255">
          </div>
          <div class="a-field">
            <label class="a-label" for="moduleSortOrder">Sort Order</label>
            <input class="a-input" type="number" id="moduleSortOrder" name="sort_order"
                   value="<?= count($modules) + 1 ?>" min="0" max="9999">
            <span class="a-field-hint">Lower numbers appear first.</span>
          </div>
        </div>
      </div>
      <div class="a-modal-footer">
        <button type="button" class="a-btn a-btn--ghost" onclick="closeModal('moduleModalBg')">Cancel</button>
        <button type="submit" class="a-btn a-btn--primary" id="moduleModalSubmitBtn">Save Module</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Lesson Modal ──────────────────────────────────────────── -->
<div class="a-modal-bg" id="lessonModalBg" onclick="closeModal('lessonModalBg')" role="dialog" aria-modal="true" aria-labelledby="lessonModalTitle" style="display:none;">
  <div class="a-modal a-modal--lg" onclick="event.stopPropagation()">
    <div class="a-modal-header">
      <h3 class="a-modal-title" id="lessonModalTitle">Add Lesson</h3>
      <button class="a-modal-close" onclick="closeModal('lessonModalBg')" aria-label="Close">&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data" action="<?= BASE ?>/admin/course-content.php<?= $selectedCourseId ? '?course_id=' . $selectedCourseId : '' ?>">
      <input type="hidden" name="action" value="save_lesson">
      <input type="hidden" name="course_id" value="<?= $selectedCourseId ?>">
      <input type="hidden" name="module_id" id="lessonModuleId" value="">
      <input type="hidden" name="lesson_id" id="lessonModalId" value="">
      <div class="a-modal-body">
        <div class="a-form-grid" style="gap:1rem;">

          <div class="a-field" style="grid-column:1/-1;">
            <label class="a-label" for="lessonTitle">Lesson Title <span style="color:var(--a-danger)">*</span></label>
            <input class="a-input" type="text" id="lessonTitle" name="title" required
                   placeholder="e.g. Variables and Data Types" maxlength="255">
          </div>

          <div class="a-field" style="grid-column:1/-1;">
            <label class="a-label" for="lessonMedia">Upload Video or Audio <span style="color:var(--a-danger)">*</span></label>
            <div class="a-upload-box">
              <input class="a-upload-input" type="file" id="lessonMedia" name="lesson_media" accept="video/*,audio/*">
              <label for="lessonMedia" class="a-btn a-btn--primary a-btn--sm" id="lessonMediaBtn">Choose Media</label>
              <span class="a-upload-name" id="lessonMediaName">No file selected</span>
            </div>
            <span class="a-field-hint">New lessons require upload. For existing lessons, upload only if you want to replace current media.</span>
          </div>

          <div class="a-form-grid" style="grid-template-columns:1fr;grid-column:1/-1;">
            <label class="a-label">Subtitle File URL (VTT) <span style="color:var(--a-text-muted);font-weight:400;font-size:.8rem">— optional, for MP4 videos only</span></label>
            <input type="url" name="subtitle_url" id="inp_subtitle_url" class="a-input" placeholder="https://example.com/subtitles/lesson1.vtt">
          </div>

          <div class="a-field">
            <label class="a-label" for="lessonDuration">Duration (seconds)</label>
            <input class="a-input" type="number" id="lessonDuration" name="duration_seconds"
                   value="0" min="0" max="86400" readonly>
            <span class="a-field-hint">Automatically detected from uploaded media on save.</span>
          </div>

          <div class="a-field">
            <label class="a-label" for="lessonSortOrder">Sort Order</label>
            <input class="a-input" type="number" id="lessonSortOrder" name="sort_order"
                   value="1" min="0" max="9999">
            <span class="a-field-hint">Lower numbers appear first within the module.</span>
          </div>

          <div class="a-field" style="display:flex;align-items:center;gap:.65rem;padding-top:1.5rem;">
            <input type="checkbox" id="lessonIsPreview" name="is_preview" value="1"
                   style="width:1rem;height:1rem;accent-color:var(--a-primary);cursor:pointer;">
            <label for="lessonIsPreview" style="font-weight:600;cursor:pointer;color:var(--a-text-sub);">
              Free Preview
              <span style="font-weight:400;color:var(--a-text-muted);font-size:.85rem;"> — visible without enrollment</span>
            </label>
          </div>

        </div>
      </div>
      <div class="a-modal-footer">
        <button type="button" class="a-btn a-btn--ghost" onclick="closeModal('lessonModalBg')">Cancel</button>
        <button type="submit" class="a-btn a-btn--primary" id="lessonModalSubmitBtn">Save Lesson</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Transcript Modal ──────────────────────────────────────── -->
<div class="a-modal-bg" id="transcriptModalBg" onclick="closeModal('transcriptModalBg')" role="dialog" aria-modal="true" aria-labelledby="transcriptModalTitle" style="display:none;">
  <div class="a-modal a-modal--xl" onclick="event.stopPropagation()">
    <div class="a-modal-header">
      <h3 class="a-modal-title" id="transcriptModalTitle">Edit Transcript</h3>
      <button class="a-modal-close" onclick="closeModal('transcriptModalBg')" aria-label="Close">&times;</button>
    </div>
    <form method="post" action="<?= BASE ?>/admin/course-content.php<?= $selectedCourseId ? '?course_id=' . $selectedCourseId : '' ?>">
      <input type="hidden" name="action" value="save_transcript">
      <input type="hidden" name="course_id" value="<?= $selectedCourseId ?>">
      <input type="hidden" name="lesson_id" id="transcriptLessonId" value="">
      <div class="a-modal-body" style="padding-bottom:.5rem;">
        <p id="transcriptLessonName" style="font-weight:600;color:var(--a-text-sub);margin-bottom:.75rem;font-size:.9rem;"></p>
        <textarea class="a-input" id="transcriptContent" name="content" rows="16"
                  placeholder="Paste or type the lesson transcript here…"
                  style="font-family:'Courier New',Courier,monospace;font-size:.85rem;line-height:1.65;resize:vertical;width:100%;"></textarea>
        <p style="font-size:.78rem;color:var(--a-text-light);margin-top:.4rem;">
          Plain text or Markdown. Displayed to learners beneath the video player.
        </p>
      </div>
      <div class="a-modal-footer">
        <button type="button" class="a-btn a-btn--ghost" onclick="closeModal('transcriptModalBg')">Cancel</button>
        <button type="submit" class="a-btn a-btn--primary">Save Transcript</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Delete confirmation modal ─────────────────────────────── -->
<div class="a-modal-bg" id="deleteModalBg" onclick="closeModal('deleteModalBg')" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle" style="display:none;">
  <div class="a-modal a-modal--sm" onclick="event.stopPropagation()">
    <div class="a-modal-header">
      <h3 class="a-modal-title" id="deleteModalTitle" style="color:var(--a-danger);">Confirm Delete</h3>
      <button class="a-modal-close" onclick="closeModal('deleteModalBg')" aria-label="Close">&times;</button>
    </div>
    <div class="a-modal-body">
      <div style="display:flex;align-items:flex-start;gap:1rem;">
        <span style="color:var(--a-danger);flex-shrink:0;margin-top:.1rem;" aria-hidden="true">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
          </svg>
        </span>
        <div>
          <p id="deleteModalMessage" style="margin:0 0 .4rem;font-weight:600;color:var(--a-text);">
            Are you sure?
          </p>
          <p style="margin:0;font-size:.88rem;color:var(--a-text-muted);" id="deleteModalSubMessage">
            This action cannot be undone.
          </p>
        </div>
      </div>
    </div>
    <form method="post" action="<?= BASE ?>/admin/course-content.php" id="deleteForm">
      <input type="hidden" name="action" id="deleteAction" value="">
      <input type="hidden" name="course_id" id="deleteCourseId" value="<?= $selectedCourseId ?>">
      <input type="hidden" name="module_id" id="deleteModuleId" value="">
      <input type="hidden" name="lesson_id" id="deleteLessonId" value="">
      <div class="a-modal-footer">
        <button type="button" class="a-btn a-btn--ghost" onclick="closeModal('deleteModalBg')">Cancel</button>
        <button type="submit" class="a-btn a-btn--danger">Yes, Delete</button>
      </div>
    </form>
  </div>
</div>

<?php endif; // canManage ?>


<!-- ============================================================
     Inline styles for modal & form elements
     ============================================================ -->
<style>
/* Modal overlay */
.a-modal-bg {
  position: fixed;
  inset: 0;
  background: rgba(17,24,39,.55);
  backdrop-filter: blur(3px);
  z-index: 900;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  animation: fadeIn .15s ease;
}
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }

/* Modal box */
.a-modal {
  background: #fff;
  border-radius: var(--a-radius-lg);
  box-shadow: 0 20px 60px rgba(0,0,0,.2);
  width: 100%;
  max-width: 520px;
  max-height: 90vh;
  overflow-y: auto;
  animation: slideUp .18s ease;
}
.a-modal--lg  { max-width: 640px; }
.a-modal--xl  { max-width: 760px; }
.a-modal--sm  { max-width: 420px; }
@keyframes slideUp { from { transform:translateY(12px); opacity:0; } to { transform:translateY(0); opacity:1; } }

.a-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--a-border);
}
.a-modal-title { margin:0; font-size:1.05rem; font-weight:700; }
.a-modal-close {
  background: none; border: none; cursor: pointer;
  font-size: 1.5rem; line-height: 1; color: var(--a-text-muted);
  padding: .1rem .4rem; border-radius: var(--a-radius);
  transition: background var(--a-transition), color var(--a-transition);
}
.a-modal-close:hover { background: var(--a-bg); color: var(--a-text); }

.a-modal-body   { padding: 1.5rem; }
.a-modal-footer {
  display: flex; justify-content: flex-end; gap: .6rem;
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--a-border);
  background: var(--a-bg);
  border-radius: 0 0 var(--a-radius-lg) var(--a-radius-lg);
}

/* Form */
.a-form-grid { display: grid; grid-template-columns: 1fr 1fr; }
.a-field { display: flex; flex-direction: column; gap: .35rem; }
.a-label { font-size: .85rem; font-weight: 600; color: var(--a-text-sub); }
.a-field-hint { font-size: .77rem; color: var(--a-text-light); margin-top: .1rem; }
.a-input {
  padding: .5rem .75rem;
  border: 1px solid var(--a-border);
  border-radius: var(--a-radius);
  font-size: .9rem;
  color: var(--a-text);
  background: #fff;
  transition: border-color var(--a-transition), box-shadow var(--a-transition);
  width: 100%;
  outline: none;
}
.a-input:focus {
  border-color: var(--a-primary);
  box-shadow: 0 0 0 3px rgba(99,102,241,.15);
}
textarea.a-input { padding: .6rem .75rem; }

/* Page header */
.a-page-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;
}
.a-page-title { margin: 0; font-size: 1.6rem; font-weight: 800; color: var(--a-text); }
.a-page-sub   { margin: .2rem 0 0; color: var(--a-text-muted); font-size: .9rem; }

/* Card header */
.a-card-header {
  padding: 1rem 1.25rem;
  border-radius: var(--a-radius-lg) var(--a-radius-lg) 0 0;
}
.a-card-header:hover { background: var(--a-bg); }

/* Alert */
.a-alert {
  padding: .75rem 1rem;
  border-radius: var(--a-radius);
  font-size: .9rem;
  font-weight: 500;
}
.a-alert--success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.a-alert--danger  { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

/* Badge colors */
.a-badge--danger  { background: #fef2f2; color: #991b1b; }
.a-badge--warning { background: #fffbeb; color: #92400e; }

/* Module card */
.module-card { overflow: hidden; }

/* Upload field styling */
.a-upload-box {
  border: 1px dashed var(--a-border);
  border-radius: var(--a-radius);
  background: var(--a-bg);
  padding: .75rem;
  display: flex;
  align-items: center;
  gap: .65rem;
  flex-wrap: wrap;
}

.a-upload-input {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  border: 0;
}

.a-upload-name {
  font-size: .84rem;
  color: var(--a-text-sub);
}

@media (max-width: 640px) {
  .a-form-grid { grid-template-columns: 1fr; }
  .a-modal-header, .a-modal-body, .a-modal-footer { padding-left: 1rem; padding-right: 1rem; }
}
</style>


<!-- ============================================================
     JavaScript
     ============================================================ -->
<script>
// ── Accordion ────────────────────────────────────────────────
function toggleModule(id) {
  const body    = document.getElementById('module-body-' + id);
  const chevron = document.getElementById('chevron-' + id);
  if (!body) return;
  const hidden = body.style.display === 'none';
  body.style.display    = hidden ? 'block' : 'none';
  chevron.style.transform = hidden ? '' : 'rotate(-90deg)';
}

// ── Generic modal helpers ────────────────────────────────────
function openModal(id) {
  const el = document.getElementById(id);
  if (el) { el.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) { el.style.display = 'none'; document.body.style.overflow = ''; }
}
// Close on Escape
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    ['moduleModalBg','lessonModalBg','transcriptModalBg','deleteModalBg'].forEach(closeModal);
  }
});

// ── Module modal ─────────────────────────────────────────────
function openModuleModal(moduleId, title, sortOrder) {
  const isEdit = (moduleId !== undefined && moduleId !== null);
  document.getElementById('moduleModalTitle').textContent  = isEdit ? 'Edit Module' : 'Add Module';
  document.getElementById('moduleModalSubmitBtn').textContent = isEdit ? 'Update Module' : 'Save Module';
  document.getElementById('moduleModalId').value           = isEdit ? moduleId : '';
  document.getElementById('moduleTitle').value             = isEdit ? title : '';
  document.getElementById('moduleSortOrder').value         = isEdit ? sortOrder : (<?= count($modules) + 1 ?>);
  openModal('moduleModalBg');
  setTimeout(() => document.getElementById('moduleTitle').focus(), 80);
}

// ── Lesson modal ─────────────────────────────────────────────
function openLessonModal(moduleId, lessonId, lessonData) {
  const isEdit = (lessonId !== null && lessonId !== undefined);
  document.getElementById('lessonModalTitle').textContent    = isEdit ? 'Edit Lesson' : 'Add Lesson';
  document.getElementById('lessonModalSubmitBtn').textContent = isEdit ? 'Update Lesson' : 'Save Lesson';
  document.getElementById('lessonModuleId').value            = moduleId;
  document.getElementById('lessonModalId').value             = isEdit ? lessonId : '';

  if (isEdit && lessonData) {
    document.getElementById('lessonTitle').value       = lessonData.title     || '';
    document.getElementById('lessonDuration').value    = lessonData.duration_seconds || 0;
    document.getElementById('lessonIsPreview').checked = lessonData.is_preview ? true : false;
    document.getElementById('lessonSortOrder').value   = lessonData.sort_order || 1;
    document.getElementById('inp_subtitle_url').value  = lessonData.subtitle_url || '';
  } else {
    document.getElementById('lessonTitle').value       = '';
    document.getElementById('lessonDuration').value    = 0;
    document.getElementById('lessonIsPreview').checked = false;
    document.getElementById('lessonSortOrder').value   = 1;
    document.getElementById('inp_subtitle_url').value  = '';
  }
  var mediaInput = document.getElementById('lessonMedia');
  if (mediaInput) mediaInput.value = '';
  var mediaName = document.getElementById('lessonMediaName');
  if (mediaName) mediaName.textContent = isEdit ? 'Keep current media (choose file to replace)' : 'No file selected';
  openModal('lessonModalBg');
  setTimeout(() => document.getElementById('lessonTitle').focus(), 80);
}

document.addEventListener('DOMContentLoaded', function () {
  var mediaEl = document.getElementById('lessonMedia');
  var mediaName = document.getElementById('lessonMediaName');
  if (!mediaEl || !mediaName) return;

  mediaEl.addEventListener('change', function () {
    mediaName.textContent = this.files && this.files[0] ? this.files[0].name : 'No file selected';
  });
});

// ── Transcript modal ─────────────────────────────────────────
function openTranscriptModal(lessonId, lessonTitle, courseId) {
  document.getElementById('transcriptLessonId').value      = lessonId;
  document.getElementById('transcriptLessonName').textContent = 'Lesson: ' + lessonTitle;
  document.getElementById('transcriptContent').value       = '';

  // Fetch existing transcript via a quick inline approach
  fetch('<?= BASE ?>/api/get-transcript.php?lesson_id=' + encodeURIComponent(lessonId))
    .then(r => r.ok ? r.json() : null)
    .then(data => {
      if (data && data.content !== undefined) {
        document.getElementById('transcriptContent').value = data.content;
      }
    })
    .catch(() => {});  // silently ignore if API not yet wired

  openModal('transcriptModalBg');
  setTimeout(() => document.getElementById('transcriptContent').focus(), 80);
}

// ── Delete confirmations ─────────────────────────────────────
function confirmDeleteModule(moduleId, title, courseId) {
  document.getElementById('deleteModalTitle').textContent   = 'Delete Module';
  document.getElementById('deleteModalMessage').textContent = 'Delete "' + title + '"?';
  document.getElementById('deleteModalSubMessage').textContent =
    'All lessons, transcripts, and progress inside this module will also be deleted. This cannot be undone.';
  document.getElementById('deleteAction').value    = 'delete_module';
  document.getElementById('deleteModuleId').value  = moduleId;
  document.getElementById('deleteLessonId').value  = '';
  document.getElementById('deleteCourseId').value  = courseId;
  openModal('deleteModalBg');
}

function confirmDeleteLesson(lessonId, title, courseId) {
  document.getElementById('deleteModalTitle').textContent   = 'Delete Lesson';
  document.getElementById('deleteModalMessage').textContent = 'Delete "' + title + '"?';
  document.getElementById('deleteModalSubMessage').textContent =
    'The transcript and all learner progress for this lesson will also be removed. This cannot be undone.';
  document.getElementById('deleteAction').value    = 'delete_lesson';
  document.getElementById('deleteLessonId').value  = lessonId;
  document.getElementById('deleteModuleId').value  = '';
  document.getElementById('deleteCourseId').value  = courseId;
  openModal('deleteModalBg');
}

</script>

<?php require_once __DIR__ . '/_foot.php'; ?>
