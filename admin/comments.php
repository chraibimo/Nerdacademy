<?php

$admin_page_title  = 'Comment Moderation';
$admin_active_page = 'comments';

if (!defined('BASE')) define('BASE', '');

require_once __DIR__ . '/_head.php';
require_once __DIR__ . '/../includes/lesson-comments-repo.php';

ensure_lesson_comments_table($mysqli);

// ================================================================
//  POST actions
// ================================================================
$message     = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && auth_has_permission($user, 'manage_courses')) {
    $action = (string)($_POST['action'] ?? '');

    // ---- Bulk delete ----
    if ($action === 'bulk_delete') {
        $ids = is_array($_POST['comment_ids'] ?? null) ? $_POST['comment_ids'] : [];
        $deleted = 0;
        foreach ($ids as $rawId) {
            $cid = (int)$rawId;
            if ($cid > 0 && delete_comment($mysqli, $cid)) {
                $deleted++;
            }
        }
        $message = "Deleted $deleted comment(s).";
    }

    // ---- Single delete ----
    if ($action === 'delete_comment') {
        $cid = (int)($_POST['comment_id'] ?? 0);
        if ($cid > 0 && delete_comment($mysqli, $cid)) {
            $message = 'Comment deleted.';
        } else {
            $message     = 'Failed to delete comment.';
            $messageType = 'error';
        }
    }

    // ---- Approve ----
    if ($action === 'approve_comment') {
        $cid = (int)($_POST['comment_id'] ?? 0);
        if ($cid > 0 && update_comment_status($mysqli, $cid, 'approved')) {
            $message = 'Comment approved.';
        } else {
            $message     = 'Failed to update comment.';
            $messageType = 'error';
        }
    }

    // ---- Reject ----
    if ($action === 'reject_comment') {
        $cid = (int)($_POST['comment_id'] ?? 0);
        if ($cid > 0 && update_comment_status($mysqli, $cid, 'rejected')) {
            $message = 'Comment rejected.';
        } else {
            $message     = 'Failed to update comment.';
            $messageType = 'error';
        }
    }
}

// ================================================================
//  Filters & pagination
// ================================================================
$search       = trim((string)($_GET['q']      ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$pageNum      = max(1, (int)($_GET['p'] ?? 1));
$perPage      = 20;

// Validate status filter
if (!in_array($statusFilter, ['approved', 'rejected', ''], true)) {
    $statusFilter = '';
}

// Fetch paginated comments
$result = get_all_comments_paginated($mysqli, $pageNum, $perPage, $search);
$comments = $result['items'];
$totalAll = $result['total'];

// If a status filter is active we need to filter in PHP (simple approach)
// for a small-scale moderation panel this is acceptable.
// A cleaner approach adds the filter inside the repo; here we re-query with a manual WHERE.
if ($statusFilter !== '') {
    // Re-query: build a custom filtered list.
    // We'll extend get_all_comments_paginated behaviour via a direct query here.
    $escapedStatus = $mysqli->real_escape_string($statusFilter);
    $escapedSearch = $search !== '' ? '%' . $mysqli->real_escape_string($search) . '%' : null;
    $searchWhere   = '';
    if ($escapedSearch !== null) {
        $searchWhere = "AND (cli.full_name LIKE '$escapedSearch' OR cli.email LIKE '$escapedSearch' OR lc.body LIKE '$escapedSearch' OR cl.title LIKE '$escapedSearch')";
    }

    $countR = $mysqli->query("
        SELECT COUNT(*) AS total
        FROM lesson_comments lc
        LEFT JOIN clients cli       ON cli.id  = lc.client_id
        LEFT JOIN course_lessons cl ON cl.id   = lc.lesson_id
        WHERE lc.status = '$escapedStatus' $searchWhere
    ");
    $totalAll = $countR ? (int)($countR->fetch_assoc()['total'] ?? 0) : 0;

    $offsetVal = ($pageNum - 1) * $perPage;
    $itemsR = $mysqli->query("
        SELECT
            lc.id, lc.lesson_id, lc.client_id, lc.parent_id, lc.body, lc.status, lc.created_at,
            cli.full_name  AS commenter_name,
            cli.email      AS commenter_email,
            cl.title       AS lesson_title,
            cc.title       AS course_title
        FROM lesson_comments lc
        LEFT JOIN clients cli         ON cli.id  = lc.client_id
        LEFT JOIN course_lessons cl   ON cl.id   = lc.lesson_id
        LEFT JOIN courses_catalog cc  ON cc.id   = cl.course_id
        WHERE lc.status = '$escapedStatus' $searchWhere
        ORDER BY lc.created_at DESC
        LIMIT $perPage OFFSET $offsetVal
    ");
    $comments = [];
    if ($itemsR) {
        while ($row = $itemsR->fetch_assoc()) {
            $comments[] = $row;
        }
    }
}

$totalPages = max(1, (int)ceil($totalAll / $perPage));

// ================================================================
//  Stats
// ================================================================
$statsR = $mysqli->query("
    SELECT
        COUNT(*)                              AS total,
        SUM(status = 'approved')              AS approved,
        SUM(status = 'rejected')              AS rejected
    FROM lesson_comments
");
$stats = ['total' => 0, 'approved' => 0, 'rejected' => 0];
if ($statsR) {
    $sRow = $statsR->fetch_assoc();
    $stats['total']    = (int)($sRow['total']    ?? 0);
    $stats['approved'] = (int)($sRow['approved'] ?? 0);
    $stats['rejected'] = (int)($sRow['rejected'] ?? 0);
}

// ================================================================
//  Build query-string helper (preserves current filters)
// ================================================================
function comments_qs(array $overrides = []): string
{
    $base = [
        'q'      => trim((string)($_GET['q']      ?? '')),
        'status' => trim((string)($_GET['status'] ?? '')),
        'p'      => (string)max(1, (int)($_GET['p'] ?? 1)),
    ];
    $merged = array_merge($base, $overrides);
    $parts  = [];
    foreach ($merged as $k => $v) {
        if ($v !== '') {
            $parts[] = urlencode($k) . '=' . urlencode($v);
        }
    }
    return $parts ? '?' . implode('&', $parts) : '?';
}
?>

<!-- ============================================================
     Page header
     ============================================================ -->
<div class="a-page-header">
  <div>
    <h1>Comment Moderation</h1>
    <p>Review, approve, reject and remove lesson comments across all courses.</p>
  </div>
</div>

<!-- Stats row -->
<div class="a-stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1.25rem">
  <div class="a-stat-card">
    <div class="a-stat-value"><?= $stats['total'] ?></div>
    <div class="a-stat-label">Total Comments</div>
  </div>
  <div class="a-stat-card">
    <div class="a-stat-value" style="color:var(--a-success,#16a34a)"><?= $stats['approved'] ?></div>
    <div class="a-stat-label">Approved</div>
  </div>
  <div class="a-stat-card">
    <div class="a-stat-value" style="color:var(--a-danger,#dc2626)"><?= $stats['rejected'] ?></div>
    <div class="a-stat-label">Rejected</div>
  </div>
</div>

<?php if ($message !== ''): ?>
<div style="padding:.85rem 1.1rem;border-radius:8px;margin-bottom:1rem;font-size:.875rem;font-weight:500;
     background:<?= $messageType === 'error' ? '#fef2f2' : '#ecfdf5' ?>;
     color:<?= $messageType === 'error' ? '#b91c1c' : '#15803d' ?>;
     border:1px solid <?= $messageType === 'error' ? '#fecaca' : '#bbf7d0' ?>;">
  <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- ============================================================
     Filter bar + bulk delete form
     ============================================================ -->
<form method="post" id="bulkForm">
  <input type="hidden" name="action" value="bulk_delete">

  <div class="a-table-card">

    <!-- Toolbar -->
    <div class="a-bar" style="flex-wrap:wrap;gap:.6rem">
      <!-- Search + filter (GET links) -->
      <div style="display:flex;gap:.6rem;flex:1;flex-wrap:wrap">
        <form method="get" style="display:contents">
          <input
            name="q"
            value="<?= htmlspecialchars($search) ?>"
            class="a-bar-input"
            style="max-width:260px"
            placeholder="Search commenter, lesson, body…"
          >
          <select name="status" class="a-bar-input" style="max-width:170px">
            <option value="">All statuses</option>
            <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
          </select>
          <button class="a-btn a-btn--primary a-btn--sm" type="submit">Filter</button>
          <?php if ($search !== '' || $statusFilter !== ''): ?>
          <a href="<?= BASE ?>/admin/comments.php" class="a-btn a-btn--ghost a-btn--sm">Clear</a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Bulk action -->
      <button
        class="a-btn a-btn--danger a-btn--sm"
        type="submit"
        onclick="return confirm('Delete all selected comments?')"
      >
        Delete Selected
      </button>
    </div>

    <!-- Table -->
    <table>
      <thead>
        <tr>
          <th style="width:2.5rem">
            <input type="checkbox" id="checkAll" title="Select all" style="cursor:pointer">
          </th>
          <th>Commenter</th>
          <th>Lesson / Course</th>
          <th>Preview</th>
          <th>Status</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($comments)): ?>
        <tr>
          <td colspan="7" style="padding:2rem;text-align:center;color:var(--a-text-muted)">
            No comments found.
          </td>
        </tr>
        <?php else: ?>
        <?php foreach ($comments as $cm): ?>
        <?php
            $preview      = htmlspecialchars(mb_strimwidth((string)($cm['body'] ?? ''), 0, 100, '…'));
            $commenterName  = htmlspecialchars((string)($cm['commenter_name']  ?? 'Unknown'));
            $commenterEmail = htmlspecialchars((string)($cm['commenter_email'] ?? ''));
            $lessonTitle  = htmlspecialchars((string)($cm['lesson_title']  ?? ('Lesson #' . (int)$cm['lesson_id'])));
            $courseTitle  = htmlspecialchars((string)($cm['course_title']  ?? ''));
            $status       = (string)($cm['status'] ?? 'approved');
            $badgeClass   = match($status) {
                'approved' => 'a-badge--success',
                'rejected' => 'a-badge--danger',
                default    => 'a-badge--warning',
            };
            $dateStr = date('M j, Y', strtotime((string)($cm['created_at'] ?? '')));
            $isReply = $cm['parent_id'] !== null;
        ?>
        <tr>
          <td>
            <input
              type="checkbox"
              name="comment_ids[]"
              value="<?= (int)$cm['id'] ?>"
              class="row-check"
            >
          </td>
          <td>
            <div class="a-user-cell" style="gap:.5rem">
              <div class="a-user-avatar" style="width:32px;height:32px;font-size:.75rem;flex-shrink:0">
                <?= strtoupper(mb_substr($commenterName, 0, 2)) ?>
              </div>
              <div>
                <div style="font-weight:600;font-size:.85rem"><?= $commenterName ?></div>
                <div style="font-size:.75rem;color:var(--a-text-muted)"><?= $commenterEmail ?></div>
              </div>
            </div>
          </td>
          <td>
            <div style="font-size:.85rem;font-weight:500"><?= $lessonTitle ?></div>
            <?php if ($courseTitle !== ''): ?>
            <div style="font-size:.75rem;color:var(--a-text-muted)"><?= $courseTitle ?></div>
            <?php endif; ?>
            <?php if ($isReply): ?>
            <span class="a-badge" style="font-size:.68rem;margin-top:.2rem">reply</span>
            <?php endif; ?>
          </td>
          <td style="max-width:280px;font-size:.85rem;color:var(--a-text-muted)">
            <?= $preview ?>
          </td>
          <td>
            <span class="a-badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
          </td>
          <td style="font-size:.8rem;white-space:nowrap"><?= $dateStr ?></td>
          <td>
            <div style="display:flex;gap:.35rem;flex-wrap:wrap">

              <?php if ($status !== 'approved'): ?>
              <form method="post">
                <input type="hidden" name="action"     value="approve_comment">
                <input type="hidden" name="comment_id" value="<?= (int)$cm['id'] ?>">
                <button class="a-btn a-btn--sm a-btn--primary" type="submit" title="Approve">
                  Approve
                </button>
              </form>
              <?php endif; ?>

              <?php if ($status !== 'rejected'): ?>
              <form method="post">
                <input type="hidden" name="action"     value="reject_comment">
                <input type="hidden" name="comment_id" value="<?= (int)$cm['id'] ?>">
                <button
                  class="a-btn a-btn--sm"
                  type="submit"
                  title="Reject"
                  style="background:#fff7ed;color:#d97706;border:1px solid #fed7aa"
                >
                  Reject
                </button>
              </form>
              <?php endif; ?>

              <form method="post" onsubmit="return confirm('Delete this comment and its replies?')">
                <input type="hidden" name="action"     value="delete_comment">
                <input type="hidden" name="comment_id" value="<?= (int)$cm['id'] ?>">
                <button class="a-btn a-btn--danger a-btn--sm" type="submit" title="Delete">
                  Delete
                </button>
              </form>

            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-top:1px solid var(--a-border)">
      <span style="font-size:.8rem;color:var(--a-text-muted)">
        Page <?= $pageNum ?> of <?= $totalPages ?>
        &nbsp;(<?= $totalAll ?> total)
      </span>
      <div style="display:flex;gap:.4rem">
        <?php if ($pageNum > 1): ?>
        <a href="<?= comments_qs(['p' => (string)($pageNum - 1)]) ?>" class="a-btn a-btn--ghost a-btn--sm">&#8592; Prev</a>
        <?php endif; ?>
        <?php
        $start = max(1, $pageNum - 2);
        $end   = min($totalPages, $pageNum + 2);
        for ($pg = $start; $pg <= $end; $pg++):
        ?>
        <a
          href="<?= comments_qs(['p' => (string)$pg]) ?>"
          class="a-btn a-btn--sm <?= $pg === $pageNum ? 'a-btn--primary' : 'a-btn--ghost' ?>"
        ><?= $pg ?></a>
        <?php endfor; ?>
        <?php if ($pageNum < $totalPages): ?>
        <a href="<?= comments_qs(['p' => (string)($pageNum + 1)]) ?>" class="a-btn a-btn--ghost a-btn--sm">Next &#8594;</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /.a-table-card -->
</form>

<script>
// Select-all checkbox logic
const checkAll = document.getElementById('checkAll');
const rowChecks = () => document.querySelectorAll('.row-check');
if (checkAll) {
  checkAll.addEventListener('change', () => {
    rowChecks().forEach(cb => cb.checked = checkAll.checked);
  });
}
</script>

<?php require_once __DIR__ . '/_foot.php'; ?>
