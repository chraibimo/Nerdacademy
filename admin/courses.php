<?php
if (!defined('BASE')) define('BASE', '');
$admin_active_page = 'courses';
$admin_page_title  = 'Courses';
require_once __DIR__ . '/_head.php';
require_once __DIR__ . '/../includes/courses-repo.php';
ensure_courses_catalog_table($mysqli);

$message     = '';
$messageType = 'success';

function csv_value(array $row, array $headerMap, array $names, string $default = ''): string
{
  foreach ($names as $name) {
    $key = strtolower(trim($name));
    if (isset($headerMap[$key])) {
      $idx = (int)$headerMap[$key];
      return trim((string)($row[$idx] ?? ''));
    }
  }
  return $default;
}

function csv_slugify(string $value): string
{
  $slug = strtolower(trim($value));
  $slug = (string)preg_replace('/[^a-z0-9]+/', '-', $slug);
  return trim($slug, '-');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && auth_has_permission($user, 'manage_courses')) {
    $action = $_POST['action'] ?? '';

  /* ── import_csv ───────────────────────────────────────────────────── */
  if ($action === 'import_csv') {
    $csvFile = $_FILES['courses_csv'] ?? null;
    $tmpPath = (string)($csvFile['tmp_name'] ?? '');
    $error   = (int)($csvFile['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error !== UPLOAD_ERR_OK || $tmpPath === '' || !is_uploaded_file($tmpPath)) {
      $message = 'Please choose a valid CSV file to import.';
      $messageType = 'error';
    } else {
      $handle = @fopen($tmpPath, 'rb');
      if ($handle === false) {
        $message = 'Unable to read uploaded CSV file.';
        $messageType = 'error';
      } else {
        $header = fgetcsv($handle);
        if (!is_array($header) || empty($header)) {
          $message = 'CSV file is empty or missing a header row.';
          $messageType = 'error';
        } else {
          $headerMap = [];
          foreach ($header as $i => $col) {
            $headerMap[strtolower(trim((string)$col))] = $i;
          }

          $insertStmt = $mysqli->prepare('INSERT INTO courses_catalog (slug,title,subtitle,category,level,duration,lessons,students,rating,reviews,price,old_price,instructor,instructor_title,color,icon,badge,image_url,description,tags_json,outcomes_json,curriculum_json,sort_order,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,9999,?)');
          $updateStmt = $mysqli->prepare('UPDATE courses_catalog SET title=?,subtitle=?,category=?,level=?,duration=?,lessons=?,students=?,rating=?,reviews=?,price=?,old_price=?,instructor=?,instructor_title=?,color=?,icon=?,badge=?,image_url=?,description=?,tags_json=?,outcomes_json=?,curriculum_json=?,is_active=?,updated_at=NOW() WHERE slug=?');

          if (!$insertStmt || !$updateStmt) {
            if ($insertStmt) $insertStmt->close();
            if ($updateStmt) $updateStmt->close();
            $message = 'Database prepare failed while importing CSV.';
            $messageType = 'error';
          } else {
            $imported = 0;
            $updated  = 0;
            $skipped  = 0;

            while (($row = fgetcsv($handle)) !== false) {
              $title = csv_value($row, $headerMap, ['title', 'name']);
              if ($title === '') {
                $skipped++;
                continue;
              }

              $slug = csv_value($row, $headerMap, ['slug']);
              if ($slug === '') {
                $slug = csv_slugify($title);
              }
              if ($slug === '') {
                $skipped++;
                continue;
              }

              $subtitle        = csv_value($row, $headerMap, ['subtitle'], '');
              $category        = csv_value($row, $headerMap, ['category'], 'General');
              $level           = csv_value($row, $headerMap, ['level'], 'Beginner');
              $duration        = csv_value($row, $headerMap, ['duration'], '');
              $lessons         = max(0, (int)csv_value($row, $headerMap, ['lessons'], '0'));
              $students        = max(0, (int)csv_value($row, $headerMap, ['students'], '0'));
              $rating          = max(0.0, min(5.0, (float)csv_value($row, $headerMap, ['rating'], '0')));
              $reviews         = max(0, (int)csv_value($row, $headerMap, ['reviews'], '0'));
              $price           = max(0.0, (float)csv_value($row, $headerMap, ['price'], '0'));
              $oldPrice        = max($price, (float)csv_value($row, $headerMap, ['old_price'], '0'));
              $instructor      = csv_value($row, $headerMap, ['instructor'], '');
              $instructorTitle = csv_value($row, $headerMap, ['instructor_title'], '');
              $color           = csv_value($row, $headerMap, ['color'], '#6366f1');
              $icon            = csv_value($row, $headerMap, ['icon'], 'brain');
              $badge           = csv_value($row, $headerMap, ['badge'], '');
              $imageUrl        = csv_value($row, $headerMap, ['image_url'], '');
              $description     = csv_value($row, $headerMap, ['description'], 'Imported from CSV');
              $isActive        = in_array(strtolower(csv_value($row, $headerMap, ['is_active', 'active'], '1')), ['1', 'true', 'yes', 'y'], true) ? 1 : 0;

              $tagsCsv = csv_value($row, $headerMap, ['tags_csv', 'tags'], '');
              $tags = array_values(array_filter(array_map('trim', explode(',', $tagsCsv)), static fn($v) => $v !== ''));

              $outcomesRaw = csv_value($row, $headerMap, ['outcomes_text', 'outcomes'], '');
              $outcomes = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|\|/', $outcomesRaw) ?: []), static fn($v) => $v !== ''));

              $curriculumRaw = csv_value($row, $headerMap, ['curriculum_text', 'curriculum'], '');
              $curriculum = [];
              foreach ((preg_split('/\r\n|\r|\n/', $curriculumRaw) ?: []) as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $parts = array_map('trim', explode('|', $line));
                $curriculum[] = ['title' => $parts[0] ?? 'Module', 'lessons' => max(1, (int)($parts[1] ?? 1))];
              }

              $tagsJson = json_encode($tags, JSON_UNESCAPED_UNICODE);
              $outcomesJson = json_encode($outcomes, JSON_UNESCAPED_UNICODE);
              $curriculumJson = json_encode($curriculum, JSON_UNESCAPED_UNICODE);

              $existsStmt = $mysqli->prepare('SELECT id FROM courses_catalog WHERE slug = ? LIMIT 1');
              $existingId = 0;
              if ($existsStmt) {
                $existsStmt->bind_param('s', $slug);
                $existsStmt->execute();
                $existingRow = $existsStmt->get_result()->fetch_assoc();
                $existingId = (int)($existingRow['id'] ?? 0);
                $existsStmt->close();
              }

              if ($existingId > 0) {
                $updateStmt->bind_param('sssssiididdssssssssssis',
                  $title,$subtitle,$category,$level,$duration,
                  $lessons,$students,$rating,$reviews,$price,$oldPrice,
                  $instructor,$instructorTitle,$color,$icon,$badge,$imageUrl,$description,
                  $tagsJson,$outcomesJson,$curriculumJson,$isActive,$slug
                );
                if ($updateStmt->execute()) {
                  $updated++;
                } else {
                  $skipped++;
                }
              } else {
                $insertStmt->bind_param('ssssssiididdssssssssssi',
                  $slug,$title,$subtitle,$category,$level,$duration,
                  $lessons,$students,$rating,$reviews,$price,$oldPrice,
                  $instructor,$instructorTitle,$color,$icon,$badge,$imageUrl,$description,
                  $tagsJson,$outcomesJson,$curriculumJson,$isActive
                );
                if ($insertStmt->execute()) {
                  $imported++;
                } else {
                  $skipped++;
                }
              }
            }

            $insertStmt->close();
            $updateStmt->close();
            $message = 'CSV import complete: ' . $imported . ' added, ' . $updated . ' updated, ' . $skipped . ' skipped.';
            $messageType = 'success';
          }
        }
        fclose($handle);
      }
    }
  }

    /* ── save_course ──────────────────────────────────────────────────── */
    if ($action === 'save_course') {
        $courseId        = (int)($_POST['course_id'] ?? 0);
        $title           = trim($_POST['title'] ?? '');
        $subtitle        = trim($_POST['subtitle'] ?? '');
        $category        = trim($_POST['category'] ?? 'General');
        $level           = trim($_POST['level'] ?? 'Beginner');
        $duration        = trim($_POST['duration'] ?? '');
        $lessons         = max(0, (int)($_POST['lessons'] ?? 0));
        $students        = max(0, (int)($_POST['students'] ?? 0));
        $rating          = max(0.0, min(5.0, (float)($_POST['rating'] ?? 0)));
        $reviews         = max(0, (int)($_POST['reviews'] ?? 0));
        $price           = max(0.0, (float)($_POST['price'] ?? 0));
        $oldPrice        = max($price, (float)($_POST['old_price'] ?? 0));
        $instructor      = trim($_POST['instructor'] ?? '');
        $instructorTitle = trim($_POST['instructor_title'] ?? '');
        $color           = trim($_POST['color'] ?? '#6366f1');
        $icon            = trim($_POST['icon'] ?? 'brain');
        $badge           = trim($_POST['badge'] ?? '');
        $imageUrl        = trim($_POST['image_url'] ?? '');
        $description     = trim($_POST['description'] ?? '');
        $isActive        = isset($_POST['is_active']) ? 1 : 0;

        $tagsCsv    = trim($_POST['tags_csv'] ?? '');
        $tags       = array_values(array_filter(array_map('trim', explode(',', $tagsCsv)), static fn($v) => $v !== ''));

        $outcomesRaw = trim($_POST['outcomes_text'] ?? '');
        $outcomes    = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $outcomesRaw) ?: []), static fn($v) => $v !== ''));

        $curriculumRaw = trim($_POST['curriculum_text'] ?? '');
        $curriculum    = [];
        foreach ((preg_split('/\r\n|\r|\n/', $curriculumRaw) ?: []) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts      = array_map('trim', explode('|', $line));
            $curriculum[] = ['title' => $parts[0] ?? 'Module', 'lessons' => max(1, (int)($parts[1] ?? 1))];
        }

        $slug = trim($_POST['slug'] ?? '');
        if ($slug === '') {
            $slug = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-');
        }

        $tagsJson       = json_encode($tags,       JSON_UNESCAPED_UNICODE);
        $outcomesJson   = json_encode($outcomes,   JSON_UNESCAPED_UNICODE);
        $curriculumJson = json_encode($curriculum, JSON_UNESCAPED_UNICODE);

        if ($title === '' || $description === '') {
            $message     = 'Title and description are required.';
            $messageType = 'error';
        } elseif ($courseId > 0) {
            $stmt = $mysqli->prepare('UPDATE courses_catalog SET slug=?,title=?,subtitle=?,category=?,level=?,duration=?,lessons=?,students=?,rating=?,reviews=?,price=?,old_price=?,instructor=?,instructor_title=?,color=?,icon=?,badge=?,image_url=?,description=?,tags_json=?,outcomes_json=?,curriculum_json=?,is_active=?,updated_at=NOW() WHERE id=?');
            if ($stmt) {
              $stmt->bind_param('ssssssiididdssssssssssii',
                    $slug,$title,$subtitle,$category,$level,$duration,
                    $lessons,$students,$rating,$reviews,$price,$oldPrice,
                $instructor,$instructorTitle,$color,$icon,$badge,$imageUrl,$description,
                    $tagsJson,$outcomesJson,$curriculumJson,$isActive,$courseId);
                $message     = $stmt->execute() ? 'Course updated successfully.' : 'Update failed: ' . $stmt->error;
                $messageType = $stmt->error ? 'error' : 'success';
                $stmt->close();
            }
        } else {
            $stmt = $mysqli->prepare('INSERT INTO courses_catalog (slug,title,subtitle,category,level,duration,lessons,students,rating,reviews,price,old_price,instructor,instructor_title,color,icon,badge,image_url,description,tags_json,outcomes_json,curriculum_json,sort_order,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,9999,?)');
            if ($stmt) {
              $stmt->bind_param('ssssssiididdssssssssssi',
                    $slug,$title,$subtitle,$category,$level,$duration,
                    $lessons,$students,$rating,$reviews,$price,$oldPrice,
                $instructor,$instructorTitle,$color,$icon,$badge,$imageUrl,$description,
                    $tagsJson,$outcomesJson,$curriculumJson,$isActive);
                $message     = $stmt->execute() ? 'Course created successfully.' : 'Create failed: ' . $stmt->error;
                $messageType = $stmt->error ? 'error' : 'success';
                $stmt->close();
            }
        }
    }

    /* ── delete_course ───────────────────────────────────────────────── */
    if ($action === 'delete_course') {
        $courseId = (int)($_POST['course_id'] ?? 0);
        if ($courseId > 0) {
            $stmt = $mysqli->prepare('DELETE FROM courses_catalog WHERE id=?');
            if ($stmt) { $stmt->bind_param('i', $courseId); $stmt->execute(); $stmt->close(); }
            $message = 'Course deleted.';
        }
    }

    /* ── toggle_active ───────────────────────────────────────────────── */
    if ($action === 'toggle_active') {
        $courseId = (int)($_POST['course_id'] ?? 0);
        $newVal   = (int)($_POST['new_val'] ?? 0);
        $stmt     = $mysqli->prepare('UPDATE courses_catalog SET is_active=? WHERE id=?');
        if ($stmt) { $stmt->bind_param('ii', $newVal, $courseId); $stmt->execute(); $stmt->close(); }
        $message = 'Course status updated.';
    }
}

/* ── Load course list ────────────────────────────────────────────────────── */
$allCourses = [];
$r = $mysqli->query('SELECT id,slug,title,subtitle,category,level,price,old_price,students,rating,is_active,created_at FROM courses_catalog ORDER BY sort_order, id DESC');
if ($r) while ($row = $r->fetch_assoc()) $allCourses[] = $row;

/* ── Stats ───────────────────────────────────────────────────────────────── */
$totalCourses   = count($allCourses);
$activeCourses  = count(array_filter($allCourses, fn($c) => (int)$c['is_active'] === 1));
$totalStudents  = array_sum(array_column($allCourses, 'students'));

/* ── Load edit course if ?edit=ID ────────────────────────────────────────── */
$editCourseId = (int)($_GET['edit'] ?? 0);
$editCourse   = null;
if ($editCourseId > 0) {
    $stmt = $mysqli->prepare('SELECT * FROM courses_catalog WHERE id=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $editCourseId);
        $stmt->execute();
        $editCourse = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

$showForm = isset($_GET['new']) || $editCourse !== null;

/* ── Pre-decode JSON fields for the form ─────────────────────────────────── */
$formTagsCsv    = '';
$formOutcomes   = '';
$formCurriculum = '';
if ($editCourse) {
    $decodedTags = json_decode($editCourse['tags_json'] ?? '[]', true);
    $formTagsCsv = is_array($decodedTags) ? implode(', ', $decodedTags) : '';

    $decodedOutcomes = json_decode($editCourse['outcomes_json'] ?? '[]', true);
    $formOutcomes    = is_array($decodedOutcomes) ? implode("\n", $decodedOutcomes) : '';

    $decodedCurriculum = json_decode($editCourse['curriculum_json'] ?? '[]', true);
    if (is_array($decodedCurriculum)) {
        $formCurriculum = implode("\n", array_map(
            fn($m) => (($m['title'] ?? 'Module') . '|' . (int)($m['lessons'] ?? 1)),
            $decodedCurriculum
        ));
    }
}

$fv = static fn(string $field, string $default = '') =>
    htmlspecialchars((string)($editCourse[$field] ?? $default), ENT_QUOTES);
?>

<!-- ── Page header ──────────────────────────────────────────────────────── -->
<div class="a-page-header">
  <div class="a-page-header-text">
    <h1>Courses</h1>
    <p>Manage your course catalog</p>
  </div>
  <div class="a-page-actions">
    <?php if (auth_has_permission($user, 'manage_courses')): ?>
      <form method="POST" action="<?= BASE ?>/admin/courses.php" enctype="multipart/form-data" style="display:flex;gap:.5rem;align-items:center;">
        <input type="hidden" name="action" value="import_csv">
        <input type="file" name="courses_csv" accept=".csv,text/csv" required style="font-size:.78rem;max-width:220px;">
        <button type="submit" class="a-btn a-btn--ghost">Import CSV</button>
      </form>
    <?php endif; ?>
    <?php if ($showForm && !$editCourse): ?>
      <a href="<?= BASE ?>/admin/courses.php" class="a-btn a-btn--ghost">Cancel</a>
    <?php else: ?>
      <a href="<?= BASE ?>/admin/courses.php?new=1" class="a-btn a-btn--primary">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Add Course
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if ($message !== ''): ?>
<div style="padding:.85rem 1.1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.875rem;font-weight:500;
     background:<?= $messageType === 'error' ? '#fef2f2' : '#ecfdf5' ?>;
     color:<?= $messageType === 'error' ? '#b91c1c' : '#15803d' ?>;
     border:1px solid <?= $messageType === 'error' ? '#fecaca' : '#bbf7d0' ?>;">
  <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['published'])): ?>
<div style="padding:.85rem 1.1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.875rem;font-weight:500;background:#ecfdf5;color:#15803d;border:1px solid #bbf7d0;">
  Course published to catalog from AI Agent.
</div>
<?php endif; ?>

<!-- ── Stats ─────────────────────────────────────────────────────────────── -->
<div class="a-stats-grid" style="grid-template-columns:repeat(3,1fr)">

  <div class="a-stat-card">
    <div class="a-stat-icon a-stat-icon--indigo">
      <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
      </svg>
    </div>
    <div class="a-stat-body">
      <div class="a-stat-label">Total Courses</div>
      <div class="a-stat-value"><?= $totalCourses ?></div>
    </div>
  </div>

  <div class="a-stat-card">
    <div class="a-stat-icon a-stat-icon--green">
      <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
    </div>
    <div class="a-stat-body">
      <div class="a-stat-label">Active</div>
      <div class="a-stat-value"><?= $activeCourses ?></div>
    </div>
  </div>

  <div class="a-stat-card">
    <div class="a-stat-icon a-stat-icon--blue">
      <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
      </svg>
    </div>
    <div class="a-stat-body">
      <div class="a-stat-label">Total Students</div>
      <div class="a-stat-value"><?= number_format($totalStudents) ?></div>
    </div>
  </div>

</div>

<!-- ── Course Form ────────────────────────────────────────────────────────── -->
<?php if ($showForm): ?>
<?php if (!auth_has_permission($user, 'manage_courses')): ?>
<div class="a-card" style="padding:1.5rem;color:var(--a-text-muted)">You do not have permission to manage courses.</div>
<?php else: ?>
<div class="a-card" style="margin-bottom:1.5rem">
  <div class="a-card-head">
    <h3><?= $editCourse ? 'Edit Course' : 'Add Course' ?></h3>
    <a href="<?= BASE ?>/admin/courses.php" class="a-btn a-btn--ghost a-btn--sm">Cancel</a>
  </div>
  <div class="a-card-body">
    <form method="POST" action="<?= BASE ?>/admin/courses.php">
      <input type="hidden" name="action"    value="save_course">
      <input type="hidden" name="course_id" value="<?= $editCourse ? (int)$editCourse['id'] : 0 ?>">

      <div class="a-form-grid">

        <!-- Row 1: Title + Slug -->
        <div class="a-form-group">
          <label for="cf_title">Title <span style="color:var(--a-danger)">*</span></label>
          <input id="cf_title" type="text" name="title" required
                 value="<?= $fv('title') ?>" placeholder="e.g. Machine Learning Fundamentals">
        </div>
        <div class="a-form-group">
          <label for="cf_slug">Slug</label>
          <input id="cf_slug" type="text" name="slug"
                 value="<?= $fv('slug') ?>" placeholder="auto-generated if blank">
        </div>

        <!-- Subtitle: full width -->
        <div class="a-form-group full">
          <label for="cf_subtitle">Subtitle</label>
          <input id="cf_subtitle" type="text" name="subtitle"
                 value="<?= $fv('subtitle') ?>" placeholder="Short compelling summary">
        </div>

        <!-- Category + Level -->
        <div class="a-form-group">
          <label for="cf_category">Category</label>
          <select id="cf_category" name="category">
            <?php
            $cats = ['Machine Learning','Deep Learning','NLP','Computer Vision','Data Science','Programming','AI Ethics','General'];
            $curCat = $editCourse['category'] ?? 'General';
            foreach ($cats as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>" <?= $curCat === $cat ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="a-form-group">
          <label for="cf_level">Level</label>
          <select id="cf_level" name="level">
            <?php foreach (['Beginner','Intermediate','Advanced'] as $lvl): ?>
            <option value="<?= $lvl ?>" <?= ($editCourse['level'] ?? 'Beginner') === $lvl ? 'selected' : '' ?>><?= $lvl ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Duration + Lessons -->
        <div class="a-form-group">
          <label for="cf_duration">Duration</label>
          <input id="cf_duration" type="text" name="duration"
                 value="<?= $fv('duration') ?>" placeholder="e.g. 8 weeks">
        </div>
        <div class="a-form-group">
          <label for="cf_lessons">Lessons</label>
          <input id="cf_lessons" type="number" name="lessons" min="0"
                 value="<?= (int)($editCourse['lessons'] ?? 0) ?>">
        </div>

        <!-- Students + Rating -->
        <div class="a-form-group">
          <label for="cf_students">Students</label>
          <input id="cf_students" type="number" name="students" min="0"
                 value="<?= (int)($editCourse['students'] ?? 0) ?>">
        </div>
        <div class="a-form-group">
          <label for="cf_rating">Rating (0–5)</label>
          <input id="cf_rating" type="number" name="rating" min="0" max="5" step="0.1"
                 value="<?= htmlspecialchars((string)($editCourse['rating'] ?? '0'), ENT_QUOTES) ?>">
        </div>

        <!-- Reviews + Price -->
        <div class="a-form-group">
          <label for="cf_reviews">Reviews</label>
          <input id="cf_reviews" type="number" name="reviews" min="0"
                 value="<?= (int)($editCourse['reviews'] ?? 0) ?>">
        </div>
        <div class="a-form-group">
          <label for="cf_price">Price (USD)</label>
          <input id="cf_price" type="number" name="price" min="0" step="0.01"
                 value="<?= htmlspecialchars((string)($editCourse['price'] ?? '0'), ENT_QUOTES) ?>">
        </div>

        <!-- Old Price + Instructor -->
        <div class="a-form-group">
          <label for="cf_old_price">Old Price (USD)</label>
          <input id="cf_old_price" type="number" name="old_price" min="0" step="0.01"
                 value="<?= htmlspecialchars((string)($editCourse['old_price'] ?? '0'), ENT_QUOTES) ?>">
        </div>
        <div class="a-form-group">
          <label for="cf_instructor">Instructor</label>
          <input id="cf_instructor" type="text" name="instructor"
                 value="<?= $fv('instructor') ?>" placeholder="Dr. Jane Smith">
        </div>

        <!-- Instructor Title + Color -->
        <div class="a-form-group">
          <label for="cf_instructor_title">Instructor Title</label>
          <input id="cf_instructor_title" type="text" name="instructor_title"
                 value="<?= $fv('instructor_title') ?>" placeholder="Senior ML Researcher, MIT">
        </div>
        <div class="a-form-group">
          <label for="cf_color">Theme Color</label>
          <input id="cf_color" type="text" name="color"
                 value="<?= $fv('color', '#6366f1') ?>" placeholder="#6366f1">
        </div>

        <!-- Icon + Badge -->
        <div class="a-form-group">
          <label for="cf_icon">Icon</label>
          <input id="cf_icon" type="text" name="icon"
                 value="<?= $fv('icon', 'brain') ?>" placeholder="brain">
        </div>
        <div class="a-form-group">
          <label for="cf_badge">Badge</label>
          <input id="cf_badge" type="text" name="badge"
                 value="<?= $fv('badge') ?>" placeholder="e.g. Bestseller">
        </div>

        <div class="a-form-group full">
          <label for="cf_image_url">Course Photo URL</label>
          <input id="cf_image_url" type="text" name="image_url"
                 value="<?= $fv('image_url') ?>" placeholder="/ai-courses/assets/images/course-ml.jpg or https://...">
        </div>

        <!-- Description: full width -->
        <div class="a-form-group full">
          <label for="cf_description">Description <span style="color:var(--a-danger)">*</span></label>
          <textarea id="cf_description" name="description" rows="4"
                    placeholder="2–3 sentences that excite potential students"
                    required><?= htmlspecialchars((string)($editCourse['description'] ?? ''), ENT_QUOTES) ?></textarea>
        </div>

        <!-- Tags: full width -->
        <div class="a-form-group full">
          <label for="cf_tags">Tags (comma-separated)</label>
          <input id="cf_tags" type="text" name="tags_csv"
                 value="<?= htmlspecialchars($formTagsCsv, ENT_QUOTES) ?>"
                 placeholder="Python, Neural Networks, Hands-on">
        </div>

        <!-- Outcomes: full width -->
        <div class="a-form-group full">
          <label for="cf_outcomes">Learning Outcomes (one per line)</label>
          <textarea id="cf_outcomes" name="outcomes_text" rows="5"
                    placeholder="Build a neural network from scratch&#10;Deploy ML models to production"><?= htmlspecialchars($formOutcomes, ENT_QUOTES) ?></textarea>
        </div>

        <!-- Curriculum: full width -->
        <div class="a-form-group full">
          <label for="cf_curriculum">Curriculum — one module per line as: <code>Module Title|lessons</code></label>
          <textarea id="cf_curriculum" name="curriculum_text" rows="7"
                    placeholder="Introduction to ML|4&#10;Supervised Learning|6&#10;Neural Networks|8"><?= htmlspecialchars($formCurriculum, ENT_QUOTES) ?></textarea>
        </div>

        <!-- Active checkbox -->
        <div class="full" style="display:flex;align-items:center;gap:.5rem">
          <input type="checkbox" id="cf_active" name="is_active"
                 <?= ((int)($editCourse['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
          <label for="cf_active" style="margin:0;cursor:pointer">Active course (visible on site)</label>
        </div>

        <!-- Actions -->
        <div class="a-form-actions">
          <button type="submit" class="a-btn a-btn--primary">
            <?= $editCourse ? 'Update Course' : 'Create Course' ?>
          </button>
          <a href="<?= BASE ?>/admin/courses.php" class="a-btn a-btn--ghost">Cancel</a>
        </div>

      </div><!-- /.a-form-grid -->
    </form>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ── Courses Table ──────────────────────────────────────────────────────── -->
<div class="a-table-card">
  <div class="a-card-head">
    <h3>Course Catalog</h3>
    <span class="a-badge a-badge--muted"><?= $totalCourses ?> total</span>
  </div>
  <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Title</th>
          <th>Category</th>
          <th>Level</th>
          <th>Price</th>
          <th>Students</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($allCourses)): ?>
        <tr>
          <td colspan="8" style="text-align:center;padding:2rem;color:var(--a-text-muted)">
            No courses found. <a href="<?= BASE ?>/admin/courses.php?new=1" style="color:var(--a-primary)">Add one.</a>
          </td>
        </tr>
        <?php else: ?>
        <?php foreach ($allCourses as $c): ?>
        <?php $cId = (int)$c['id']; $cActive = (int)($c['is_active'] ?? 1); ?>
        <tr>
          <td style="color:var(--a-text-muted);font-size:.8rem">#<?= $cId ?></td>
          <td>
            <div style="font-weight:600;color:var(--a-text)"><?= htmlspecialchars((string)$c['title']) ?></div>
            <?php if (!empty($c['subtitle'])): ?>
            <div style="font-size:.78rem;color:var(--a-text-muted)"><?= htmlspecialchars((string)$c['subtitle']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars((string)$c['category']) ?></td>
          <td><?= htmlspecialchars((string)$c['level']) ?></td>
          <td>$<?= number_format((float)$c['price'], 2) ?></td>
          <td><?= number_format((int)$c['students']) ?></td>
          <td>
            <?php if ($cActive): ?>
              <span class="a-badge a-badge--success">Active</span>
            <?php else: ?>
              <span class="a-badge a-badge--muted">Inactive</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:.4rem;flex-wrap:wrap">
              <a href="<?= BASE ?>/admin/courses.php?edit=<?= $cId ?>"
                 class="a-btn a-btn--ghost a-btn--sm">Edit</a>

              <?php if (auth_has_permission($user, 'manage_courses')): ?>
              <!-- Toggle active -->
              <form method="POST" action="<?= BASE ?>/admin/courses.php" style="display:inline">
                <input type="hidden" name="action"    value="toggle_active">
                <input type="hidden" name="course_id" value="<?= $cId ?>">
                <input type="hidden" name="new_val"   value="<?= $cActive ? 0 : 1 ?>">
                <button type="submit" class="a-btn a-btn--ghost a-btn--sm">
                  <?= $cActive ? 'Deactivate' : 'Activate' ?>
                </button>
              </form>

              <!-- Delete -->
              <form method="POST" action="<?= BASE ?>/admin/courses.php" style="display:inline"
                    onsubmit="return confirm('Delete course \'<?= addslashes(htmlspecialchars((string)$c['title'])) ?>\'? This cannot be undone.')">
                <input type="hidden" name="action"    value="delete_course">
                <input type="hidden" name="course_id" value="<?= $cId ?>">
                <button type="submit" class="a-btn a-btn--danger a-btn--sm">Delete</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/_foot.php'; ?>
