<?php
if (!defined('BASE')) define('BASE', '');

$admin_active_page = 'reviews';
$admin_page_title = 'Reviews';

require_once __DIR__ . '/_head.php';
require_once __DIR__ . '/../includes/reviews-repo.php';

ensure_course_reviews_table($mysqli);
seed_demo_reviews($mysqli);

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && auth_has_permission($user, 'manage_courses')) {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add_review') {
        $courseId = (int)($_POST['course_id'] ?? 0);
        $reviewerName = trim((string)($_POST['reviewer_name'] ?? ''));
        $reviewerEmail = trim((string)($_POST['reviewer_email'] ?? ''));
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $reviewText = trim((string)($_POST['review_text'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'approved'));
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $status = 'pending';
        }

        if ($courseId <= 0 || $reviewerName === '' || $reviewText === '') {
            $message = 'Course, reviewer name, and review text are required.';
            $messageType = 'error';
        } else {
            $stmt = $mysqli->prepare('INSERT INTO course_reviews (course_id, reviewer_name, reviewer_email, rating, review_text, status) VALUES (?, ?, ?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param('ississ', $courseId, $reviewerName, $reviewerEmail, $rating, $reviewText, $status);
                if ($stmt->execute()) {
                    $message = 'Review created successfully.';
                } else {
                    $message = 'Failed to create review: ' . $stmt->error;
                    $messageType = 'error';
                }
                $stmt->close();
            }
        }
    }

    if ($action === 'update_status') {
        $reviewId = (int)($_POST['review_id'] ?? 0);
        $status = trim((string)($_POST['status'] ?? 'pending'));
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $status = 'pending';
        }
        if ($reviewId > 0) {
            $stmt = $mysqli->prepare('UPDATE course_reviews SET status = ?, updated_at = NOW() WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('si', $status, $reviewId);
                $stmt->execute();
                $stmt->close();
                $message = 'Review status updated.';
            }
        }
    }

    if ($action === 'delete_review') {
        $reviewId = (int)($_POST['review_id'] ?? 0);
        if ($reviewId > 0) {
            $stmt = $mysqli->prepare('DELETE FROM course_reviews WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $reviewId);
                $stmt->execute();
                $stmt->close();
                $message = 'Review deleted.';
            }
        }
    }
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));
$where = '1=1';
if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
    $where .= " AND cr.status='" . $mysqli->real_escape_string($statusFilter) . "'";
}
if ($search !== '') {
    $like = '%' . $mysqli->real_escape_string($search) . '%';
    $where .= " AND (cr.reviewer_name LIKE '$like' OR cr.review_text LIKE '$like' OR cc.title LIKE '$like')";
}

$stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$r = $mysqli->query("SELECT COUNT(*) total, SUM(status='pending') pending, SUM(status='approved') approved, SUM(status='rejected') rejected FROM course_reviews");
if ($r) {
    $row = $r->fetch_assoc();
    $stats['total'] = (int)($row['total'] ?? 0);
    $stats['pending'] = (int)($row['pending'] ?? 0);
    $stats['approved'] = (int)($row['approved'] ?? 0);
    $stats['rejected'] = (int)($row['rejected'] ?? 0);
}

$reviews = [];
$q = $mysqli->query("SELECT cr.id, cr.course_id, cr.reviewer_name, cr.reviewer_email, cr.rating, cr.review_text, cr.status, cr.created_at, cc.title AS course_title
                     FROM course_reviews cr
                     LEFT JOIN courses_catalog cc ON cc.id = cr.course_id
                     WHERE $where
                     ORDER BY cr.created_at DESC");
if ($q) {
    while ($row = $q->fetch_assoc()) {
        $reviews[] = $row;
    }
}

$courses = [];
$cr = $mysqli->query('SELECT id, title FROM courses_catalog WHERE is_active = 1 ORDER BY title ASC');
if ($cr) {
    while ($row = $cr->fetch_assoc()) {
        $courses[] = $row;
    }
}
?>

<div class="a-page-header">
  <div>
    <h1>Reviews Management</h1>
    <p>Moderate, publish, and organize course reviews.</p>
  </div>
</div>

<div class="a-stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1rem">
  <div class="a-stat-card"><div class="a-stat-value"><?php echo $stats['total']; ?></div><div class="a-stat-label">Total</div></div>
  <div class="a-stat-card"><div class="a-stat-value"><?php echo $stats['pending']; ?></div><div class="a-stat-label">Pending</div></div>
  <div class="a-stat-card"><div class="a-stat-value"><?php echo $stats['approved']; ?></div><div class="a-stat-label">Approved</div></div>
  <div class="a-stat-card"><div class="a-stat-value"><?php echo $stats['rejected']; ?></div><div class="a-stat-label">Rejected</div></div>
</div>

<?php if ($message !== ''): ?>
<div style="padding:.85rem 1.1rem;border-radius:8px;margin-bottom:1rem;font-size:.875rem;font-weight:500;background:<?php echo $messageType === 'error' ? '#fef2f2' : '#ecfdf5'; ?>;color:<?php echo $messageType === 'error' ? '#b91c1c' : '#15803d'; ?>;border:1px solid <?php echo $messageType === 'error' ? '#fecaca' : '#bbf7d0'; ?>;">
  <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="a-card" style="margin-bottom:1rem">
  <div class="a-card-head"><h3>Add Review</h3></div>
  <div class="a-card-body">
    <form method="post" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem">
      <input type="hidden" name="action" value="add_review">
      <div class="a-form-group">
        <label>Course</label>
        <select name="course_id" class="a-bar-input" style="padding:.55rem .75rem" required>
          <option value="">Select course</option>
          <?php foreach ($courses as $c): ?>
          <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars((string)$c['title']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="a-form-group">
        <label>Reviewer Name</label>
        <input name="reviewer_name" class="a-bar-input" style="padding:.55rem .75rem" required>
      </div>
      <div class="a-form-group">
        <label>Reviewer Email</label>
        <input name="reviewer_email" type="email" class="a-bar-input" style="padding:.55rem .75rem">
      </div>
      <div class="a-form-group">
        <label>Rating</label>
        <input name="rating" type="number" min="1" max="5" value="5" class="a-bar-input" style="padding:.55rem .75rem">
      </div>
      <div class="a-form-group">
        <label>Status</label>
        <select name="status" class="a-bar-input" style="padding:.55rem .75rem">
          <option value="pending">pending</option>
          <option value="approved" selected>approved</option>
          <option value="rejected">rejected</option>
        </select>
      </div>
      <div class="a-form-group" style="grid-column:1 / -1">
        <label>Review Text</label>
        <textarea name="review_text" rows="3" class="a-bar-input" style="padding:.65rem .75rem" required></textarea>
      </div>
      <div style="grid-column:1 / -1"><button class="a-btn a-btn--primary" type="submit">Create Review</button></div>
    </form>
  </div>
</div>

<div class="a-table-card">
  <div class="a-bar">
    <form method="get" style="display:flex;gap:.6rem;flex-wrap:wrap;width:100%">
      <input name="q" value="<?php echo htmlspecialchars($search); ?>" class="a-bar-input" style="max-width:280px" placeholder="Search review or course">
      <select name="status" class="a-bar-input" style="max-width:170px">
        <option value="">All statuses</option>
        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>pending</option>
        <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>approved</option>
        <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>rejected</option>
      </select>
      <button class="a-btn a-btn--primary a-btn--sm" type="submit">Filter</button>
    </form>
  </div>
  <table>
    <thead>
      <tr>
        <th>Reviewer</th>
        <th>Course</th>
        <th>Rating</th>
        <th>Review</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($reviews)): ?>
      <tr><td colspan="6" style="padding:1.5rem;text-align:center;color:var(--a-text-muted)">No reviews found.</td></tr>
      <?php else: ?>
      <?php foreach ($reviews as $rv): ?>
      <tr>
        <td>
          <strong><?php echo htmlspecialchars((string)$rv['reviewer_name']); ?></strong><br>
          <span style="font-size:.78rem;color:var(--a-text-muted)"><?php echo htmlspecialchars((string)($rv['reviewer_email'] ?? '')); ?></span>
        </td>
        <td><?php echo htmlspecialchars((string)($rv['course_title'] ?? ('Course #' . (int)$rv['course_id']))); ?></td>
        <td><?php echo str_repeat('★', (int)$rv['rating']); ?></td>
        <td style="max-width:340px"><?php echo htmlspecialchars((string)$rv['review_text']); ?></td>
        <td><span class="a-badge <?php echo $rv['status'] === 'approved' ? 'a-badge--success' : ($rv['status'] === 'rejected' ? 'a-badge--danger' : 'a-badge--warning'); ?>"><?php echo htmlspecialchars((string)$rv['status']); ?></span></td>
        <td>
          <div style="display:flex;gap:.4rem;flex-wrap:wrap">
            <form method="post">
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="review_id" value="<?php echo (int)$rv['id']; ?>">
              <input type="hidden" name="status" value="approved">
              <button class="a-btn a-btn--sm a-btn--success" type="submit">Approve</button>
            </form>
            <form method="post">
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="review_id" value="<?php echo (int)$rv['id']; ?>">
              <input type="hidden" name="status" value="rejected">
              <button class="a-btn a-btn--sm" style="background:#fff7ed;color:#d97706;border:1px solid #fed7aa" type="submit">Reject</button>
            </form>
            <form method="post" onsubmit="return confirm('Delete this review?')">
              <input type="hidden" name="action" value="delete_review">
              <input type="hidden" name="review_id" value="<?php echo (int)$rv['id']; ?>">
              <button class="a-btn a-btn--danger a-btn--sm" type="submit">Delete</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/_foot.php'; ?>
