<?php
$page_title = 'Support Center';
$page_desc = 'Open and track support tickets.';

if (!defined('BASE')) define('BASE', '');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/support-repo.php';

$user = auth_current_user();
if (!$user) {
    header('Location: ' . BASE . '/login.php');
    exit;
}

ensure_support_tickets_table($mysqli);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $subject = trim((string)($_POST['subject'] ?? ''));
    $category = trim((string)($_POST['category'] ?? 'general'));
    $priority = trim((string)($_POST['priority'] ?? 'normal'));
    $body = trim((string)($_POST['message'] ?? ''));

    $allowedCategories = ['general', 'billing', 'technical', 'course'];
    $allowedPriorities = ['low', 'normal', 'high', 'urgent'];

    if (!in_array($category, $allowedCategories, true)) {
        $category = 'general';
    }
    if (!in_array($priority, $allowedPriorities, true)) {
        $priority = 'normal';
    }

    if ($subject === '' || $body === '') {
        $error = 'Subject and message are required.';
    } else {
        if (create_support_ticket($mysqli, (int)$user['id'], $subject, $category, $priority, $body)) {
            $message = 'Ticket submitted successfully. Our team will respond soon.';
        } else {
            $error = 'Unable to submit ticket right now.';
        }
    }
}

$tickets = [];
$stmt = $mysqli->prepare('SELECT id, subject, category, priority, status, created_at, updated_at FROM support_tickets WHERE client_id = ? ORDER BY updated_at DESC');
if ($stmt) {
    $uid = (int)$user['id'];
    $stmt->bind_param('i', $uid);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result?->fetch_assoc()) {
            $tickets[] = $row;
        }
    }
    $stmt->close();
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="settings-wrap">
    <div style="margin-bottom:2rem">
        <div class="section-tag" style="margin-bottom:.75rem">Support</div>
        <h1 style="font-family:'Space Grotesk',sans-serif;font-size:2rem;font-weight:800;color:var(--text-primary);letter-spacing:-.3px">Support Center</h1>
        <p style="color:var(--text-muted);margin-top:.35rem">Create a ticket and track response status.</p>
    </div>

    <?php if ($error): ?>
        <div class="auth-page-error show" style="margin-bottom:1rem"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="auth-page-error show" style="margin-bottom:1rem;color:var(--accent-green);background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2)"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="settings-section">
        <div class="settings-section-head"><h2>Open New Ticket</h2></div>
        <div class="settings-section-body">
            <form method="post">
                <input type="hidden" name="create_ticket" value="1">
                <div class="form-group">
                    <label for="ticket_subject">Subject</label>
                    <input id="ticket_subject" type="text" name="subject" class="form-input" required>
                </div>
                <div class="form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                    <div>
                        <label for="ticket_category">Category</label>
                        <select id="ticket_category" name="category" class="form-input">
                            <option value="general">General</option>
                            <option value="billing">Billing</option>
                            <option value="technical">Technical</option>
                            <option value="course">Course</option>
                        </select>
                    </div>
                    <div>
                        <label for="ticket_priority">Priority</label>
                        <select id="ticket_priority" name="priority" class="form-input">
                            <option value="low">Low</option>
                            <option value="normal" selected>Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="ticket_message">Message</label>
                    <textarea id="ticket_message" name="message" rows="5" class="form-input" required></textarea>
                </div>
                <button type="submit" class="btn-primary">Submit Ticket</button>
            </form>
        </div>
    </div>

    <div class="settings-section">
        <div class="settings-section-head"><h2>My Tickets</h2></div>
        <div class="settings-section-body">
            <?php if (empty($tickets)): ?>
                <p style="color:var(--text-muted)">No tickets yet.</p>
            <?php else: ?>
                <div style="display:grid;gap:.75rem">
                    <?php foreach ($tickets as $t): ?>
                    <div style="padding:.9rem;border:1px solid var(--border);border-radius:12px;background:var(--bg-surface)">
                        <div style="display:flex;justify-content:space-between;gap:.8rem;flex-wrap:wrap">
                            <strong><?php echo htmlspecialchars((string)$t['subject']); ?></strong>
                            <span style="font-size:.8rem;color:var(--text-muted)"><?php echo htmlspecialchars((string)$t['created_at']); ?></span>
                        </div>
                        <div style="margin-top:.35rem;font-size:.82rem;color:var(--text-muted)">
                            #<?php echo (int)$t['id']; ?> | <?php echo htmlspecialchars((string)$t['category']); ?> | <?php echo htmlspecialchars((string)$t['priority']); ?>
                        </div>
                        <div style="margin-top:.35rem">
                            <span class="course-tag" style="font-size:.78rem;padding:.25rem .55rem;background:var(--primary-bg);border:1px solid var(--border)"><?php echo htmlspecialchars((string)$t['status']); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
