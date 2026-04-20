<?php

if (!defined('BASE')) define('BASE', '');

$admin_active_page = 'settings';
$admin_page_title  = 'Settings';

require_once __DIR__ . '/_head.php';

$mailConfigured = is_file(__DIR__ . '/../includes/mail-config.php');
$stripeConfigured = is_file(__DIR__ . '/../includes/stripe-config.php');
$courseStoragePath = __DIR__ . '/../storage/courses';
$courseStorageReady = is_dir($courseStoragePath) && is_writable($courseStoragePath);
?>

<div class="a-page-header">
  <div>
    <h1>Settings</h1>
    <p>Manage admin preferences and review platform configuration.</p>
  </div>
  <div class="a-page-actions">
    <a href="<?php echo BASE; ?>/profile.php" class="a-btn a-btn--ghost">My Profile</a>
  </div>
</div>

<div class="a-two-col" style="margin-bottom:1.5rem">
  <div class="a-card">
    <div class="a-card-head"><h3>Account</h3></div>
    <div class="a-card-body" style="display:grid;gap:1rem">
      <div>
        <div style="font-weight:700;color:var(--a-text)">Signed in as</div>
        <div style="color:var(--a-text-muted);margin-top:.25rem"><?php echo htmlspecialchars((string)($user['full_name'] ?: $user['email']), ENT_QUOTES); ?></div>
      </div>
      <div style="display:flex;gap:.65rem;flex-wrap:wrap">
        <a href="<?php echo BASE; ?>/profile.php" class="a-btn a-btn--primary">Open Profile & Password</a>
        <a href="<?php echo BASE; ?>/admin/users.php" class="a-btn a-btn--ghost">Manage Users</a>
      </div>
    </div>
  </div>

  <div class="a-card">
    <div class="a-card-head"><h3>System Status</h3></div>
    <div class="a-card-body" style="display:grid;gap:.85rem">
      <div style="display:flex;justify-content:space-between;gap:1rem">
        <span>Mail configuration</span>
        <span class="a-badge <?php echo $mailConfigured ? 'a-badge--success' : 'a-badge--warning'; ?>"><?php echo $mailConfigured ? 'Ready' : 'Check file'; ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;gap:1rem">
        <span>Stripe configuration</span>
        <span class="a-badge <?php echo $stripeConfigured ? 'a-badge--success' : 'a-badge--warning'; ?>"><?php echo $stripeConfigured ? 'Ready' : 'Check file'; ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;gap:1rem">
        <span>Course storage</span>
        <span class="a-badge <?php echo $courseStorageReady ? 'a-badge--success' : 'a-badge--warning'; ?>"><?php echo $courseStorageReady ? 'Writable' : 'Needs attention'; ?></span>
      </div>
    </div>
  </div>
</div>

<div class="a-card">
  <div class="a-card-head"><h3>Quick Actions</h3></div>
  <div class="a-card-body" style="display:flex;gap:.65rem;flex-wrap:wrap">
    <a href="<?php echo BASE; ?>/admin/courses.php" class="a-btn a-btn--ghost">Courses</a>
    <a href="<?php echo BASE; ?>/admin/enrollments.php" class="a-btn a-btn--ghost">Enrollments</a>
    <a href="<?php echo BASE; ?>/admin/coupons.php" class="a-btn a-btn--ghost">Coupons</a>
    <a href="<?php echo BASE; ?>/admin/reports.php" class="a-btn a-btn--ghost">Reports</a>
  </div>
</div>

<?php require_once __DIR__ . '/_foot.php'; ?>
