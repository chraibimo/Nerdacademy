<?php
if (!defined('BASE')) define('BASE', '');

$admin_active_page = 'dashboard';
$admin_page_title  = 'Dashboard';

require_once __DIR__ . '/_head.php';
require_once __DIR__ . '/../includes/reviews-repo.php';
// _head.php has set $user and $mysqli, run the auth check, and opened the HTML layout.

/* =========================================================
   DB queries (analytics gated by permission)
   ========================================================= */
$totalUsers       = 0;
$totalCourses     = 0;
$totalEnrollments = 0;
$totalRevenue     = 0.0;
$newUsersThisMonth = 0;
$pendingReviews = 0;

if (auth_has_permission($user, 'view_analytics')) {
    $r = $mysqli->query('SELECT COUNT(*) c FROM clients');
    if ($r) { $row = $r->fetch_assoc(); $totalUsers = (int)($row['c'] ?? 0); }

    $r = $mysqli->query('SELECT COUNT(*) c FROM courses_catalog WHERE is_active = 1');
    if ($r) { $row = $r->fetch_assoc(); $totalCourses = (int)($row['c'] ?? 0); }

    $r = $mysqli->query('SELECT COUNT(*) c, COALESCE(SUM(amount), 0) s FROM purchases');
    if ($r) {
        $row = $r->fetch_assoc();
        $totalEnrollments = (int)($row['c'] ?? 0);
        $totalRevenue     = (float)($row['s'] ?? 0);
    }

    $r = $mysqli->query('SELECT COUNT(*) c FROM clients WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())');
    if ($r) { $row = $r->fetch_assoc(); $newUsersThisMonth = (int)($row['c'] ?? 0); }
}

  ensure_course_reviews_table($mysqli);
  $r = $mysqli->query("SELECT COUNT(*) c FROM course_reviews WHERE status = 'pending'");
  if ($r) { $row = $r->fetch_assoc(); $pendingReviews = (int)($row['c'] ?? 0); }

/* ── Recent 5 sign-ups ──────────────────────────────────────────────────── */
$recentUsers = [];
$r = $mysqli->query('SELECT id, full_name, email, role, is_admin, created_at, account_status
                     FROM clients ORDER BY created_at DESC LIMIT 5');
if ($r) { while ($row = $r->fetch_assoc()) $recentUsers[] = $row; }

/* ── Recent 5 enrollments ───────────────────────────────────────────────── */
$recentEnrollments = [];
$r = $mysqli->query('SELECT p.id, c.full_name, cc.title AS course_title,
                            p.amount, p.purchased_at, p.status
                     FROM purchases p
                     LEFT JOIN clients c        ON c.id  = p.client_id
                     LEFT JOIN courses_catalog cc ON cc.id = p.course_id
                     ORDER BY p.purchased_at DESC LIMIT 5');
if ($r) { while ($row = $r->fetch_assoc()) $recentEnrollments[] = $row; }

/* ── Monthly enrollment trend (last 6 months) ───────────────────────────── */
$chartData = [];
for ($i = 5; $i >= 0; $i--) {
    $r = $mysqli->query(
        "SELECT COUNT(*) c, COALESCE(SUM(amount), 0) s
         FROM purchases
         WHERE YEAR(purchased_at)  = YEAR(DATE_SUB(NOW(),  INTERVAL {$i} MONTH))
           AND MONTH(purchased_at) = MONTH(DATE_SUB(NOW(), INTERVAL {$i} MONTH))"
    );
    $row        = $r ? $r->fetch_assoc() : ['c' => 0, 's' => 0];
    $chartData[] = [
        'count'   => (int)($row['c'] ?? 0),
        'revenue' => (float)($row['s'] ?? 0),
        'month'   => date('M', strtotime("-{$i} months")),
    ];
}

/* ── Helper: avatar background color by name hash ───────────────────────── */
$avatarColors = ['#6366f1','#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6'];
function avatar_color(string $name, array $colors): string {
    $idx = abs(crc32($name)) % count($colors);
    return $colors[$idx];
}
?>

<!-- =========================================================
     Page header
     ========================================================= -->
<div class="a-page-header">
  <div>
    <h1>Dashboard</h1>
    <p>Welcome back, <?php echo htmlspecialchars((string)($user['full_name'] ?: 'Admin')); ?>. Here's what's happening with NerdAcademy.</p>
  </div>
  <div class="a-page-actions">
    <a href="<?php echo BASE; ?>/admin/courses.php?new=1" class="a-btn a-btn--primary">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      Add Course
    </a>
  </div>
</div>

<!-- =========================================================
     Stats grid
     ========================================================= -->
<div class="a-stats-grid">

  <!-- Total Users -->
  <div class="a-stat-card">
    <div class="a-stat-icon a-stat-icon--indigo" aria-hidden="true">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
      </svg>
    </div>
    <div class="a-stat-body">
      <div class="a-stat-label">Total Users</div>
      <div class="a-stat-value"><?php echo number_format($totalUsers); ?></div>
      <?php if ($newUsersThisMonth > 0): ?>
      <div class="a-stat-change">+<?php echo $newUsersThisMonth; ?> this month</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Active Courses -->
  <div class="a-stat-card">
    <div class="a-stat-icon a-stat-icon--green" aria-hidden="true">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/>
        <path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/>
      </svg>
    </div>
    <div class="a-stat-body">
      <div class="a-stat-label">Active Courses</div>
      <div class="a-stat-value"><?php echo number_format($totalCourses); ?></div>
    </div>
  </div>

  <!-- Total Enrollments -->
  <div class="a-stat-card">
    <div class="a-stat-icon a-stat-icon--blue" aria-hidden="true">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2"/>
        <rect x="8" y="2" width="8" height="4" rx="1"/>
        <path d="M9 12l2 2 4-4"/>
      </svg>
    </div>
    <div class="a-stat-body">
      <div class="a-stat-label">Total Enrollments</div>
      <div class="a-stat-value"><?php echo number_format($totalEnrollments); ?></div>
    </div>
  </div>

  <!-- Revenue -->
  <div class="a-stat-card">
    <div class="a-stat-icon a-stat-icon--amber" aria-hidden="true">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <line x1="12" y1="1" x2="12" y2="23"/>
        <path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
      </svg>
    </div>
    <div class="a-stat-body">
      <div class="a-stat-label">Revenue</div>
      <div class="a-stat-value">$<?php echo number_format($totalRevenue, 0); ?></div>
    </div>
  </div>

  <!-- Pending Reviews -->
  <div class="a-stat-card">
    <div class="a-stat-icon a-stat-icon--danger" aria-hidden="true">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
      </svg>
    </div>
    <div class="a-stat-body">
      <div class="a-stat-label">Pending Reviews</div>
      <div class="a-stat-value"><?php echo number_format($pendingReviews); ?></div>
    </div>
  </div>

</div>
<!-- /.a-stats-grid -->

<div class="a-card" style="margin-bottom:1.5rem">
  <div class="a-card-head"><h3>Quick Management</h3></div>
  <div class="a-card-body" style="display:flex;gap:.65rem;flex-wrap:wrap">
    <a href="<?php echo BASE; ?>/admin/users.php" class="a-btn a-btn--ghost">Manage Users</a>
    <a href="<?php echo BASE; ?>/admin/courses.php" class="a-btn a-btn--ghost">Manage Courses</a>
    <a href="<?php echo BASE; ?>/admin/coupons.php" class="a-btn a-btn--ghost">Manage Coupons</a>
    <a href="<?php echo BASE; ?>/admin/reviews.php" class="a-btn a-btn--ghost">Manage Reviews</a>
    <a href="<?php echo BASE; ?>/admin/enrollments.php" class="a-btn a-btn--ghost">Manage Enrollments</a>
    <a href="<?php echo BASE; ?>/admin/reports.php" class="a-btn a-btn--ghost">Open Reports</a>
  </div>
</div>

<!-- =========================================================
     Enrollment trend chart
     ========================================================= -->
<div class="a-card" style="margin-bottom:1.5rem">
  <div class="a-card-head">
    <h3>Enrollment Trend (Last 6 Months)</h3>
    <span style="font-size:.82rem;color:var(--a-text-muted)">Enrollments per month</span>
  </div>
  <div class="a-card-body">
    <div class="a-chart-wrap">
      <canvas id="enrollChart" style="width:100%;height:100%"></canvas>
    </div>
  </div>
</div>

<!-- =========================================================
     Two-column: recent sign-ups + recent enrollments
     ========================================================= -->
<div class="a-two-col">

  <!-- Recent Sign-ups -->
  <div class="a-table-card">
    <div class="a-card-head">
      <h3>Recent Sign-ups</h3>
      <a href="<?php echo BASE; ?>/admin/users.php" class="a-btn a-btn--ghost a-btn--sm">View all</a>
    </div>
    <?php if (empty($recentUsers)): ?>
      <div style="padding:2rem 1.5rem;text-align:center;color:var(--a-text-muted);font-size:.875rem">No users yet.</div>
    <?php else: ?>
    <div style="overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Role</th>
            <th>Status</th>
            <th>Joined</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentUsers as $u): ?>
            <?php
              $uRole   = strtolower((string)($u['role'] ?: ((int)$u['is_admin'] ? 'admin' : 'user')));
              $uStatus = strtolower((string)($u['account_status'] ?? 'active'));
              $roleBadge = match($uRole) {
                  'admin' => 'a-badge--danger',
                  'agent' => 'a-badge--warning',
                  default => 'a-badge--muted',
              };
              $statusBadge = ($uStatus === 'active' || $uStatus === 'pending_verification')
                  ? 'a-badge--success' : 'a-badge--danger';
              $statusLabel = $uStatus === 'pending_verification' ? 'pending' : $uStatus;
              $initials = strtoupper(substr(trim((string)($u['full_name'] ?: $u['email'])), 0, 2));
              $bgColor  = avatar_color((string)($u['full_name'] ?: $u['email']), $avatarColors);
            ?>
            <tr>
              <td>
                <div class="a-user-cell">
                  <div class="a-user-avatar" style="background:<?php echo $bgColor; ?>;color:#fff"><?php echo htmlspecialchars($initials); ?></div>
                  <div>
                    <div class="a-user-name"><?php echo htmlspecialchars((string)$u['full_name']); ?></div>
                    <div class="a-user-email"><?php echo htmlspecialchars((string)$u['email']); ?></div>
                  </div>
                </div>
              </td>
              <td><span class="a-badge <?php echo $roleBadge; ?>"><?php echo htmlspecialchars($uRole); ?></span></td>
              <td><span class="a-badge <?php echo $statusBadge; ?>"><?php echo htmlspecialchars($statusLabel); ?></span></td>
              <td style="white-space:nowrap;font-size:.8rem;color:var(--a-text-muted)"><?php echo htmlspecialchars(date('M j, Y', strtotime((string)$u['created_at']))); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Recent Enrollments -->
  <div class="a-table-card">
    <div class="a-card-head">
      <h3>Recent Enrollments</h3>
      <a href="<?php echo BASE; ?>/admin/enrollments.php" class="a-btn a-btn--ghost a-btn--sm">View all</a>
    </div>
    <?php if (empty($recentEnrollments)): ?>
      <div style="padding:2rem 1.5rem;text-align:center;color:var(--a-text-muted);font-size:.875rem">No enrollments yet.</div>
    <?php else: ?>
    <div style="overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th>User</th>
            <th>Course</th>
            <th>Amount</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentEnrollments as $e): ?>
            <?php
              $eName    = (string)($e['full_name'] ?? 'Unknown');
              $eCourse  = (string)($e['course_title'] ?? 'Unknown');
              $initials = strtoupper(substr($eName, 0, 2));
              $bgColor  = avatar_color($eName, $avatarColors);
            ?>
            <tr>
              <td>
                <div class="a-user-cell">
                  <div class="a-user-avatar" style="background:<?php echo $bgColor; ?>;color:#fff;font-size:.73rem"><?php echo htmlspecialchars($initials); ?></div>
                  <div class="a-user-name"><?php echo htmlspecialchars($eName); ?></div>
                </div>
              </td>
              <td style="font-size:.82rem;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?php echo htmlspecialchars($eCourse); ?>
              </td>
              <td style="font-weight:600;color:var(--a-text)">$<?php echo number_format((float)$e['amount'], 2); ?></td>
              <td style="white-space:nowrap;font-size:.8rem;color:var(--a-text-muted)"><?php echo htmlspecialchars(date('M j, Y', strtotime((string)$e['purchased_at']))); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div>
<!-- /.a-two-col -->

<!-- =========================================================
     Chart script (vanilla canvas, no dependencies)
     ========================================================= -->
<script>
(function () {
  var raw = <?php echo json_encode($chartData, JSON_UNESCAPED_UNICODE); ?>;
  var canvas = document.getElementById('enrollChart');
  if (!canvas || !canvas.getContext) return;

  var ctx = canvas.getContext('2d');
  var dpr = window.devicePixelRatio || 1;

  function draw() {
    var W = canvas.parentElement.offsetWidth;
    var H = canvas.parentElement.offsetHeight || 220;
    canvas.width  = W * dpr;
    canvas.height = H * dpr;
    canvas.style.width  = W + 'px';
    canvas.style.height = H + 'px';
    ctx.scale(dpr, dpr);

    var pad = { top: 20, right: 20, bottom: 40, left: 48 };
    var chartW = W - pad.left - pad.right;
    var chartH = H - pad.top  - pad.bottom;

    var counts   = raw.map(function (d) { return d.count; });
    var labels   = raw.map(function (d) { return d.month; });
    var maxVal   = Math.max.apply(null, counts.concat([1]));
    var n        = counts.length;

    /* ── helpers ── */
    function xPos(i) { return pad.left + (i / (n - 1)) * chartW; }
    function yPos(v) { return pad.top  + chartH - (v / maxVal) * chartH; }

    /* ── background ── */
    ctx.clearRect(0, 0, W, H);

    /* ── grid lines + Y labels ── */
    var gridSteps = 4;
    ctx.strokeStyle = '#e5e7eb';
    ctx.lineWidth   = 1;
    ctx.fillStyle   = '#9ca3af';
    ctx.font        = '11px Inter, system-ui, sans-serif';
    ctx.textAlign   = 'right';
    for (var g = 0; g <= gridSteps; g++) {
      var gv = (maxVal / gridSteps) * g;
      var gy = yPos(gv);
      ctx.beginPath();
      ctx.moveTo(pad.left, gy);
      ctx.lineTo(pad.left + chartW, gy);
      ctx.stroke();
      ctx.fillText(Math.round(gv).toString(), pad.left - 6, gy + 4);
    }

    /* ── filled area under line ── */
    var grad = ctx.createLinearGradient(0, pad.top, 0, pad.top + chartH);
    grad.addColorStop(0,   'rgba(99,102,241,0.25)');
    grad.addColorStop(1,   'rgba(99,102,241,0)');

    ctx.beginPath();
    ctx.moveTo(xPos(0), yPos(counts[0]));
    for (var i = 1; i < n; i++) {
      /* smooth bezier curve */
      var x0 = xPos(i - 1), y0 = yPos(counts[i - 1]);
      var x1 = xPos(i),     y1 = yPos(counts[i]);
      var cpx = (x0 + x1) / 2;
      ctx.bezierCurveTo(cpx, y0, cpx, y1, x1, y1);
    }
    ctx.lineTo(xPos(n - 1), pad.top + chartH);
    ctx.lineTo(xPos(0),     pad.top + chartH);
    ctx.closePath();
    ctx.fillStyle = grad;
    ctx.fill();

    /* ── line ── */
    ctx.beginPath();
    ctx.moveTo(xPos(0), yPos(counts[0]));
    for (var j = 1; j < n; j++) {
      var lx0 = xPos(j - 1), ly0 = yPos(counts[j - 1]);
      var lx1 = xPos(j),     ly1 = yPos(counts[j]);
      var lcpx = (lx0 + lx1) / 2;
      ctx.bezierCurveTo(lcpx, ly0, lcpx, ly1, lx1, ly1);
    }
    ctx.strokeStyle  = '#6366f1';
    ctx.lineWidth    = 2.5;
    ctx.lineJoin     = 'round';
    ctx.stroke();

    /* ── dots ── */
    for (var k = 0; k < n; k++) {
      ctx.beginPath();
      ctx.arc(xPos(k), yPos(counts[k]), 4, 0, Math.PI * 2);
      ctx.fillStyle   = '#6366f1';
      ctx.fill();
      ctx.strokeStyle = '#fff';
      ctx.lineWidth   = 2;
      ctx.stroke();
    }

    /* ── X axis labels ── */
    ctx.fillStyle  = '#9ca3af';
    ctx.textAlign  = 'center';
    ctx.font       = '11px Inter, system-ui, sans-serif';
    for (var l = 0; l < n; l++) {
      ctx.fillText(labels[l], xPos(l), H - 10);
    }
  }

  draw();

  var resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(draw, 120);
  });
})();
</script>

<?php require_once __DIR__ . '/_foot.php'; ?>
