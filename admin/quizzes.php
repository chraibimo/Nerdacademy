<?php
$admin_page_title  = 'Quizzes';
$admin_active_page = 'quizzes';
if (!defined('BASE')) define('BASE', '');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/course-content-repo.php';
require_once __DIR__ . '/../includes/courses-repo.php';

$user = auth_current_user();
if (!$user || !auth_can_access_admin_panel($user)) {
    header('Location: ' . BASE . '/index.php');
    exit;
}

require_once __DIR__ . '/../includes/quiz-repo.php';
ensure_quiz_tables($mysqli);
ensure_course_content_tables($mysqli);

require_once __DIR__ . '/_head.php';

// ============================================================
//  Flash message
// ============================================================
$flash     = '';
$flashType = 'success';

// ============================================================
//  POST handlers
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && auth_has_permission($user, 'manage_courses')) {
    $action = $_POST['action'] ?? '';

    /* ── save_quiz ─────────────────────────────────────────── */
    if ($action === 'save_quiz') {
        $lessonId         = (int)($_POST['lesson_id'] ?? 0);
        $quizIdEdit       = ($_POST['quiz_id'] ?? '') !== '' ? (int)$_POST['quiz_id'] : null;
        $title            = trim($_POST['title'] ?? '');
        $desc             = trim($_POST['description'] ?? '');
        $passScore        = max(0, min(100, (int)($_POST['pass_score'] ?? 70)));
        $timeLimitSeconds = max(0, (int)($_POST['time_limit_seconds'] ?? 0));

        if ($title === '' || $lessonId <= 0) {
            $flash     = 'Title and lesson are required.';
            $flashType = 'error';
        } else {
            $savedId = save_quiz($mysqli, $lessonId, $title, $desc, $passScore, $timeLimitSeconds, $quizIdEdit);
            if ($savedId > 0) {
                $flash = $quizIdEdit ? 'Quiz updated.' : 'Quiz created.';
                // Redirect to quiz detail
                header('Location: ' . BASE . '/admin/quizzes.php?quiz_id=' . $savedId . '&saved=1');
                exit;
            } else {
                $flash     = 'Failed to save quiz.';
                $flashType = 'error';
            }
        }
    }

    /* ── delete_quiz ───────────────────────────────────────── */
    if ($action === 'delete_quiz') {
        $quizId   = (int)($_POST['quiz_id'] ?? 0);
        $courseId = (int)($_POST['course_id'] ?? 0);
        if ($quizId > 0) {
            delete_quiz($mysqli, $quizId);
            $flash = 'Quiz deleted.';
        }
        $redirect = BASE . '/admin/quizzes.php' . ($courseId > 0 ? '?course_id=' . $courseId : '');
        header('Location: ' . $redirect);
        exit;
    }

    /* ── save_question ─────────────────────────────────────── */
    if ($action === 'save_question') {
        $quizId      = (int)($_POST['quiz_id'] ?? 0);
        $questionId  = ($_POST['question_id'] ?? '') !== '' ? (int)$_POST['question_id'] : null;
        $text        = trim($_POST['question_text'] ?? '');
        $sortOrder   = (int)($_POST['sort_order'] ?? 0);

        if ($text === '' || $quizId <= 0) {
            $flash     = 'Question text is required.';
            $flashType = 'error';
        } else {
            $qid = save_question($mysqli, $quizId, $text, $sortOrder, $questionId);

            // Save options submitted with question
            if ($qid > 0) {
                $optionTexts   = $_POST['option_text']    ?? [];
                $optionCorrect = $_POST['correct_option'] ?? -1;

                // If editing, delete existing options then re-insert
                if ($questionId !== null) {
                    // Remove old options
                    $delRes = $mysqli->prepare('DELETE FROM quiz_options WHERE question_id = ?');
                    if ($delRes) {
                        $delRes->bind_param('i', $qid);
                        $delRes->execute();
                        $delRes->close();
                    }
                }

                foreach ($optionTexts as $idx => $optText) {
                    $optText = trim((string)$optText);
                    if ($optText === '') {
                        continue;
                    }
                    $isCorrect = ((string)$optionCorrect === (string)$idx);
                    save_option($mysqli, $qid, $optText, $isCorrect, (int)$idx);
                }

                $flash = $questionId ? 'Question updated.' : 'Question added.';
                header('Location: ' . BASE . '/admin/quizzes.php?quiz_id=' . $quizId . '&saved=1');
                exit;
            } else {
                $flash     = 'Failed to save question.';
                $flashType = 'error';
            }
        }
    }

    /* ── delete_question ───────────────────────────────────── */
    if ($action === 'delete_question') {
        $questionId = (int)($_POST['question_id'] ?? 0);
        $quizId     = (int)($_POST['quiz_id'] ?? 0);
        if ($questionId > 0) {
            delete_question($mysqli, $questionId);
        }
        header('Location: ' . BASE . '/admin/quizzes.php?quiz_id=' . $quizId . '&saved=1');
        exit;
    }

    /* ── save_option ───────────────────────────────────────── */
    if ($action === 'save_option') {
        $questionId = (int)($_POST['question_id'] ?? 0);
        $quizId     = (int)($_POST['quiz_id'] ?? 0);
        $optionId   = ($_POST['option_id'] ?? '') !== '' ? (int)$_POST['option_id'] : null;
        $text       = trim($_POST['option_text'] ?? '');
        $isCorrect  = isset($_POST['is_correct']) && (int)$_POST['is_correct'] === 1;
        $sortOrder  = (int)($_POST['sort_order'] ?? 0);

        if ($text !== '' && $questionId > 0) {
            // If marking correct, clear other correct flags for this question
            if ($isCorrect) {
                $clr = $mysqli->prepare('UPDATE quiz_options SET is_correct = 0 WHERE question_id = ?');
                if ($clr) {
                    $clr->bind_param('i', $questionId);
                    $clr->execute();
                    $clr->close();
                }
            }
            save_option($mysqli, $questionId, $text, $isCorrect, $sortOrder, $optionId);
            $flash = $optionId ? 'Option updated.' : 'Option added.';
        }
        header('Location: ' . BASE . '/admin/quizzes.php?quiz_id=' . $quizId . '&saved=1');
        exit;
    }

    /* ── delete_option ─────────────────────────────────────── */
    if ($action === 'delete_option') {
        $optionId = (int)($_POST['option_id'] ?? 0);
        $quizId   = (int)($_POST['quiz_id'] ?? 0);
        if ($optionId > 0) {
            delete_option($mysqli, $optionId);
        }
        header('Location: ' . BASE . '/admin/quizzes.php?quiz_id=' . $quizId . '&saved=1');
        exit;
    }

    /* ── set_correct ────────────────────────────────────────── */
    if ($action === 'set_correct') {
        $optionId   = (int)($_POST['option_id'] ?? 0);
        $questionId = (int)($_POST['question_id'] ?? 0);
        $quizId     = (int)($_POST['quiz_id'] ?? 0);
        if ($optionId > 0 && $questionId > 0) {
            $clr = $mysqli->prepare('UPDATE quiz_options SET is_correct = 0 WHERE question_id = ?');
            if ($clr) {
                $clr->bind_param('i', $questionId);
                $clr->execute();
                $clr->close();
            }
            $upd = $mysqli->prepare('UPDATE quiz_options SET is_correct = 1 WHERE id = ?');
            if ($upd) {
                $upd->bind_param('i', $optionId);
                $upd->execute();
                $upd->close();
            }
        }
        header('Location: ' . BASE . '/admin/quizzes.php?quiz_id=' . $quizId . '&saved=1');
        exit;
    }
}

// ============================================================
//  GET: Determine view
// ============================================================
$viewQuizId  = (int)($_GET['quiz_id'] ?? 0);
$viewCourseId = (int)($_GET['course_id'] ?? 0);

$currentQuiz   = null;
$currentLesson = null;
$quizAttempts  = [];

if ($viewQuizId > 0) {
    $currentQuiz = get_quiz_by_id($mysqli, $viewQuizId);
    if ($currentQuiz) {
        $currentLesson = get_lesson($mysqli, $currentQuiz['lesson_id']);
        $quizAttempts  = get_all_attempts($mysqli, $viewQuizId);
    }
}

// Load courses for the course selector
$allCourses = load_all_courses($mysqli, false);

// Load modules/lessons for selected course
$courseModules = [];
$quizzesByLesson = [];

if ($viewCourseId > 0) {
    $courseModules = get_course_modules($mysqli, $viewCourseId);

    // Pre-load quizzes for all lessons in this course
    foreach ($courseModules as $mod) {
        foreach ($mod['lessons'] as $lesson) {
            $q = get_quiz_by_lesson($mysqli, (int)$lesson['id']);
            if ($q) {
                $quizzesByLesson[(int)$lesson['id']] = $q;
            }
        }
    }
}

// ============================================================
//  Helpers
// ============================================================
function fmt_time(int $seconds): string
{
    if ($seconds <= 0) {
        return 'Unlimited';
    }
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;
    return $s > 0 ? "{$m}m {$s}s" : "{$m}m";
}
?>

<!-- ── Page header ─────────────────────────────────────────────── -->
<div class="a-page-header">
  <div class="a-page-header-text">
    <?php if ($currentQuiz): ?>
      <h1><?= htmlspecialchars($currentQuiz['title'], ENT_QUOTES) ?></h1>
      <p>
        <?php if ($currentLesson): ?>
          Lesson: <strong><?= htmlspecialchars($currentLesson['title'], ENT_QUOTES) ?></strong>
          &mdash;
        <?php endif; ?>
        Pass score: <strong><?= $currentQuiz['pass_score'] ?>%</strong>
        &mdash; <?= count($currentQuiz['questions']) ?> question<?= count($currentQuiz['questions']) !== 1 ? 's' : '' ?>
        &mdash; Time limit: <strong><?= fmt_time($currentQuiz['time_limit_seconds']) ?></strong>
      </p>
    <?php else: ?>
      <h1>Quizzes</h1>
      <p>Create and manage lesson quizzes</p>
    <?php endif; ?>
  </div>
  <div class="a-page-actions">
    <?php if ($currentQuiz): ?>
      <?php
        $backUrl = BASE . '/admin/quizzes.php';
        if ($currentLesson) {
            $backUrl .= '?course_id=' . (int)$currentLesson['course_id'];
        }
      ?>
      <a href="<?= $backUrl ?>" class="a-btn a-btn--ghost">
        &larr; Back to Course
      </a>
      <?php if (auth_has_permission($user, 'manage_courses')): ?>
      <button class="a-btn a-btn--primary" onclick="openModal('modalAddQuestion')">
        + Add Question
      </button>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($flash !== '' || isset($_GET['saved'])): ?>
<div style="padding:.85rem 1.1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.875rem;font-weight:500;
     background:<?= ($flashType === 'error') ? '#fef2f2' : '#ecfdf5' ?>;
     color:<?= ($flashType === 'error') ? '#b91c1c' : '#15803d' ?>;
     border:1px solid <?= ($flashType === 'error') ? '#fecaca' : '#bbf7d0' ?>;">
  <?= $flash !== '' ? htmlspecialchars($flash) : 'Changes saved.' ?>
</div>
<?php endif; ?>

<?php if ($currentQuiz): ?>
<!-- ============================================================
     QUIZ DETAIL VIEW
     ============================================================ -->

<!-- Quiz meta card with edit/delete -->
<?php if (auth_has_permission($user, 'manage_courses')): ?>
<div class="a-card" style="margin-bottom:1.5rem">
  <div class="a-card-head">
    <h3>Quiz Settings</h3>
    <div style="display:flex;gap:.5rem">
      <button class="a-btn a-btn--ghost a-btn--sm"
              onclick="openEditQuiz(<?= $currentQuiz['id'] ?>,
                <?= (int)$currentQuiz['lesson_id'] ?>,
                <?= htmlspecialchars(json_encode($currentQuiz['title']), ENT_QUOTES) ?>,
                <?= htmlspecialchars(json_encode($currentQuiz['description']), ENT_QUOTES) ?>,
                <?= $currentQuiz['pass_score'] ?>,
                <?= $currentQuiz['time_limit_seconds'] ?>)">Edit Quiz</button>
      <button class="a-btn a-btn--danger a-btn--sm"
              onclick="confirmDeleteQuiz(<?= $currentQuiz['id'] ?>, 0)">Delete Quiz</button>
    </div>
  </div>
  <?php if ($currentQuiz['description']): ?>
  <div class="a-card-body">
    <p style="color:var(--a-text-muted);margin:0"><?= htmlspecialchars($currentQuiz['description']) ?></p>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Questions -->
<?php if (empty($currentQuiz['questions'])): ?>
<div class="a-card" style="padding:2.5rem;text-align:center;color:var(--a-text-muted)">
  <p>No questions yet.</p>
  <?php if (auth_has_permission($user, 'manage_courses')): ?>
  <button class="a-btn a-btn--primary" onclick="openModal('modalAddQuestion')" style="margin-top:.75rem">
    Add First Question
  </button>
  <?php endif; ?>
</div>
<?php else: ?>

<?php foreach ($currentQuiz['questions'] as $qi => $question): ?>
<div class="a-card" style="margin-bottom:1.25rem" id="q<?= $question['id'] ?>">
  <div class="a-card-head">
    <div style="display:flex;align-items:center;gap:.75rem;flex:1;min-width:0">
      <span class="a-badge a-badge--muted" style="flex-shrink:0">Q<?= $qi + 1 ?></span>
      <span style="font-weight:600;color:var(--a-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
        <?= htmlspecialchars($question['question_text']) ?>
      </span>
    </div>
    <?php if (auth_has_permission($user, 'manage_courses')): ?>
    <div style="display:flex;gap:.4rem;flex-shrink:0">
      <button class="a-btn a-btn--ghost a-btn--sm"
              onclick="openEditQuestion(<?= $question['id'] ?>, <?= $currentQuiz['id'] ?>,
                <?= htmlspecialchars(json_encode($question['question_text']), ENT_QUOTES) ?>,
                <?= $question['sort_order'] ?>,
                <?= htmlspecialchars(json_encode($question['options']), ENT_QUOTES) ?>)">Edit</button>
      <button class="a-btn a-btn--danger a-btn--sm"
              onclick="confirmDeleteQuestion(<?= $question['id'] ?>, <?= $currentQuiz['id'] ?>)">Delete</button>
    </div>
    <?php endif; ?>
  </div>
  <div class="a-card-body" style="padding-top:.5rem">
    <!-- Options list -->
    <div style="display:flex;flex-direction:column;gap:.4rem;margin-bottom:1rem">
      <?php foreach ($question['options'] as $oi => $opt): ?>
      <div style="display:flex;align-items:center;gap:.65rem;padding:.5rem .75rem;border-radius:6px;
                  background:<?= $opt['is_correct'] ? 'rgba(16,185,129,.08)' : 'var(--a-bg-muted,#f8f9fa)' ?>;
                  border:1px solid <?= $opt['is_correct'] ? '#6ee7b7' : 'var(--a-border,#e2e8f0)' ?>">
        <?php if ($opt['is_correct']): ?>
          <svg width="16" height="16" fill="none" stroke="#10b981" stroke-width="2.5" viewBox="0 0 24 24" style="flex-shrink:0" aria-label="Correct"><polyline points="20 6 9 17 4 12"/></svg>
        <?php else: ?>
          <span style="width:16px;height:16px;border-radius:50%;border:2px solid var(--a-border,#e2e8f0);flex-shrink:0;display:inline-block"></span>
        <?php endif; ?>
        <span style="flex:1;font-size:.875rem"><?= htmlspecialchars($opt['option_text']) ?></span>
        <?php if (auth_has_permission($user, 'manage_courses')): ?>
        <div style="display:flex;gap:.3rem">
          <?php if (!$opt['is_correct']): ?>
          <form method="POST" action="<?= BASE ?>/admin/quizzes.php" style="display:inline">
            <input type="hidden" name="action"      value="set_correct">
            <input type="hidden" name="option_id"   value="<?= $opt['id'] ?>">
            <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
            <input type="hidden" name="quiz_id"     value="<?= $currentQuiz['id'] ?>">
            <button type="submit" class="a-btn a-btn--ghost a-btn--sm" style="font-size:.72rem;padding:.2rem .55rem">
              Set Correct
            </button>
          </form>
          <?php endif; ?>
          <button class="a-btn a-btn--ghost a-btn--sm" style="font-size:.72rem;padding:.2rem .55rem"
                  onclick="openEditOption(<?= $opt['id'] ?>, <?= $question['id'] ?>, <?= $currentQuiz['id'] ?>,
                    <?= htmlspecialchars(json_encode($opt['option_text']), ENT_QUOTES) ?>,
                    <?= $opt['is_correct'] ? 1 : 0 ?>, <?= $opt['sort_order'] ?>)">Edit</button>
          <form method="POST" action="<?= BASE ?>/admin/quizzes.php" style="display:inline"
                onsubmit="return confirm('Delete this option?')">
            <input type="hidden" name="action"    value="delete_option">
            <input type="hidden" name="option_id" value="<?= $opt['id'] ?>">
            <input type="hidden" name="quiz_id"   value="<?= $currentQuiz['id'] ?>">
            <button type="submit" class="a-btn a-btn--danger a-btn--sm" style="font-size:.72rem;padding:.2rem .55rem">Del</button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if (auth_has_permission($user, 'manage_courses')): ?>
    <button class="a-btn a-btn--ghost a-btn--sm"
            onclick="openAddOption(<?= $question['id'] ?>, <?= $currentQuiz['id'] ?>)">
      + Add Option
    </button>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<?php endif; /* end questions */ ?>

<!-- Attempts table -->
<?php if (!empty($quizAttempts)): ?>
<div class="a-table-card" style="margin-top:2rem">
  <div class="a-card-head">
    <h3>Attempts</h3>
    <span class="a-badge a-badge--muted"><?= count($quizAttempts) ?> total</span>
  </div>
  <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>User</th>
          <th>Score</th>
          <th>Result</th>
          <th>Completed</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($quizAttempts as $attempt): ?>
        <tr>
          <td>
            <div style="font-weight:500"><?= htmlspecialchars($attempt['full_name'] ?? 'Unknown') ?></div>
            <div style="font-size:.78rem;color:var(--a-text-muted)"><?= htmlspecialchars($attempt['email'] ?? '') ?></div>
          </td>
          <td><strong><?= $attempt['score'] ?>%</strong></td>
          <td>
            <?php if ($attempt['passed']): ?>
              <span class="a-badge a-badge--success">Passed</span>
            <?php else: ?>
              <span class="a-badge a-badge--danger">Failed</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.8rem;color:var(--a-text-muted)"><?= htmlspecialchars($attempt['completed_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ============================================================
     COURSE SELECTOR + QUIZ OVERVIEW
     ============================================================ -->

<!-- Course selector -->
<div class="a-card" style="margin-bottom:1.5rem">
  <div class="a-card-body">
    <form method="GET" action="<?= BASE ?>/admin/quizzes.php" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
      <div class="a-form-group" style="flex:1;min-width:200px;margin:0">
        <label for="sel_course">Select Course</label>
        <select id="sel_course" name="course_id" onchange="this.form.submit()">
          <option value="">— choose a course —</option>
          <?php foreach ($allCourses as $c): ?>
          <option value="<?= (int)$c['id'] ?>"
            <?= ($viewCourseId === (int)$c['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['title']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="a-btn a-btn--primary">View</button>
    </form>
  </div>
</div>

<?php if ($viewCourseId > 0): ?>

<?php if (empty($courseModules)): ?>
<div class="a-card" style="padding:2rem;text-align:center;color:var(--a-text-muted)">
  No modules found for this course. Add content first via
  <a href="<?= BASE ?>/admin/course-content.php?course_id=<?= $viewCourseId ?>">Course Content</a>.
</div>
<?php else: ?>

<?php foreach ($courseModules as $mod): ?>
<div class="a-card" style="margin-bottom:1.25rem">
  <div class="a-card-head">
    <h3 style="font-size:1rem"><?= htmlspecialchars($mod['title']) ?></h3>
    <span class="a-badge a-badge--muted"><?= count($mod['lessons']) ?> lesson<?= count($mod['lessons']) !== 1 ? 's' : '' ?></span>
  </div>

  <?php if (empty($mod['lessons'])): ?>
  <div class="a-card-body" style="color:var(--a-text-muted);font-size:.875rem">No lessons in this module.</div>
  <?php else: ?>
  <div class="a-card-body" style="padding:0">
    <?php foreach ($mod['lessons'] as $li => $lesson): ?>
    <?php
      $lid       = (int)$lesson['id'];
      $hasQuiz   = isset($quizzesByLesson[$lid]);
      $lessonQuiz = $hasQuiz ? $quizzesByLesson[$lid] : null;
    ?>
    <div style="display:flex;align-items:center;gap:1rem;padding:.85rem 1.25rem;
                border-top:<?= $li > 0 ? '1px solid var(--a-border,#e2e8f0)' : 'none' ?>;flex-wrap:wrap">
      <!-- Lesson info -->
      <div style="flex:1;min-width:0">
        <div style="font-weight:500;color:var(--a-text)"><?= htmlspecialchars($lesson['title']) ?></div>
        <?php if ($hasQuiz): ?>
        <div style="font-size:.78rem;color:var(--a-text-muted);margin-top:.15rem">
          Pass: <?= $lessonQuiz['pass_score'] ?>%
          &middot; <?= count($lessonQuiz['questions']) ?> question<?= count($lessonQuiz['questions']) !== 1 ? 's' : '' ?>
          &middot; Time: <?= fmt_time($lessonQuiz['time_limit_seconds']) ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Actions -->
      <div style="display:flex;gap:.4rem;flex-shrink:0">
        <?php if ($hasQuiz): ?>
          <a href="<?= BASE ?>/admin/quizzes.php?quiz_id=<?= $lessonQuiz['id'] ?>"
             class="a-btn a-btn--ghost a-btn--sm">Edit Quiz</a>
          <?php if (auth_has_permission($user, 'manage_courses')): ?>
          <button class="a-btn a-btn--danger a-btn--sm"
                  onclick="confirmDeleteQuiz(<?= $lessonQuiz['id'] ?>, <?= $viewCourseId ?>)">Delete</button>
          <?php endif; ?>
        <?php else: ?>
          <?php if (auth_has_permission($user, 'manage_courses')): ?>
          <button class="a-btn a-btn--primary a-btn--sm"
                  onclick="openAddQuiz(<?= $lid ?>, <?= htmlspecialchars(json_encode($lesson['title']), ENT_QUOTES) ?>)">
            + Add Quiz
          </button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php endif; /* end courseModules check */ ?>
<?php endif; /* end viewCourseId check */ ?>
<?php endif; /* end quiz detail vs overview */ ?>


<!-- ============================================================
     MODALS
     ============================================================ -->

<!-- Add / Edit Quiz Modal -->
<div class="a-modal-bg" id="modalQuiz" style="display:none" onclick="if(event.target===this)closeModal('modalQuiz')">
  <div class="a-modal">
    <div class="a-modal-head">
      <h3 id="modalQuizTitle">Add Quiz</h3>
      <button class="a-modal-close" onclick="closeModal('modalQuiz')" aria-label="Close">&times;</button>
    </div>
    <div class="a-modal-body">
      <form method="POST" action="<?= BASE ?>/admin/quizzes.php">
        <input type="hidden" name="action"     value="save_quiz">
        <input type="hidden" name="quiz_id"    id="mq_quiz_id">
        <input type="hidden" name="lesson_id"  id="mq_lesson_id">

        <div class="a-form-grid">
          <div class="a-form-group full">
            <label for="mq_lesson_label">Lesson</label>
            <input type="text" id="mq_lesson_label" disabled style="background:var(--a-bg-muted,#f8f9fa)">
          </div>

          <div class="a-form-group full">
            <label for="mq_title">Quiz Title <span style="color:var(--a-danger)">*</span></label>
            <input type="text" id="mq_title" name="title" required placeholder="e.g. Module 1 Quiz">
          </div>

          <div class="a-form-group full">
            <label for="mq_desc">Description</label>
            <textarea id="mq_desc" name="description" rows="2" placeholder="Optional description or instructions"></textarea>
          </div>

          <div class="a-form-group">
            <label for="mq_pass_score">Pass Score (%) <span style="color:var(--a-danger)">*</span></label>
            <input type="number" id="mq_pass_score" name="pass_score" min="0" max="100" value="70" required>
          </div>

          <div class="a-form-group">
            <label for="mq_time">Time Limit (seconds, 0 = unlimited)</label>
            <input type="number" id="mq_time" name="time_limit_seconds" min="0" value="0">
          </div>
        </div>

        <div class="a-form-actions" style="margin-top:1.25rem">
          <button type="submit" class="a-btn a-btn--primary" id="mq_submit_btn">Create Quiz</button>
          <button type="button" class="a-btn a-btn--ghost" onclick="closeModal('modalQuiz')">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add / Edit Question Modal -->
<div class="a-modal-bg" id="modalAddQuestion" style="display:none" onclick="if(event.target===this)closeModal('modalAddQuestion')">
  <div class="a-modal" style="max-width:640px">
    <div class="a-modal-head">
      <h3 id="modalQTitle">Add Question</h3>
      <button class="a-modal-close" onclick="closeModal('modalAddQuestion')" aria-label="Close">&times;</button>
    </div>
    <div class="a-modal-body">
      <form method="POST" action="<?= BASE ?>/admin/quizzes.php">
        <input type="hidden" name="action"       value="save_question">
        <input type="hidden" name="quiz_id"      id="mq2_quiz_id" value="<?= $viewQuizId ?>">
        <input type="hidden" name="question_id"  id="mq2_question_id">
        <input type="hidden" name="sort_order"   id="mq2_sort_order" value="0">

        <div class="a-form-group" style="margin-bottom:1rem">
          <label for="mq2_text">Question Text <span style="color:var(--a-danger)">*</span></label>
          <textarea id="mq2_text" name="question_text" rows="3"
                    placeholder="Enter your question here…" required></textarea>
        </div>

        <div style="margin-bottom:.75rem">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem">
            <label style="margin:0;font-weight:600">Answer Options</label>
            <button type="button" class="a-btn a-btn--ghost a-btn--sm" onclick="addOptionRow()">+ Add Option</button>
          </div>
          <div id="optionRows" style="display:flex;flex-direction:column;gap:.5rem">
            <!-- Option rows will be inserted by JS -->
          </div>
          <p style="font-size:.78rem;color:var(--a-text-muted);margin-top:.4rem">
            Select the radio button next to the correct answer.
          </p>
        </div>

        <div class="a-form-actions" style="margin-top:1.25rem">
          <button type="submit" class="a-btn a-btn--primary" id="mq2_submit_btn">Add Question</button>
          <button type="button" class="a-btn a-btn--ghost" onclick="closeModal('modalAddQuestion')">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add / Edit Option Modal -->
<div class="a-modal-bg" id="modalOption" style="display:none" onclick="if(event.target===this)closeModal('modalOption')">
  <div class="a-modal">
    <div class="a-modal-head">
      <h3 id="modalOptTitle">Add Option</h3>
      <button class="a-modal-close" onclick="closeModal('modalOption')" aria-label="Close">&times;</button>
    </div>
    <div class="a-modal-body">
      <form method="POST" action="<?= BASE ?>/admin/quizzes.php">
        <input type="hidden" name="action"      value="save_option">
        <input type="hidden" name="quiz_id"     id="mo_quiz_id">
        <input type="hidden" name="question_id" id="mo_question_id">
        <input type="hidden" name="option_id"   id="mo_option_id">
        <input type="hidden" name="sort_order"  id="mo_sort_order" value="0">

        <div class="a-form-group" style="margin-bottom:1rem">
          <label for="mo_text">Option Text <span style="color:var(--a-danger)">*</span></label>
          <input type="text" id="mo_text" name="option_text" required placeholder="Answer option text">
        </div>

        <div style="margin-bottom:1rem;display:flex;align-items:center;gap:.5rem">
          <input type="checkbox" id="mo_correct" name="is_correct" value="1">
          <label for="mo_correct" style="margin:0;cursor:pointer">This is the correct answer</label>
        </div>

        <div class="a-form-actions">
          <button type="submit" class="a-btn a-btn--primary" id="mo_submit_btn">Save Option</button>
          <button type="button" class="a-btn a-btn--ghost" onclick="closeModal('modalOption')">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Quiz confirm form (hidden) -->
<form method="POST" action="<?= BASE ?>/admin/quizzes.php" id="formDeleteQuiz" style="display:none">
  <input type="hidden" name="action"    value="delete_quiz">
  <input type="hidden" name="quiz_id"   id="del_quiz_id">
  <input type="hidden" name="course_id" id="del_course_id">
</form>

<!-- Delete Question confirm form (hidden) -->
<form method="POST" action="<?= BASE ?>/admin/quizzes.php" id="formDeleteQuestion" style="display:none">
  <input type="hidden" name="action"      value="delete_question">
  <input type="hidden" name="question_id" id="del_question_id">
  <input type="hidden" name="quiz_id"     id="del_q_quiz_id">
</form>

<script>
// ── Modal helpers ─────────────────────────────────────────────────────────────
function openModal(id) {
  const el = document.getElementById(id);
  if (el) { el.style.display = 'flex'; }
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) { el.style.display = 'none'; }
}
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    ['modalQuiz','modalAddQuestion','modalOption'].forEach(closeModal);
  }
});

// ── Quiz modal ────────────────────────────────────────────────────────────────
function openAddQuiz(lessonId, lessonTitle) {
  document.getElementById('modalQuizTitle').textContent   = 'Add Quiz';
  document.getElementById('mq_submit_btn').textContent    = 'Create Quiz';
  document.getElementById('mq_quiz_id').value             = '';
  document.getElementById('mq_lesson_id').value           = lessonId;
  document.getElementById('mq_lesson_label').value        = lessonTitle;
  document.getElementById('mq_title').value               = '';
  document.getElementById('mq_desc').value                = '';
  document.getElementById('mq_pass_score').value          = '70';
  document.getElementById('mq_time').value                = '0';
  openModal('modalQuiz');
}

function openEditQuiz(quizId, lessonId, title, desc, passScore, timeLimit) {
  document.getElementById('modalQuizTitle').textContent   = 'Edit Quiz';
  document.getElementById('mq_submit_btn').textContent    = 'Update Quiz';
  document.getElementById('mq_quiz_id').value             = quizId;
  document.getElementById('mq_lesson_id').value           = lessonId;
  document.getElementById('mq_lesson_label').value        = 'Lesson #' + lessonId;
  document.getElementById('mq_title').value               = title;
  document.getElementById('mq_desc').value                = desc;
  document.getElementById('mq_pass_score').value          = passScore;
  document.getElementById('mq_time').value                = timeLimit;
  openModal('modalQuiz');
}

// ── Question modal ────────────────────────────────────────────────────────────
let _optionIdx = 0;

function buildOptionRow(idx, text, isCorrect) {
  const checked = isCorrect ? 'checked' : '';
  return `<div class="option-row" style="display:flex;align-items:center;gap:.5rem">
    <input type="radio" name="correct_option" value="${idx}" ${checked}
           style="flex-shrink:0" title="Mark as correct answer">
    <input type="text" name="option_text[]" value="${text}" placeholder="Option text…"
           style="flex:1" required>
    <button type="button" class="a-btn a-btn--danger a-btn--sm"
            onclick="this.closest('.option-row').remove()" style="flex-shrink:0">&times;</button>
  </div>`;
}

function addOptionRow(text, isCorrect) {
  const container = document.getElementById('optionRows');
  const idx = _optionIdx++;
  const div = document.createElement('div');
  div.innerHTML = buildOptionRow(idx, text || '', isCorrect || false);
  container.appendChild(div.firstElementChild);
}

function openAddQuestion(quizId) {
  document.getElementById('modalQTitle').textContent    = 'Add Question';
  document.getElementById('mq2_submit_btn').textContent = 'Add Question';
  document.getElementById('mq2_quiz_id').value          = quizId;
  document.getElementById('mq2_question_id').value      = '';
  document.getElementById('mq2_sort_order').value       = '0';
  document.getElementById('mq2_text').value             = '';
  // Reset option rows with 4 blank defaults
  const container = document.getElementById('optionRows');
  container.innerHTML = '';
  _optionIdx = 0;
  for (let i = 0; i < 4; i++) addOptionRow('', i === 0);
  openModal('modalAddQuestion');
}

function openEditQuestion(questionId, quizId, text, sortOrder, options) {
  document.getElementById('modalQTitle').textContent    = 'Edit Question';
  document.getElementById('mq2_submit_btn').textContent = 'Update Question';
  document.getElementById('mq2_quiz_id').value          = quizId;
  document.getElementById('mq2_question_id').value      = questionId;
  document.getElementById('mq2_sort_order').value       = sortOrder;
  document.getElementById('mq2_text').value             = text;

  const container = document.getElementById('optionRows');
  container.innerHTML = '';
  _optionIdx = 0;

  if (options && options.length > 0) {
    options.forEach((opt) => addOptionRow(opt.option_text, opt.is_correct));
  } else {
    for (let i = 0; i < 4; i++) addOptionRow('', i === 0);
  }
  openModal('modalAddQuestion');
}

// Wire default "Add Question" button to use current quiz id
document.querySelectorAll('button[onclick^="openModal(\'modalAddQuestion\')"]').forEach(btn => {
  btn.removeAttribute('onclick');
  btn.addEventListener('click', () => openAddQuestion(<?= $viewQuizId ?: 0 ?>));
});

// ── Option modal ──────────────────────────────────────────────────────────────
function openAddOption(questionId, quizId) {
  document.getElementById('modalOptTitle').textContent   = 'Add Option';
  document.getElementById('mo_submit_btn').textContent   = 'Add Option';
  document.getElementById('mo_quiz_id').value            = quizId;
  document.getElementById('mo_question_id').value        = questionId;
  document.getElementById('mo_option_id').value          = '';
  document.getElementById('mo_sort_order').value         = '0';
  document.getElementById('mo_text').value               = '';
  document.getElementById('mo_correct').checked          = false;
  openModal('modalOption');
}

function openEditOption(optionId, questionId, quizId, text, isCorrect, sortOrder) {
  document.getElementById('modalOptTitle').textContent   = 'Edit Option';
  document.getElementById('mo_submit_btn').textContent   = 'Update Option';
  document.getElementById('mo_quiz_id').value            = quizId;
  document.getElementById('mo_question_id').value        = questionId;
  document.getElementById('mo_option_id').value          = optionId;
  document.getElementById('mo_sort_order').value         = sortOrder;
  document.getElementById('mo_text').value               = text;
  document.getElementById('mo_correct').checked          = isCorrect === 1;
  openModal('modalOption');
}

// ── Delete helpers ────────────────────────────────────────────────────────────
function confirmDeleteQuiz(quizId, courseId) {
  if (!confirm('Delete this quiz and all its questions? This cannot be undone.')) return;
  document.getElementById('del_quiz_id').value   = quizId;
  document.getElementById('del_course_id').value = courseId;
  document.getElementById('formDeleteQuiz').submit();
}

function confirmDeleteQuestion(questionId, quizId) {
  if (!confirm('Delete this question and all its options?')) return;
  document.getElementById('del_question_id').value = questionId;
  document.getElementById('del_q_quiz_id').value   = quizId;
  document.getElementById('formDeleteQuestion').submit();
}
</script>

<?php require_once __DIR__ . '/_foot.php'; ?>
