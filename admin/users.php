<?php
if (!defined('BASE')) define('BASE', '');
$admin_active_page = 'users';
$admin_page_title  = 'Users';
require_once __DIR__ . '/_head.php';

$message = ''; $messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'update_role' && auth_has_permission($user, 'manage_users')) {
        $targetId = (int)($_POST['target_user_id'] ?? 0);
        $role = strtolower(trim((string)($_POST['role'] ?? 'user')));
        if ($targetId > 0 && in_array($role, ['user','agent','admin'], true)) {
            $isAdmin = ($role === 'admin') ? 1 : 0;
            $stmt = $mysqli->prepare('UPDATE clients SET role=?, is_admin=?, updated_at=NOW() WHERE id=?');
            if ($stmt) { $stmt->bind_param('sii', $role, $isAdmin, $targetId); $stmt->execute(); $stmt->close(); }
            $message = 'User role updated.';
        }
    }

    if ($action === 'suspend_user' && auth_has_permission($user, 'manage_users')) {
        $targetId = (int)($_POST['target_user_id'] ?? 0);
        $newStatus = ($_POST['new_status'] ?? '') === 'active' ? 'active' : 'suspended';
        if ($targetId > 0) {
            $stmt = $mysqli->prepare('UPDATE clients SET account_status=?, updated_at=NOW() WHERE id=?');
            if ($stmt) { $stmt->bind_param('si', $newStatus, $targetId); $stmt->execute(); $stmt->close(); }
            $message = 'User status updated.';
        }
    }

    if ($action === 'delete_user' && auth_has_permission($user, 'manage_users')) {
        $targetId = (int)($_POST['target_user_id'] ?? 0);
        if ($targetId > 0 && $targetId !== (int)($user['id'] ?? 0)) {
            $stmt = $mysqli->prepare('DELETE FROM clients WHERE id=?');
            if ($stmt) { $stmt->bind_param('i', $targetId); $stmt->execute(); $stmt->close(); }
            $message = 'User deleted.';
        }
    }

    if ($action === 'save_permissions' && auth_has_permission($user, 'manage_permissions')) {
        $targetId = (int)($_POST['target_user_id'] ?? 0);
        $selected = is_array($_POST['permissions'] ?? null) ? $_POST['permissions'] : [];
        if ($targetId > 0) {
            $del = $mysqli->prepare('DELETE FROM user_permissions WHERE client_id=?');
            if ($del) { $del->bind_param('i', $targetId); $del->execute(); $del->close(); }
            $ins = $mysqli->prepare('INSERT INTO user_permissions (client_id, permission_key, allowed) VALUES (?,?,1)');
            if ($ins) { foreach ($selected as $pk) { $pk=trim((string)$pk); if ($pk==='') continue; $ins->bind_param('is',$targetId,$pk); $ins->execute(); } $ins->close(); }
            $message = 'Permissions saved.';
        }
    }
}

// Filter/search
$search       = trim($_GET['q'] ?? '');
$roleFilter   = trim($_GET['role'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$page_num     = max(1, (int)($_GET['p'] ?? 1));
$per_page     = 15;
$offset       = ($page_num - 1) * $per_page;

$where  = '1=1';
$binds  = []; $types = '';
if ($search !== '') {
    $like = '%' . $mysqli->real_escape_string($search) . '%';
    $where .= " AND (full_name LIKE '$like' OR email LIKE '$like')";
}
if ($roleFilter !== '') $where .= " AND role='" . $mysqli->real_escape_string($roleFilter) . "'";
if ($statusFilter !== '') $where .= " AND account_status='" . $mysqli->real_escape_string($statusFilter) . "'";

$countRes   = $mysqli->query("SELECT COUNT(*) c FROM clients WHERE $where");
$totalCount = $countRes ? (int)($countRes->fetch_assoc()['c'] ?? 0) : 0;
$totalPages = max(1, (int)ceil($totalCount / $per_page));

$users = [];
$r = $mysqli->query("SELECT id, full_name, email, role, is_admin, account_status, email_verified, created_at FROM clients WHERE $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
if ($r) { while ($row = $r->fetch_assoc()) $users[] = $row; }

$statsRes = $mysqli->query('SELECT COUNT(*) total, SUM(account_status="active") active_c, SUM(role="admin" OR is_admin=1) admin_c, SUM(email_verified=1) verified_c FROM clients');
$stats = $statsRes ? ($statsRes->fetch_assoc() ?? []) : [];

$permissionCatalog = [];
$pr = $mysqli->query('SELECT permission_key, label FROM permissions ORDER BY permission_key');
if ($pr) { while ($row = $pr->fetch_assoc()) $permissionCatalog[] = $row; }

$userPermsMap = [];
$pm = $mysqli->query('SELECT client_id, permission_key FROM user_permissions WHERE allowed=1');
if ($pm) { while ($row = $pm->fetch_assoc()) { $cid=(int)$row['client_id']; $userPermsMap[$cid][]=$row['permission_key']; } }
?>

<div class="a-page-header">
  <div>
    <h1>Users</h1>
    <p>Manage user accounts, roles and permissions.</p>
  </div>
</div>

<!-- Mini stats -->
<div style="display:flex;gap:.75rem;margin-bottom:1.5rem;flex-wrap:wrap">
  <?php foreach([
    ['Total',    $stats['total']    ?? 0, 'a-badge--muted'],
    ['Active',   $stats['active_c'] ?? 0, 'a-badge--success'],
    ['Admins',   $stats['admin_c']  ?? 0, 'a-badge--danger'],
    ['Verified', $stats['verified_c']??0, 'a-badge--info'],
  ] as [$label, $val, $cls]): ?>
  <div style="background:#fff;border:1px solid var(--a-border);border-radius:var(--a-radius);padding:.6rem 1.1rem;display:flex;align-items:center;gap:.6rem;box-shadow:var(--a-shadow)">
    <span style="font-size:1.15rem;font-weight:700;color:var(--a-text)"><?= number_format((int)$val) ?></span>
    <span class="a-badge <?= $cls ?>"><?= $label ?></span>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($message): ?>
<div style="padding:.85rem 1.25rem;border-radius:var(--a-radius);margin-bottom:1rem;background:<?= $messageType==='error'?'#fef2f2':'#ecfdf5' ?>;border:1px solid <?= $messageType==='error'?'#fecaca':'#bbf7d0' ?>;color:<?= $messageType==='error'?'#b91c1c':'#15803d' ?>;font-size:.875rem;font-weight:600">
  <?= htmlspecialchars($message, ENT_QUOTES) ?>
</div>
<?php endif; ?>

<!-- Table card -->
<div class="a-table-card">

  <!-- Search/filter bar -->
  <div class="a-bar">
    <form method="GET" action="" style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;width:100%">
      <div class="a-bar-search-wrap" style="position:relative;flex:1;min-width:180px;max-width:300px">
        <span style="position:absolute;left:.65rem;top:50%;transform:translateY(-50%);color:var(--a-text-muted)">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </span>
        <input type="text" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>" placeholder="Search name or emailâ€¦" class="a-bar-input">
      </div>
      <select name="role" class="a-bar-input" style="max-width:140px;flex:none;padding:.5rem .9rem">
        <option value="">All Roles</option>
        <?php foreach(['user','agent','admin'] as $r): ?>
        <option value="<?= $r ?>" <?= $roleFilter===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" class="a-bar-input" style="max-width:150px;flex:none;padding:.5rem .9rem">
        <option value="">All Status</option>
        <?php foreach(['active','suspended','pending_verification'] as $s): ?>
        <option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="a-btn a-btn--primary a-btn--sm">Search</button>
      <?php if ($search||$roleFilter||$statusFilter): ?>
        <a href="<?= BASE ?>/admin/users.php" class="a-btn a-btn--ghost a-btn--sm">Clear</a>
      <?php endif; ?>
      <span class="a-pagination-info"><?= number_format($totalCount) ?> user<?= $totalCount!==1?'s':'' ?></span>
    </form>
  </div>

  <table>
    <thead>
      <tr>
        <th>User</th>
        <th>Role</th>
        <th>Status</th>
        <th>Verified</th>
        <th>Joined</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($users)): ?>
        <tr><td colspan="6" style="text-align:center;padding:3rem;color:var(--a-text-muted)">No users found.</td></tr>
      <?php else: foreach ($users as $u):
        $uid = (int)$u['id'];
        $uRole = $u['role'] ?: ((int)$u['is_admin']===1?'admin':'user');
        $roleBadge = $uRole==='admin'?'a-badge--danger':($uRole==='agent'?'a-badge--warning':'a-badge--muted');
        $statusBadge = $u['account_status']==='active'?'a-badge--success':($u['account_status']==='suspended'?'a-badge--danger':'a-badge--muted');
        $init = strtoupper(substr($u['full_name']?:$u['email'], 0, 2));
        $userPermsJson = json_encode($userPermsMap[$uid] ?? [], JSON_HEX_QUOT);
      ?>
        <tr>
          <td>
            <div class="a-user-cell">
              <div class="a-user-avatar" style="font-size:.75rem"><?= $init ?></div>
              <div>
                <div class="a-user-name"><?= htmlspecialchars($u['full_name']?:'â€”', ENT_QUOTES) ?></div>
                <div class="a-user-email"><?= htmlspecialchars($u['email'], ENT_QUOTES) ?></div>
              </div>
            </div>
          </td>
          <td><span class="a-badge <?= $roleBadge ?>"><?= htmlspecialchars($uRole) ?></span></td>
          <td><span class="a-badge <?= $statusBadge ?>"><?= htmlspecialchars($u['account_status']?:'â€”') ?></span></td>
          <td>
            <?php if ($u['email_verified']): ?>
              <span style="color:var(--a-success)">&#10003;</span>
            <?php else: ?>
              <span style="color:var(--a-text-muted)">â€”</span>
            <?php endif; ?>
          </td>
          <td style="color:var(--a-text-muted);font-size:.82rem"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
          <td>
            <div style="display:flex;gap:.4rem;flex-wrap:wrap">
              <?php if (auth_has_permission($user, 'manage_users')): ?>
              <button type="button" class="a-btn a-btn--ghost a-btn--sm"
                onclick="openRoleModal(<?= $uid ?>, '<?= htmlspecialchars($uRole, ENT_QUOTES) ?>')">
                Role
              </button>
              <?php if ($u['account_status'] === 'active'): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action" value="suspend_user">
                  <input type="hidden" name="target_user_id" value="<?= $uid ?>">
                  <input type="hidden" name="new_status" value="suspended">
                  <button class="a-btn a-btn--sm" style="background:#fff7ed;color:#d97706;border:1px solid #fed7aa">Suspend</button>
                </form>
              <?php else: ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action" value="suspend_user">
                  <input type="hidden" name="target_user_id" value="<?= $uid ?>">
                  <input type="hidden" name="new_status" value="active">
                  <button class="a-btn a-btn--sm a-btn--success">Activate</button>
                </form>
              <?php endif; ?>
              <?php endif; ?>
              <?php if (auth_has_permission($user, 'manage_permissions')): ?>
              <button type="button" class="a-btn a-btn--ghost a-btn--sm"
                onclick="openPermsModal(<?= $uid ?>, <?= $userPermsJson ?>)">
                Perms
              </button>
              <?php endif; ?>
              <?php if (auth_has_permission($user, 'manage_users') && $uid !== (int)($user['id']??0)): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user? This cannot be undone.')">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="target_user_id" value="<?= $uid ?>">
                <button class="a-btn a-btn--danger a-btn--sm">Del</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div class="a-pagination">
    <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
      <a href="?q=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>&status=<?= urlencode($statusFilter) ?>&p=<?= $pg ?>"
         class="a-btn a-btn--ghost a-btn--sm <?= $pg===$page_num?'active':'' ?>"
         style="<?= $pg===$page_num?'background:var(--a-primary);color:#fff;border-color:var(--a-primary)':'' ?>">
        <?= $pg ?>
      </a>
    <?php endfor; ?>
    <span class="a-pagination-info">Page <?= $page_num ?> of <?= $totalPages ?></span>
  </div>
  <?php endif; ?>
</div>

<!-- Role Modal -->
<div class="a-modal-bg" id="roleModal">
  <div class="a-modal" style="max-width:400px">
    <div class="a-modal-head">
      <h2>Change User Role</h2>
      <button class="a-modal-close" onclick="closeModal('roleModal')">&times;</button>
    </div>
    <form method="POST" class="a-modal-body">
      <input type="hidden" name="action" value="update_role">
      <input type="hidden" name="target_user_id" id="roleUserId">
      <div class="a-form-group" style="margin-bottom:1.25rem">
        <label>Role</label>
        <select name="role" id="roleSelect" class="a-bar-input" style="padding:.6rem .9rem;width:100%">
          <option value="user">User</option>
          <option value="agent">Agent</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <div style="display:flex;gap:.75rem">
        <button type="submit" class="a-btn a-btn--primary">Save Role</button>
        <button type="button" class="a-btn a-btn--ghost" onclick="closeModal('roleModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Permissions Modal -->
<div class="a-modal-bg" id="permsModal">
  <div class="a-modal" style="max-width:440px">
    <div class="a-modal-head">
      <h2>Manage Permissions</h2>
      <button class="a-modal-close" onclick="closeModal('permsModal')">&times;</button>
    </div>
    <form method="POST" class="a-modal-body" id="permsForm">
      <input type="hidden" name="action" value="save_permissions">
      <input type="hidden" name="target_user_id" id="permsUserId">
      <div style="display:flex;flex-direction:column;gap:.75rem;margin-bottom:1.25rem">
        <?php foreach ($permissionCatalog as $perm): ?>
        <label style="display:flex;align-items:center;gap:.65rem;font-size:.875rem;cursor:pointer">
          <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($perm['permission_key'], ENT_QUOTES) ?>" style="width:16px;height:16px;accent-color:var(--a-primary)">
          <span>
            <strong style="color:var(--a-text)"><?= htmlspecialchars($perm['label'], ENT_QUOTES) ?></strong><br>
            <span style="font-size:.78rem;color:var(--a-text-muted)"><?= htmlspecialchars($perm['permission_key'], ENT_QUOTES) ?></span>
          </span>
        </label>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:.75rem">
        <button type="submit" class="a-btn a-btn--primary">Save Permissions</button>
        <button type="button" class="a-btn a-btn--ghost" onclick="closeModal('permsModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRoleModal(userId, currentRole) {
    document.getElementById('roleUserId').value = userId;
    document.getElementById('roleSelect').value = currentRole;
    document.getElementById('roleModal').classList.add('open');
}
function openPermsModal(userId, userPerms) {
    document.getElementById('permsUserId').value = userId;
    document.querySelectorAll('#permsForm input[type=checkbox]').forEach(cb => {
        cb.checked = Array.isArray(userPerms) && userPerms.includes(cb.value);
    });
    document.getElementById('permsModal').classList.add('open');
}
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.a-modal-bg').forEach(bg => {
    bg.addEventListener('click', e => { if (e.target === bg) bg.classList.remove('open'); });
});
</script>

<?php require_once __DIR__ . '/_foot.php'; ?>
