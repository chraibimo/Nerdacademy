<?php
if (!defined('BASE')) define('BASE', '');

$admin_active_page = 'coupons';
$admin_page_title = 'Coupons';

require_once __DIR__ . '/_head.php';
require_once __DIR__ . '/../includes/coupons-repo.php';

ensure_coupons_table($mysqli);

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && auth_has_permission($user, 'manage_courses')) {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create_coupon') {
        $code = normalize_coupon_code((string)($_POST['code'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));
        $discount = max(1, min(95, (float)($_POST['discount_percent'] ?? 10)));
        $maxUses = max(0, (int)($_POST['max_uses'] ?? 0));
        $expires = trim((string)($_POST['expires_at'] ?? ''));
        $expiresAt = $expires !== '' ? (date('Y-m-d H:i:s', strtotime($expires)) ?: null) : null;

        if ($code === '') {
            $message = 'Coupon code is required.';
            $messageType = 'error';
        } else {
            $stmt = $mysqli->prepare('INSERT INTO coupon_codes (code, label, discount_percent, max_uses, expires_at, is_active) VALUES (?, ?, ?, ?, ?, 1)');
            if ($stmt) {
                $stmt->bind_param('ssdis', $code, $label, $discount, $maxUses, $expiresAt);
                if ($stmt->execute()) {
                    $message = 'Coupon created successfully.';
                } else {
                    $message = 'Failed to create coupon: ' . $stmt->error;
                    $messageType = 'error';
                }
                $stmt->close();
            }
        }
    }

    if ($action === 'toggle_coupon') {
        $couponId = (int)($_POST['coupon_id'] ?? 0);
        $newVal = (int)($_POST['new_val'] ?? 0);
        if ($couponId > 0) {
            $stmt = $mysqli->prepare('UPDATE coupon_codes SET is_active = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('ii', $newVal, $couponId);
                $stmt->execute();
                $stmt->close();
                $message = 'Coupon status updated.';
            }
        }
    }

    if ($action === 'delete_coupon') {
        $couponId = (int)($_POST['coupon_id'] ?? 0);
        if ($couponId > 0) {
            $stmt = $mysqli->prepare('DELETE FROM coupon_codes WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $couponId);
                $stmt->execute();
                $stmt->close();
                $message = 'Coupon deleted.';
            }
        }
    }
}

$coupons = [];
$r = $mysqli->query('SELECT * FROM coupon_codes ORDER BY created_at DESC');
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $coupons[] = $row;
    }
}
?>

<div class="a-page-header">
  <div>
    <h1>Coupons</h1>
    <p>Create and manage discount campaigns.</p>
  </div>
</div>

<?php if ($message !== ''): ?>
<div style="padding:.85rem 1.1rem;border-radius:8px;margin-bottom:1rem;font-size:.875rem;font-weight:500;background:<?php echo $messageType === 'error' ? '#fef2f2' : '#ecfdf5'; ?>;color:<?php echo $messageType === 'error' ? '#b91c1c' : '#15803d'; ?>;border:1px solid <?php echo $messageType === 'error' ? '#fecaca' : '#bbf7d0'; ?>;">
  <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="a-card" style="margin-bottom:1rem">
  <div class="a-card-head"><h3>Create Coupon</h3></div>
  <div class="a-card-body">
    <form method="post" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem">
      <input type="hidden" name="action" value="create_coupon">
      <div class="a-form-group">
        <label>Code</label>
        <input class="a-bar-input" name="code" placeholder="WELCOME20" required>
      </div>
      <div class="a-form-group">
        <label>Label</label>
        <input class="a-bar-input" name="label" placeholder="Welcome campaign">
      </div>
      <div class="a-form-group">
        <label>Discount %</label>
        <input class="a-bar-input" type="number" name="discount_percent" min="1" max="95" value="20" required>
      </div>
      <div class="a-form-group">
        <label>Max Uses (0 = unlimited)</label>
        <input class="a-bar-input" type="number" name="max_uses" min="0" value="0">
      </div>
      <div class="a-form-group">
        <label>Expires At</label>
        <input class="a-bar-input" type="datetime-local" name="expires_at">
      </div>
      <div class="a-form-group" style="display:flex;align-items:flex-end">
        <button class="a-btn a-btn--primary" type="submit">Create</button>
      </div>
    </form>
  </div>
</div>

<div class="a-table-card">
  <table>
    <thead>
      <tr>
        <th>Code</th>
        <th>Label</th>
        <th>Discount</th>
        <th>Usage</th>
        <th>Expires</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($coupons)): ?>
      <tr><td colspan="7" style="padding:1.5rem;text-align:center;color:var(--a-text-muted)">No coupons yet.</td></tr>
      <?php else: foreach ($coupons as $coupon): ?>
      <tr>
        <td><strong><?php echo htmlspecialchars((string)$coupon['code']); ?></strong></td>
        <td><?php echo htmlspecialchars((string)$coupon['label']); ?></td>
        <td><?php echo number_format((float)$coupon['discount_percent'], 2); ?>%</td>
        <td><?php echo (int)$coupon['used_count']; ?><?php if ((int)$coupon['max_uses'] > 0): ?> / <?php echo (int)$coupon['max_uses']; ?><?php endif; ?></td>
        <td><?php echo !empty($coupon['expires_at']) ? htmlspecialchars((string)$coupon['expires_at']) : 'No expiry'; ?></td>
        <td><span class="a-badge <?php echo (int)$coupon['is_active'] === 1 ? 'a-badge--success' : 'a-badge--muted'; ?>"><?php echo (int)$coupon['is_active'] === 1 ? 'Active' : 'Inactive'; ?></span></td>
        <td>
          <div style="display:flex;gap:.4rem;flex-wrap:wrap">
            <form method="post">
              <input type="hidden" name="action" value="toggle_coupon">
              <input type="hidden" name="coupon_id" value="<?php echo (int)$coupon['id']; ?>">
              <input type="hidden" name="new_val" value="<?php echo (int)$coupon['is_active'] === 1 ? 0 : 1; ?>">
              <button class="a-btn a-btn--sm a-btn--ghost" type="submit"><?php echo (int)$coupon['is_active'] === 1 ? 'Disable' : 'Enable'; ?></button>
            </form>
            <form method="post" onsubmit="return confirm('Delete this coupon?')">
              <input type="hidden" name="action" value="delete_coupon">
              <input type="hidden" name="coupon_id" value="<?php echo (int)$coupon['id']; ?>">
              <button class="a-btn a-btn--danger a-btn--sm" type="submit">Delete</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/_foot.php'; ?>
