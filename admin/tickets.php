<?php
if (!defined('BASE')) define('BASE', '');

$admin_active_page = 'tickets';
$admin_page_title = 'Support Tickets';

require_once __DIR__ . '/_head.php';
require_once __DIR__ . '/../includes/support-repo.php';

ensure_support_tickets_table($mysqli);

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && auth_has_permission($user, 'manage_users')) {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    if ($ticketId > 0) {
        $status = trim((string)($_POST['status'] ?? 'open'));
        $priority = trim((string)($_POST['priority'] ?? 'normal'));
        $assignedTo = (int)($_POST['assigned_to'] ?? 0);
        $notes = trim((string)($_POST['admin_notes'] ?? ''));

        if (!in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true)) {
            $status = 'open';
        }
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            $priority = 'normal';
        }

        $assigned = $assignedTo > 0 ? $assignedTo : null;

        $stmt = $mysqli->prepare('UPDATE support_tickets SET status = ?, priority = ?, assigned_to = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('ssisi', $status, $priority, $assigned, $notes, $ticketId);
            if ($stmt->execute()) {
                $message = 'Ticket updated.';
            } else {
                $message = 'Unable to update ticket.';
                $messageType = 'error';
            }
            $stmt->close();
        }
    }
}

$staff = [];
$r = $mysqli->query("SELECT id, full_name, role FROM clients WHERE role IN ('admin','agent') OR is_admin = 1 ORDER BY full_name ASC");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $staff[] = $row;
    }
}

$search = trim((string)($_GET['q'] ?? ''));
$where = '1=1';
if ($search !== '') {
    $like = '%' . $mysqli->real_escape_string($search) . '%';
    $where .= " AND (t.subject LIKE '$like' OR t.message LIKE '$like' OR c.full_name LIKE '$like' OR c.email LIKE '$like')";
}

$tickets = [];
$q = $mysqli->query("SELECT t.*, c.full_name, c.email, a.full_name AS assigned_name
                     FROM support_tickets t
                     LEFT JOIN clients c ON c.id = t.client_id
                     LEFT JOIN clients a ON a.id = t.assigned_to
                     WHERE $where
                     ORDER BY FIELD(t.status, 'open','in_progress','resolved','closed'), t.updated_at DESC");
if ($q) {
    while ($row = $q->fetch_assoc()) {
        $tickets[] = $row;
    }
}
?>

<div class="a-page-header">
  <div>
    <h1>Support Tickets</h1>
    <p>Assign and resolve user support requests.</p>
  </div>
</div>

<?php if ($message !== ''): ?>
<div style="padding:.85rem 1.1rem;border-radius:8px;margin-bottom:1rem;font-size:.875rem;font-weight:500;background:<?php echo $messageType === 'error' ? '#fef2f2' : '#ecfdf5'; ?>;color:<?php echo $messageType === 'error' ? '#b91c1c' : '#15803d'; ?>;border:1px solid <?php echo $messageType === 'error' ? '#fecaca' : '#bbf7d0'; ?>;">
  <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="a-table-card">
  <div class="a-bar">
    <form method="get" style="display:flex;gap:.6rem;flex-wrap:wrap;width:100%">
      <input class="a-bar-input" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search tickets" style="max-width:320px">
      <button class="a-btn a-btn--primary a-btn--sm" type="submit">Search</button>
    </form>
  </div>

  <table>
    <thead>
      <tr>
        <th>Ticket</th>
        <th>User</th>
        <th>Status</th>
        <th>Priority</th>
        <th>Assigned</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($tickets)): ?>
      <tr><td colspan="6" style="padding:1.5rem;text-align:center;color:var(--a-text-muted)">No tickets found.</td></tr>
      <?php else: foreach ($tickets as $t): ?>
      <tr>
        <td>
          <strong>#<?php echo (int)$t['id']; ?> <?php echo htmlspecialchars((string)$t['subject']); ?></strong>
          <div style="font-size:.8rem;color:var(--a-text-muted);max-width:320px"><?php echo htmlspecialchars((string)$t['message']); ?></div>
        </td>
        <td>
          <?php echo htmlspecialchars((string)($t['full_name'] ?? 'Unknown')); ?><br>
          <span style="font-size:.8rem;color:var(--a-text-muted)"><?php echo htmlspecialchars((string)($t['email'] ?? '')); ?></span>
        </td>
        <td><span class="a-badge <?php echo $t['status'] === 'resolved' ? 'a-badge--success' : ($t['status'] === 'closed' ? 'a-badge--muted' : 'a-badge--warning'); ?>"><?php echo htmlspecialchars((string)$t['status']); ?></span></td>
        <td><span class="a-badge <?php echo $t['priority'] === 'urgent' ? 'a-badge--danger' : ($t['priority'] === 'high' ? 'a-badge--warning' : 'a-badge--muted'); ?>"><?php echo htmlspecialchars((string)$t['priority']); ?></span></td>
        <td><?php echo htmlspecialchars((string)($t['assigned_name'] ?? 'Unassigned')); ?></td>
        <td>
          <button class="a-btn a-btn--ghost a-btn--sm" type="button" onclick="openTicketModal(<?php echo (int)$t['id']; ?>)">Manage</button>
        </td>
      </tr>
      <tr id="ticket-row-<?php echo (int)$t['id']; ?>" style="display:none">
        <td colspan="6" style="background:#f8fafc">
          <form method="post" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.65rem;padding:.8rem 0">
            <input type="hidden" name="ticket_id" value="<?php echo (int)$t['id']; ?>">
            <div>
              <label style="font-size:.75rem;color:var(--a-text-muted)">Status</label>
              <select name="status" class="a-bar-input" style="padding:.45rem .65rem">
                <?php foreach (['open','in_progress','resolved','closed'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $t['status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label style="font-size:.75rem;color:var(--a-text-muted)">Priority</label>
              <select name="priority" class="a-bar-input" style="padding:.45rem .65rem">
                <?php foreach (['low','normal','high','urgent'] as $p): ?>
                <option value="<?php echo $p; ?>" <?php echo $t['priority'] === $p ? 'selected' : ''; ?>><?php echo $p; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label style="font-size:.75rem;color:var(--a-text-muted)">Assigned To</label>
              <select name="assigned_to" class="a-bar-input" style="padding:.45rem .65rem">
                <option value="0">Unassigned</option>
                <?php foreach ($staff as $s): ?>
                <option value="<?php echo (int)$s['id']; ?>" <?php echo (int)($t['assigned_to'] ?? 0) === (int)$s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($s['full_name'] ?? ('#' . $s['id']))); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label style="font-size:.75rem;color:var(--a-text-muted)">Admin Notes</label>
              <input name="admin_notes" class="a-bar-input" style="padding:.45rem .65rem" value="<?php echo htmlspecialchars((string)($t['admin_notes'] ?? '')); ?>">
            </div>
            <div style="grid-column:1 / -1"><button class="a-btn a-btn--primary a-btn--sm" type="submit">Save Ticket</button></div>
          </form>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<script>
function openTicketModal(id) {
  var row = document.getElementById('ticket-row-' + id);
  if (!row) return;
  row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
}
</script>

<?php require_once __DIR__ . '/_foot.php'; ?>
