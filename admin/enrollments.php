<?php
if (!defined('BASE')) define('BASE', '');
$admin_active_page = 'enrollments';
$admin_page_title  = 'Enrollments';
require_once __DIR__ . '/_head.php';
require_once __DIR__ . '/../includes/purchases-repo.php';

/* ── Ensure purchases table ──────────────────────────────────────────────── */
ensure_purchases_table($mysqli);

/* ── POST: refund_purchase ───────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'refund_purchase') {
    $refundId = (int)($_POST['purchase_id'] ?? 0);
    if ($refundId > 0) {
        $stmt = $mysqli->prepare("UPDATE purchases SET status = 'refunded' WHERE id = ? AND status != 'refunded'");
        if ($stmt) {
            $stmt->bind_param('i', $refundId);
            $stmt->execute();
            $stmt->close();
        }
        // Optionally reset progress to 0 for that enrollment
        $stmt2 = $mysqli->prepare("UPDATE purchases p JOIN course_progress cp ON cp.client_id = p.client_id AND cp.course_id = p.course_id SET cp.progress_percent = 0 WHERE p.id = ?");
        if ($stmt2) {
            $stmt2->bind_param('i', $refundId);
            $stmt2->execute();
            $stmt2->close();
        }
    }
    // Redirect to avoid re-POST on refresh
    $qs = http_build_query(array_diff_key($_GET, ['action' => '']));
    header('Location: ' . BASE . '/admin/enrollments.php' . ($qs ? '?' . $qs : ''));
    exit;
}

/* ── Stats ───────────────────────────────────────────────────────────────── */
$stats = ['total' => 0, 'revenue' => 0.0, 'this_month' => 0, 'unique_students' => 0, 'refunds' => 0];

$r = $mysqli->query('SELECT COUNT(*) total, COALESCE(SUM(amount),0) revenue, COUNT(DISTINCT client_id) unique_students FROM purchases');
if ($r) {
    $row   = $r->fetch_assoc();
    $stats['total']           = (int)$row['total'];
    $stats['revenue']         = (float)$row['revenue'];
    $stats['unique_students'] = (int)$row['unique_students'];
}

$r = $mysqli->query('SELECT COUNT(*) c FROM purchases WHERE MONTH(purchased_at)=MONTH(NOW()) AND YEAR(purchased_at)=YEAR(NOW())');
if ($r) $stats['this_month'] = (int)($r->fetch_assoc()['c'] ?? 0);

$r = $mysqli->query("SELECT COUNT(*) c FROM purchases WHERE status = 'refunded'");
if ($r) $stats['refunds'] = (int)($r->fetch_assoc()['c'] ?? 0);

/* ── Pagination + search ─────────────────────────────────────────────────── */
$search       = trim($_GET['q']      ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$page_num     = max(1, (int)($_GET['p'] ?? 1));
$per_page     = 20;
$offset       = ($page_num - 1) * $per_page;

$where = '1=1';
if ($search !== '') {
    $esc    = $mysqli->real_escape_string($search);
    $where .= " AND (c.full_name LIKE '%{$esc}%' OR c.email LIKE '%{$esc}%' OR cc.title LIKE '%{$esc}%')";
}
if ($statusFilter !== '') {
    $escStatus = $mysqli->real_escape_string($statusFilter);
    $where    .= " AND p.status='{$escStatus}'";
}

$countRes   = $mysqli->query("SELECT COUNT(*) c FROM purchases p LEFT JOIN clients c ON c.id=p.client_id LEFT JOIN courses_catalog cc ON cc.id=p.course_id WHERE {$where}");
$totalCount = $countRes ? (int)($countRes->fetch_assoc()['c'] ?? 0) : 0;
$totalPages = max(1, (int)ceil($totalCount / $per_page));

$enrollments = [];
$r = $mysqli->query("SELECT p.id, c.full_name, c.email, cc.title AS course_title, cc.category,
                     p.original_amount, p.amount, p.coupon_code, p.discount_percent,
                     p.currency, p.status, p.purchased_at
                     FROM purchases p
                     LEFT JOIN clients c  ON c.id  = p.client_id
                     LEFT JOIN courses_catalog cc ON cc.id = p.course_id
                     WHERE {$where}
                     ORDER BY p.purchased_at DESC
                     LIMIT {$per_page} OFFSET {$offset}");
if ($r) while ($row = $r->fetch_assoc()) $enrollments[] = $row;

/* ── Helper: pagination URL ──────────────────────────────────────────────── */
function enroll_page_url(int $p): string {
    $params = $_GET;
    $params['p'] = $p;
    return BASE . '/admin/enrollments.php?' . http_build_query($params);
}
?>

<!-- ── Page header ──────────────────────────────────────────────────────── -->
<div class="a-page-header">
  <div class="a-page-header-text">
    <h1>Enrollments</h1>
    <p>Track course purchases and student revenue</p>
  </div>
</div>

<!-- ── Stats ─────────────────────────────────────────────────────────────── -->
<div class="a-stats-grid">

  <div class="a-stat-card">
    <div class="a-stat-icon a-stat-icon--indigo">
      <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
        <rect x="8" y="2" width="8" height="4" rx="1"/>
        <path d="M9 12l2 2 4-4"/>
      </svg>
    </div>
    <div class="a-stat-body">
      <div class="a-stat-label">Total Enrollments</div>
      <div class="a-stat-value"><?= number_format($stats['total']) ?></div>
    </div>
  </div>

  <div class="a-stat-card">
    <div class="a-stat-icon a-stat-icon--green">
      <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <line x1="12" y1="1" x2="12" y2="23"/>
        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
      </svg>
    </div>
    <div class="a-stat-body">
      <div class="a-stat-label">Total Revenue</div>
      <div class="a-stat-value">$<?= number_format($stats['revenue'], 2) ?></div>
    </div>
  </div>

  <div class="a-stat-card">
    <div class="a-stat-icon a-stat-icon--amber">
      <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <rect x="3" y="4" width="18" height="18" rx="2"/>
        <line x1="16" y1="2" x2="16" y2="6"/>
        <line x1="8"  y1="2" x2="8"  y2="6"/>
        <line x1="3"  y1="10" x2="21" y2="10"/>
      </svg>
    </div>
    <div class="a-stat-body">
      <div class="a-stat-label">This Month</div>
      <div class="a-stat-value"><?= number_format($stats['this_month']) ?></div>
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
      <div class="a-stat-label">Unique Students</div>
      <div class="a-stat-value"><?= number_format($stats['unique_students']) ?></div>
    </div>
  </div>

  <div class="a-stat-card">
    <div class="a-stat-icon a-stat-icon--danger" style="background:rgba(239,68,68,.12);color:#ef4444">
      <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <polyline points="1 4 1 10 7 10"/>
        <path d="M3.51 15a9 9 0 1 0 .49-4.5"/>
      </svg>
    </div>
    <div class="a-stat-body">
      <div class="a-stat-label">Total Refunds</div>
      <div class="a-stat-value" style="color:#ef4444"><?= number_format($stats['refunds']) ?></div>
    </div>
  </div>

</div>

<!-- ── Table Card ─────────────────────────────────────────────────────────── -->
<div class="a-table-card">

  <!-- Search + filter bar -->
  <form method="GET" action="<?= BASE ?>/admin/enrollments.php">
    <div class="a-bar">
      <div class="a-bar-search-wrap">
        <span class="a-bar-search-icon" aria-hidden="true">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
        </span>
        <input class="a-bar-input" type="search" name="q"
               value="<?= htmlspecialchars($search, ENT_QUOTES) ?>"
               placeholder="Search student or course…" aria-label="Search enrollments">
      </div>

      <select name="status" class="a-bar-input" style="max-width:160px;padding-left:.9rem"
              onchange="this.form.submit()" aria-label="Filter by status">
        <option value="">All statuses</option>
        <?php foreach (['completed','pending','refunded','failed'] as $s): ?>
        <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="a-btn a-btn--primary a-btn--sm">Search</button>
      <?php if ($search !== '' || $statusFilter !== ''): ?>
      <a href="<?= BASE ?>/admin/enrollments.php" class="a-btn a-btn--ghost a-btn--sm">Clear</a>
      <?php endif; ?>

      <span class="a-pagination-info" style="margin-left:auto">
        <?= number_format($totalCount) ?> enrollment<?= $totalCount !== 1 ? 's' : '' ?>
      </span>
    </div>
  </form>

  <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>Student</th>
          <th>Course</th>
          <th>Category</th>
          <th>Amount</th>
          <th>Coupon</th>
          <th>Status</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($enrollments)): ?>
        <tr>
          <td colspan="8" style="text-align:center;padding:2.5rem;color:var(--a-text-muted)">
            No enrollments found<?= ($search !== '' || $statusFilter !== '') ? ' for current filters' : '' ?>.
          </td>
        </tr>
        <?php else: ?>
        <?php foreach ($enrollments as $e): ?>
        <?php
        $name    = $e['full_name'] ?? 'Unknown';
        $email   = $e['email']     ?? '';
        $initial = strtoupper(substr(trim($name), 0, 1)) ?: '?';
        $statusBadge = match($e['status'] ?? 'completed') {
            'completed' => 'a-badge--success',
            'pending'   => 'a-badge--warning',
            'refunded'  => 'a-badge--danger',
            'failed'    => 'a-badge--danger',
            default     => 'a-badge--muted',
        };
        $isRefunded = ($e['status'] ?? '') === 'refunded';
        ?>
        <tr>
          <td>
            <div class="a-user-cell">
              <div class="a-user-avatar"><?= htmlspecialchars($initial) ?></div>
              <div>
                <div class="a-user-name"><?= htmlspecialchars($name) ?></div>
                <div class="a-user-email"><?= htmlspecialchars($email) ?></div>
              </div>
            </div>
          </td>
          <td>
            <span style="font-weight:500;color:var(--a-text)">
              <?= htmlspecialchars($e['course_title'] ?? '—') ?>
            </span>
          </td>
          <td><?= htmlspecialchars($e['category'] ?? '—') ?></td>
          <td style="font-weight:600">
            <?= htmlspecialchars($e['currency'] ?? 'USD') ?>
            <?= number_format((float)($e['amount'] ?? 0), 2) ?>
          </td>
          <td>
            <?php if (!empty($e['coupon_code'])): ?>
              <span class="a-badge a-badge--warning"><?= htmlspecialchars((string)$e['coupon_code']) ?> (-<?= number_format((float)($e['discount_percent'] ?? 0), 0) ?>%)</span>
            <?php else: ?>
              <span style="color:var(--a-text-muted)">—</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="a-badge <?= $statusBadge ?>">
              <?= htmlspecialchars(ucfirst($e['status'] ?? 'completed')) ?>
            </span>
          </td>
          <td style="color:var(--a-text-muted);font-size:.82rem;white-space:nowrap">
            <?= htmlspecialchars(date('M j, Y', strtotime($e['purchased_at'] ?? 'now'))) ?>
          </td>
          <td>
            <?php if ($isRefunded): ?>
              <button class="a-btn a-btn--danger a-btn--sm" disabled style="opacity:.45;cursor:default">Refunded</button>
            <?php else: ?>
              <button class="a-btn a-btn--danger a-btn--sm"
                      onclick="openRefundModal(<?= (int)$e['id'] ?>, <?= htmlspecialchars(json_encode(($e['full_name'] ?? 'Unknown') . ' — ' . ($e['course_title'] ?? '')), ENT_QUOTES) ?>)">
                Refund
              </button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div class="a-pagination">
    <?php if ($page_num > 1): ?>
      <a href="<?= enroll_page_url($page_num - 1) ?>" class="a-btn a-btn--ghost a-btn--sm">&laquo; Prev</a>
    <?php endif; ?>

    <?php
    $start = max(1, $page_num - 2);
    $end   = min($totalPages, $page_num + 2);
    for ($p = $start; $p <= $end; $p++): ?>
    <a href="<?= enroll_page_url($p) ?>"
       class="a-btn a-btn--sm <?= $p === $page_num ? 'a-btn--primary' : 'a-btn--ghost' ?>">
      <?= $p ?>
    </a>
    <?php endfor; ?>

    <?php if ($page_num < $totalPages): ?>
      <a href="<?= enroll_page_url($page_num + 1) ?>" class="a-btn a-btn--ghost a-btn--sm">Next &raquo;</a>
    <?php endif; ?>

    <span class="a-pagination-info">
      Page <?= $page_num ?> of <?= $totalPages ?>
    </span>
  </div>
  <?php endif; ?>

</div><!-- /.a-table-card -->

<!-- ── Refund Confirmation Modal ──────────────────────────────────────────── -->
<div class="a-modal-bg" id="refundModal" style="display:none" onclick="if(event.target===this)closeRefundModal()">
  <div class="a-modal" style="max-width:420px;width:100%">
    <h2 style="font-size:1.1rem;font-weight:700;margin:0 0 .75rem;display:flex;align-items:center;gap:.5rem">
      <svg width="18" height="18" fill="none" stroke="#ef4444" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.5"/>
      </svg>
      Confirm Refund
    </h2>
    <p style="color:var(--a-text-muted);margin-bottom:1.5rem">
      Are you sure you want to mark this purchase as <strong style="color:#ef4444">Refunded</strong>?<br>
      <span id="refundDesc" style="font-size:.88rem;color:var(--a-text)"></span>
    </p>
    <form method="POST" action="<?= BASE ?>/admin/enrollments.php<?= http_build_query($_GET) !== '' ? '?' . http_build_query($_GET) : '' ?>">
      <input type="hidden" name="action"      value="refund_purchase">
      <input type="hidden" name="purchase_id" id="refundPurchaseId" value="">
      <div style="display:flex;gap:.75rem;justify-content:flex-end">
        <button type="button" class="a-btn a-btn--ghost" onclick="closeRefundModal()">Cancel</button>
        <button type="submit" class="a-btn a-btn--danger">Yes, Refund</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRefundModal(purchaseId, desc) {
  document.getElementById('refundPurchaseId').value = purchaseId;
  document.getElementById('refundDesc').textContent = desc;
  document.getElementById('refundModal').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeRefundModal() {
  document.getElementById('refundModal').style.display = 'none';
  document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeRefundModal();
});
</script>

<?php require_once __DIR__ . '/_foot.php'; ?>
