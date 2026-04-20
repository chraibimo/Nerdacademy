<?php
if (!defined('BASE')) define('BASE', '');
$admin_active_page = 'reports';
$admin_page_title  = 'Reports';
require_once __DIR__ . '/_head.php';

/* ── Monthly stats for last 12 months ───────────────────────────────────── */
$monthlyData = [];
for ($i = 11; $i >= 0; $i--) {
    $r = $mysqli->query("SELECT COUNT(*) signups FROM clients
                         WHERE YEAR(created_at)  = YEAR(DATE_SUB(NOW(), INTERVAL {$i} MONTH))
                           AND MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL {$i} MONTH))");
    $row  = $r ? $r->fetch_assoc() : ['signups' => 0];

    $r2   = $mysqli->query("SELECT COUNT(*) enrolls, COALESCE(SUM(amount),0) rev FROM purchases
                            WHERE YEAR(purchased_at)  = YEAR(DATE_SUB(NOW(), INTERVAL {$i} MONTH))
                              AND MONTH(purchased_at) = MONTH(DATE_SUB(NOW(), INTERVAL {$i} MONTH))");
    $row2 = $r2 ? $r2->fetch_assoc() : ['enrolls' => 0, 'rev' => 0];

    $monthlyData[] = [
        'month'       => date('M Y', strtotime("-{$i} months")),
        'short'       => date('M',   strtotime("-{$i} months")),
        'signups'     => (int)$row['signups'],
        'enrollments' => (int)$row2['enrolls'],
        'revenue'     => (float)$row2['rev'],
    ];
}

/* ── Top courses ─────────────────────────────────────────────────────────── */
$topCourses = [];
$r = $mysqli->query('SELECT cc.title, cc.category, COUNT(p.id) count, COALESCE(SUM(p.amount),0) revenue
                     FROM purchases p
                     LEFT JOIN courses_catalog cc ON cc.id = p.course_id
                     GROUP BY p.course_id
                     ORDER BY count DESC
                     LIMIT 5');
if ($r) while ($row = $r->fetch_assoc()) $topCourses[] = $row;

/* ── Overall stats ───────────────────────────────────────────────────────── */
$overview = ['users' => 0, 'courses' => 0, 'enrollments' => 0, 'revenue' => 0.0];
$r = $mysqli->query('SELECT COUNT(*) c FROM clients');
if ($r) $overview['users'] = (int)$r->fetch_assoc()['c'];

$r = $mysqli->query('SELECT COUNT(*) c FROM courses_catalog WHERE is_active=1');
if ($r) $overview['courses'] = (int)$r->fetch_assoc()['c'];

$r = $mysqli->query('SELECT COUNT(*) c, COALESCE(SUM(amount),0) s FROM purchases');
if ($r) { $row = $r->fetch_assoc(); $overview['enrollments'] = (int)$row['c']; $overview['revenue'] = (float)$row['s']; }

/* ── Chart data (JSON for JS) ────────────────────────────────────────────── */
$chartLabels    = json_encode(array_column($monthlyData, 'short'));
$chartSignups   = json_encode(array_column($monthlyData, 'signups'));
$chartRevenue   = json_encode(array_column($monthlyData, 'revenue'));
?>

<!-- ── Page header ──────────────────────────────────────────────────────── -->
<div class="a-page-header">
  <div class="a-page-header-text">
    <h1>Reports &amp; Analytics</h1>
    <p>Platform performance overview — last 12 months</p>
  </div>
</div>

<!-- ── Summary stats ─────────────────────────────────────────────────────── -->
<div class="a-stats-grid">

  <div class="a-stat-card">
    <div class="a-stat-icon a-stat-icon--indigo">
      <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
      </svg>
    </div>
    <div class="a-stat-body">
      <div class="a-stat-label">Total Users</div>
      <div class="a-stat-value"><?= number_format($overview['users']) ?></div>
    </div>
  </div>

  <div class="a-stat-card">
    <div class="a-stat-icon a-stat-icon--blue">
      <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
      </svg>
    </div>
    <div class="a-stat-body">
      <div class="a-stat-label">Active Courses</div>
      <div class="a-stat-value"><?= number_format($overview['courses']) ?></div>
    </div>
  </div>

  <div class="a-stat-card">
    <div class="a-stat-icon a-stat-icon--amber">
      <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
        <rect x="8" y="2" width="8" height="4" rx="1"/>
        <path d="M9 12l2 2 4-4"/>
      </svg>
    </div>
    <div class="a-stat-body">
      <div class="a-stat-label">Enrollments</div>
      <div class="a-stat-value"><?= number_format($overview['enrollments']) ?></div>
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
      <div class="a-stat-value">$<?= number_format($overview['revenue'], 2) ?></div>
    </div>
  </div>

</div>

<!-- ── Charts row ─────────────────────────────────────────────────────────── -->
<div class="a-two-col" style="margin-bottom:1.75rem">

  <!-- New Users chart -->
  <div class="a-card">
    <div class="a-card-head">
      <h3>New User Signups</h3>
      <span class="a-badge a-badge--muted">Last 12 months</span>
    </div>
    <div class="a-card-body">
      <div class="a-chart-wrap">
        <canvas id="usersChart" style="width:100%;height:100%"></canvas>
      </div>
    </div>
  </div>

  <!-- Revenue chart -->
  <div class="a-card">
    <div class="a-card-head">
      <h3>Monthly Revenue</h3>
      <span class="a-badge a-badge--muted">Last 12 months</span>
    </div>
    <div class="a-card-body">
      <div class="a-chart-wrap">
        <canvas id="revenueChart" style="width:100%;height:100%"></canvas>
      </div>
    </div>
  </div>

</div>

<!-- ── Monthly trend table ────────────────────────────────────────────────── -->
<div class="a-table-card" style="margin-bottom:1.75rem">
  <div class="a-card-head">
    <h3>Monthly Breakdown</h3>
  </div>
  <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>Month</th>
          <th>New Users</th>
          <th>Enrollments</th>
          <th>Revenue</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_reverse($monthlyData) as $md): ?>
        <tr>
          <td style="font-weight:500"><?= htmlspecialchars($md['month']) ?></td>
          <td><?= number_format($md['signups']) ?></td>
          <td><?= number_format($md['enrollments']) ?></td>
          <td>$<?= number_format($md['revenue'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Top performing courses ─────────────────────────────────────────────── -->
<div class="a-table-card" style="margin-bottom:1.75rem">
  <div class="a-card-head">
    <h3>Top Performing Courses</h3>
    <span class="a-badge a-badge--muted">By enrollment count</span>
  </div>
  <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Course</th>
          <th>Category</th>
          <th>Enrollments</th>
          <th>Revenue</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($topCourses)): ?>
        <tr>
          <td colspan="5" style="text-align:center;padding:2rem;color:var(--a-text-muted)">No enrollment data yet.</td>
        </tr>
        <?php else: ?>
        <?php foreach ($topCourses as $i => $tc): ?>
        <tr>
          <td style="color:var(--a-text-muted);font-size:.82rem"><?= $i + 1 ?></td>
          <td style="font-weight:600;color:var(--a-text)"><?= htmlspecialchars($tc['title'] ?? '—') ?></td>
          <td><?= htmlspecialchars($tc['category'] ?? '—') ?></td>
          <td>
            <span class="a-badge a-badge--primary"><?= number_format((int)$tc['count']) ?></span>
          </td>
          <td style="font-weight:600">$<?= number_format((float)$tc['revenue'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Note -->
<p style="font-size:.8rem;color:var(--a-text-muted);text-align:right;margin-top:-.5rem">
  Data is real-time from the database. Last refreshed: <?= date('M j, Y \a\t g:i A') ?>
</p>

<!-- ── Chart.js ───────────────────────────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function() {
  'use strict';

  var labels  = <?= $chartLabels ?>;
  var signups = <?= $chartSignups ?>;
  var revenue = <?= $chartRevenue ?>;

  /* Shared defaults */
  Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
  Chart.defaults.font.size   = 12;
  Chart.defaults.color       = '#6b7280';

  /* ── Users bar chart ── */
  var usersCtx = document.getElementById('usersChart');
  if (usersCtx) {
    new Chart(usersCtx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'New Signups',
          data:  signups,
          backgroundColor: 'rgba(99, 102, 241, 0.7)',
          borderColor:     '#6366f1',
          borderWidth:     1,
          borderRadius:    4,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function(ctx) { return ' ' + ctx.parsed.y + ' signups'; }
            }
          }
        },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, ticks: { precision: 0 } }
        }
      }
    });
  }

  /* ── Revenue line chart ── */
  var revCtx = document.getElementById('revenueChart');
  if (revCtx) {
    new Chart(revCtx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Revenue (USD)',
          data:  revenue,
          borderColor:     '#10b981',
          backgroundColor: 'rgba(16, 185, 129, 0.12)',
          borderWidth:     2.5,
          pointBackgroundColor: '#10b981',
          pointRadius:    4,
          tension:        0.35,
          fill:           true,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function(ctx) { return ' $' + ctx.parsed.y.toFixed(2); }
            }
          }
        },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true }
        }
      }
    });
  }
})();
</script>

<?php require_once __DIR__ . '/_foot.php'; ?>
