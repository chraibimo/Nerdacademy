<?php

$admin_page_title  = 'Instructors';
$admin_active_page = 'instructors';

if (!defined('BASE')) define('BASE', '');

header('Location: ' . BASE . '/admin/index.php');
exit;

require_once __DIR__ . '/_head.php';
require_once __DIR__ . '/../includes/mailer.php';

auth_ensure_rbac_tables();

// ================================================================
//  Ensure instructor_courses join table
// ================================================================
$mysqli->query("
    CREATE TABLE IF NOT EXISTS instructor_courses (
        instructor_id BIGINT UNSIGNED NOT NULL,
        course_id     INT            NOT NULL,
        assigned_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (instructor_id, course_id),
        KEY idx_ic_instructor (instructor_id),
        KEY idx_ic_course (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ================================================================
//  RBAC: seed instructor role permissions once
// ================================================================
$instructorPerms = ['panel_access', 'manage_courses'];
$stmtSeedRole = $mysqli->prepare('INSERT IGNORE INTO role_permissions (role, permission_key) VALUES (?, ?)');
if ($stmtSeedRole) {
    foreach ($instructorPerms as $perm) {
        $role = 'instructor';
        $stmtSeedRole->bind_param('ss', $role, $perm);
        $stmtSeedRole->execute();
    }
    $stmtSeedRole->close();
}

// ================================================================
//  POST actions
// ================================================================
$message     = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && auth_has_permission($user, 'manage_users')) {
    $action = (string)($_POST['action'] ?? '');

    // ---- Promote existing user to instructor ----
    if ($action === 'promote_to_instructor') {
        $targetId = (int)($_POST['target_user_id'] ?? 0);
        if ($targetId > 0) {
            $role = 'instructor';
            $stmt = $mysqli->prepare('UPDATE clients SET role = ?, updated_at = NOW() WHERE id = ? AND role NOT IN (\'admin\')');
            if ($stmt) {
                $stmt->bind_param('si', $role, $targetId);
                $stmt->execute();
                $stmt->close();
            }
            // Grant individual permissions as well (in case role_permissions aren't checked for new roles)
            $ins = $mysqli->prepare('INSERT IGNORE INTO user_permissions (client_id, permission_key, allowed) VALUES (?, ?, 1)');
            if ($ins) {
                foreach ($instructorPerms as $perm) {
                    $ins->bind_param('is', $targetId, $perm);
                    $ins->execute();
                }
                $ins->close();
            }
            $message = 'User promoted to instructor.';
        } else {
            $message     = 'Invalid user ID.';
            $messageType = 'error';
        }
    }

    // ---- Demote instructor back to user ----
    if ($action === 'demote_instructor') {
        $targetId = (int)($_POST['target_user_id'] ?? 0);
        if ($targetId > 0 && $targetId !== (int)($user['id'] ?? 0)) {
            $role = 'user';
            $stmt = $mysqli->prepare('UPDATE clients SET role = ?, updated_at = NOW() WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('si', $role, $targetId);
                $stmt->execute();
                $stmt->close();
            }
            // Remove instructor-specific user_permissions
            $del = $mysqli->prepare("DELETE FROM user_permissions WHERE client_id = ? AND permission_key IN ('panel_access','manage_courses')");
            if ($del) {
                $del->bind_param('i', $targetId);
                $del->execute();
                $del->close();
            }
            // Remove course assignments
            $delCourses = $mysqli->prepare('DELETE FROM instructor_courses WHERE instructor_id = ?');
            if ($delCourses) {
                $delCourses->bind_param('i', $targetId);
                $delCourses->execute();
                $delCourses->close();
            }
            $message = 'Instructor demoted to user.';
        } else {
            $message     = 'Cannot demote yourself or invalid user.';
            $messageType = 'error';
        }
    }

    // ---- Assign courses to instructor ----
    if ($action === 'assign_courses') {
        $targetId   = (int)($_POST['target_user_id'] ?? 0);
        $courseIds  = is_array($_POST['course_ids'] ?? null) ? $_POST['course_ids'] : [];

        if ($targetId > 0) {
            // Replace all assignments
            $delAll = $mysqli->prepare('DELETE FROM instructor_courses WHERE instructor_id = ?');
            if ($delAll) {
                $delAll->bind_param('i', $targetId);
                $delAll->execute();
                $delAll->close();
            }
            if (!empty($courseIds)) {
                $ins = $mysqli->prepare('INSERT IGNORE INTO instructor_courses (instructor_id, course_id) VALUES (?, ?)');
                if ($ins) {
                    foreach ($courseIds as $cid) {
                        $cid = (int)$cid;
                        if ($cid > 0) {
                            $ins->bind_param('ii', $targetId, $cid);
                            $ins->execute();
                        }
                    }
                    $ins->close();
                }
            }
            $message = 'Course assignments updated.';
        } else {
            $message     = 'Invalid instructor ID.';
            $messageType = 'error';
        }
    }

    // ---- Unassign single course ----
    if ($action === 'unassign_course') {
        $targetId = (int)($_POST['target_user_id'] ?? 0);
        $courseId = (int)($_POST['course_id']       ?? 0);
        if ($targetId > 0 && $courseId > 0) {
            $del = $mysqli->prepare('DELETE FROM instructor_courses WHERE instructor_id = ? AND course_id = ?');
            if ($del) {
                $del->bind_param('ii', $targetId, $courseId);
                $del->execute();
                $del->close();
            }
            $message = 'Course unassigned.';
        }
    }

    // ---- Invite new instructor ----
    if ($action === 'invite_instructor') {
        $inviteName  = trim((string)($_POST['invite_name']  ?? ''));
        $inviteEmail = trim((string)($_POST['invite_email'] ?? ''));

        if ($inviteName === '' || $inviteEmail === '' || !filter_var($inviteEmail, FILTER_VALIDATE_EMAIL)) {
            $message     = 'A valid name and email are required.';
            $messageType = 'error';
        } else {
            // Check if email already exists
            $checkStmt = $mysqli->prepare('SELECT id, role FROM clients WHERE email = ? LIMIT 1');
            $existing  = null;
            if ($checkStmt) {
                $checkStmt->bind_param('s', $inviteEmail);
                $checkStmt->execute();
                $r = $checkStmt->get_result();
                $existing = $r ? $r->fetch_assoc() : null;
                $checkStmt->close();
            }

            if ($existing) {
                // Promote existing user
                $targetId = (int)$existing['id'];
                $role = 'instructor';
                $upd = $mysqli->prepare("UPDATE clients SET role = ?, full_name = ?, updated_at = NOW() WHERE id = ? AND role NOT IN ('admin')");
                if ($upd) {
                    $upd->bind_param('ssi', $role, $inviteName, $targetId);
                    $upd->execute();
                    $upd->close();
                }
                $message = 'Existing user promoted to instructor.';
            } else {
                // Create new user account
                $tempPassword  = bin2hex(random_bytes(8));
                $passwordHash  = password_hash($tempPassword, PASSWORD_DEFAULT);
                $userUid       = 'inv_' . bin2hex(random_bytes(8));
                $role          = 'instructor';
                $accountStatus = 'active';

                $insUser = $mysqli->prepare('
                    INSERT INTO clients (user_uid, full_name, email, password_hash, email_verified, account_status, role, provider_ids_json)
                    VALUES (?, ?, ?, ?, 1, ?, ?, \'[]\')
                ');
                if ($insUser) {
                    $insUser->bind_param('ssssss', $userUid, $inviteName, $inviteEmail, $passwordHash, $accountStatus, $role);
                    if ($insUser->execute()) {
                        $newUserId = (int)$mysqli->insert_id;
                        $insUser->close();

                        // Grant permissions
                        $insPerms = $mysqli->prepare('INSERT IGNORE INTO user_permissions (client_id, permission_key, allowed) VALUES (?, ?, 1)');
                        if ($insPerms) {
                            foreach ($instructorPerms as $perm) {
                                $insPerms->bind_param('is', $newUserId, $perm);
                                $insPerms->execute();
                            }
                            $insPerms->close();
                        }

                        // Send invitation email
                        $subject    = 'You have been invited as an Instructor on NerdAcademy';
                        $loginUrl   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                            . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE . '/login.php';
                        $safeInviteName = htmlspecialchars($inviteName, ENT_QUOTES);
                        $safeInviteEmail = htmlspecialchars($inviteEmail, ENT_QUOTES);
                        $safeTempPassword = htmlspecialchars($tempPassword, ENT_QUOTES);
                        $safeLoginUrl = htmlspecialchars($loginUrl, ENT_QUOTES);
                        $htmlBody   = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:32px 12px;background:linear-gradient(160deg,#eef2ff 0%,#f8fafc 48%,#e0f2fe 100%);font-family:Inter,'Segoe UI',Arial,sans-serif;color:#111827">
  <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;max-width:660px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:20px;overflow:hidden;box-shadow:0 18px 45px rgba(79,70,229,.14)">
    <tr>
      <td style="background:linear-gradient(135deg,#4338ca 0%,#6366f1 52%,#0ea5e9 100%);padding:28px 30px 24px;color:#ffffff;text-align:center">
        <div style="font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;opacity:.92">NerdAcademy Instructor Invite</div>
        <h1 style="margin:12px 0 8px;font-size:28px;line-height:1.25;font-weight:800">You're invited to teach with us</h1>
        <p style="margin:0;font-size:15px;line-height:1.75;color:rgba(255,255,255,.92)">Your instructor account is ready. Sign in and start shaping real-world AI learning.</p>
      </td>
    </tr>
    <tr>
      <td style="padding:30px">
        <p style="margin:0 0 12px;font-size:16px;line-height:1.75;color:#111827">Hi {$safeInviteName},</p>
        <p style="margin:0 0 18px;font-size:15px;line-height:1.85;color:#475569">You have been invited to join <strong style="color:#312e81">NerdAcademy</strong> as an <strong>Instructor</strong>. Use the temporary credentials below to sign in for the first time.</p>
        <div style="padding:16px 18px;border:1px solid #e5e7eb;border-radius:16px;background:#f8fafc;margin:0 0 18px">
          <div style="margin-bottom:8px"><strong>Email:</strong> {$safeInviteEmail}</div>
          <div><strong>Temporary password:</strong> <span style="font-family:'Courier New',monospace;color:#4f46e5;font-weight:700">{$safeTempPassword}</span></div>
        </div>
        <div style="text-align:center;margin:24px 0">
          <a href="{$safeLoginUrl}" style="display:inline-block;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:10px;font-size:15px;font-weight:800">Sign In to Your Instructor Account</a>
        </div>
        <p style="margin:0;font-size:13px;line-height:1.8;color:#64748b">For security, please sign in and change your password immediately after your first login.</p>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
                        $textBody   = "Hi {$inviteName},\n\nYou have been invited to join NerdAcademy as an Instructor.\n\nEmail: {$inviteEmail}\nTemporary password: {$tempPassword}\n\nSign in here: {$loginUrl}\n\nPlease change your password immediately after your first login.";
                        $sent = send_smtp_mail($inviteEmail, $inviteName, $subject, $htmlBody, $textBody);
                        $message = 'Instructor account created.'
                            . ($sent ? ' Invitation email sent.' : ' (Email could not be sent.)');
                    } else {
                        $insUser->close();
                        if ($mysqli->errno === 1062) {
                            $message     = 'An account with this email already exists.';
                        } else {
                            $message     = 'Failed to create account: ' . $mysqli->error;
                        }
                        $messageType = 'error';
                    }
                } else {
                    $message     = 'Database error.';
                    $messageType = 'error';
                }
            }
        }
    }
}

// ================================================================
//  Data: all instructors
// ================================================================
$instructors = [];
$instrRes = $mysqli->query("
    SELECT
        c.id, c.full_name, c.email, c.account_status, c.created_at,
        COUNT(ic.course_id) AS course_count
    FROM clients c
    LEFT JOIN instructor_courses ic ON ic.instructor_id = c.id
    WHERE c.role = 'instructor'
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
if ($instrRes) {
    while ($row = $instrRes->fetch_assoc()) {
        $instructors[] = $row;
    }
}

// ================================================================
//  Data: all courses (for assignment modal)
// ================================================================
$allCourses = [];
$coursesRes = $mysqli->query('SELECT id, title FROM courses_catalog WHERE is_active = 1 ORDER BY title ASC');
if ($coursesRes) {
    while ($row = $coursesRes->fetch_assoc()) {
        $allCourses[] = $row;
    }
}

// ================================================================
//  Data: current assignments per instructor (keyed by instructor_id)
// ================================================================
$assignments = []; // instructor_id => [course_id, ...]
if (!empty($instructors)) {
    $instrIds = implode(',', array_map(fn($i) => (int)$i['id'], $instructors));
    $asgnRes  = $mysqli->query("SELECT instructor_id, course_id FROM instructor_courses WHERE instructor_id IN ($instrIds)");
    if ($asgnRes) {
        while ($row = $asgnRes->fetch_assoc()) {
            $assignments[(int)$row['instructor_id']][] = (int)$row['course_id'];
        }
    }
}

// ================================================================
//  Active tab
// ================================================================
$activeTab = (string)($_GET['tab'] ?? 'instructors');
if (!in_array($activeTab, ['instructors', 'add'], true)) {
    $activeTab = 'instructors';
}
?>

<!-- ============================================================
     Page header
     ============================================================ -->
<div class="a-page-header">
  <div>
    <h1>Instructors</h1>
    <p>Manage instructor accounts, course assignments, and invitations.</p>
  </div>
  <a href="<?= BASE ?>/admin/instructors.php?tab=add" class="a-btn a-btn--primary">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
         style="vertical-align:middle;margin-right:.3rem" aria-hidden="true">
      <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
    </svg>
    Invite Instructor
  </a>
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
     Tabs
     ============================================================ -->
<div style="display:flex;gap:.15rem;border-bottom:2px solid var(--a-border);margin-bottom:1.5rem">
  <a
    href="<?= BASE ?>/admin/instructors.php?tab=instructors"
    style="padding:.6rem 1.1rem;font-size:.875rem;font-weight:600;text-decoration:none;border-bottom:3px solid transparent;margin-bottom:-2px;
           <?= $activeTab === 'instructors' ? 'border-color:var(--a-primary,#6366f1);color:var(--a-primary,#6366f1)' : 'color:var(--a-text-muted)' ?>"
  >
    Active Instructors <span class="a-badge" style="margin-left:.35rem"><?= count($instructors) ?></span>
  </a>
  <a
    href="<?= BASE ?>/admin/instructors.php?tab=add"
    style="padding:.6rem 1.1rem;font-size:.875rem;font-weight:600;text-decoration:none;border-bottom:3px solid transparent;margin-bottom:-2px;
           <?= $activeTab === 'add' ? 'border-color:var(--a-primary,#6366f1);color:var(--a-primary,#6366f1)' : 'color:var(--a-text-muted)' ?>"
  >
    Add Instructor
  </a>
</div>

<!-- ============================================================
     TAB: Active Instructors
     ============================================================ -->
<?php if ($activeTab === 'instructors'): ?>

<?php if (empty($instructors)): ?>
<div class="a-card" style="text-align:center;padding:3rem 1.5rem">
  <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
       stroke="#d1d5db" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
       style="display:block;margin:0 auto 1rem" aria-hidden="true">
    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
    <circle cx="9" cy="7" r="4"/>
    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
  </svg>
  <p style="color:var(--a-text-muted);font-size:.9rem">No instructors yet.</p>
  <a href="<?= BASE ?>/admin/instructors.php?tab=add" class="a-btn a-btn--primary a-btn--sm" style="margin-top:.75rem">
    Add First Instructor
  </a>
</div>
<?php else: ?>

<div class="a-table-card">
  <table>
    <thead>
      <tr>
        <th>Instructor</th>
        <th>Assigned Courses</th>
        <th>Status</th>
        <th>Joined</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($instructors as $instr): ?>
      <?php
          $instrId      = (int)$instr['id'];
          $instrName    = htmlspecialchars((string)$instr['full_name']);
          $instrEmail   = htmlspecialchars((string)$instr['email']);
          $initials     = strtoupper(mb_substr((string)$instr['full_name'], 0, 2));
          $courseCount  = (int)$instr['course_count'];
          $joinedDate   = date('M j, Y', strtotime((string)$instr['created_at']));
          $status       = (string)$instr['account_status'];
          $statusClass  = $status === 'active' ? 'a-badge--success' : 'a-badge--warning';
          $instrCourses = $assignments[$instrId] ?? [];
      ?>
      <tr>
        <td>
          <div class="a-user-cell">
            <div class="a-user-avatar"><?= $initials ?></div>
            <div>
              <div class="a-user-name"><?= $instrName ?></div>
              <div class="a-user-email"><?= $instrEmail ?></div>
            </div>
          </div>
        </td>
        <td>
          <span class="a-badge a-badge--primary" style="font-size:.78rem"><?= $courseCount ?> course<?= $courseCount !== 1 ? 's' : '' ?></span>
        </td>
        <td>
          <span class="a-badge <?= $statusClass ?>"><?= htmlspecialchars($status) ?></span>
        </td>
        <td style="font-size:.82rem;white-space:nowrap"><?= $joinedDate ?></td>
        <td>
          <div style="display:flex;gap:.4rem;flex-wrap:wrap">
            <button
              class="a-btn a-btn--ghost a-btn--sm"
              onclick="openAssignModal(<?= $instrId ?>, <?= htmlspecialchars(json_encode($instrCourses), ENT_QUOTES) ?>)"
            >
              Assign Courses
            </button>
            <form method="post" onsubmit="return confirm('Demote this instructor back to a regular user?')">
              <input type="hidden" name="action"         value="demote_instructor">
              <input type="hidden" name="target_user_id" value="<?= $instrId ?>">
              <button class="a-btn a-btn--danger a-btn--sm" type="submit">Demote</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php endif; // empty instructors ?>

<?php endif; // tab=instructors ?>

<!-- ============================================================
     TAB: Add Instructor
     ============================================================ -->
<?php if ($activeTab === 'add'): ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem">

  <!-- ---- Promote existing user ---- -->
  <div class="a-card">
    <div class="a-card-head">
      <h3>Promote Existing User</h3>
      <p style="font-size:.82rem;color:var(--a-text-muted);margin-top:.25rem">
        Search by email and grant instructor access to an existing account.
      </p>
    </div>
    <div class="a-card-body">
      <form method="post" id="promoteForm">
        <input type="hidden" name="action"         value="promote_to_instructor">
        <input type="hidden" name="target_user_id" id="promoteUserId" value="">

        <div class="a-form-group" style="margin-bottom:.75rem">
          <label>Search by Email</label>
          <div style="display:flex;gap:.5rem">
            <input
              type="email"
              id="promoteEmailInput"
              class="a-bar-input"
              placeholder="user@example.com"
              style="flex:1"
              autocomplete="off"
            >
            <button type="button" class="a-btn a-btn--ghost a-btn--sm" onclick="searchUserByEmail()">
              Search
            </button>
          </div>
        </div>

        <div id="promoteUserResult" style="display:none;margin-bottom:.85rem">
          <div class="a-card" style="padding:.75rem 1rem;background:var(--a-surface-alt,#f8f9fb)">
            <div class="a-user-cell">
              <div class="a-user-avatar" id="promoteUserAvatar" style="width:36px;height:36px;font-size:.8rem;flex-shrink:0"></div>
              <div>
                <div id="promoteUserName"  style="font-weight:600;font-size:.875rem"></div>
                <div id="promoteUserEmail" style="font-size:.78rem;color:var(--a-text-muted)"></div>
                <div id="promoteUserRole"  style="font-size:.75rem;margin-top:.15rem"></div>
              </div>
            </div>
          </div>
        </div>

        <div id="promoteError"
             style="display:none;padding:.6rem .8rem;border-radius:6px;font-size:.82rem;
                    background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;margin-bottom:.75rem">
        </div>

        <button
          class="a-btn a-btn--primary"
          type="submit"
          id="promoteBtn"
          disabled
          style="width:100%"
        >
          Promote to Instructor
        </button>
      </form>
    </div>
  </div>

  <!-- ---- Invite new instructor ---- -->
  <div class="a-card">
    <div class="a-card-head">
      <h3>Invite New Instructor</h3>
      <p style="font-size:.82rem;color:var(--a-text-muted);margin-top:.25rem">
        Create a new instructor account and send them a welcome email with login credentials.
      </p>
    </div>
    <div class="a-card-body">
      <form method="post">
        <input type="hidden" name="action" value="invite_instructor">
        <div class="a-form-grid">
          <div class="a-form-group">
            <label>Full Name</label>
            <input
              name="invite_name"
              class="a-bar-input"
              style="padding:.55rem .75rem"
              placeholder="Jane Doe"
              required
            >
          </div>
          <div class="a-form-group">
            <label>Email Address</label>
            <input
              name="invite_email"
              type="email"
              class="a-bar-input"
              style="padding:.55rem .75rem"
              placeholder="jane@example.com"
              required
            >
          </div>
        </div>
        <button class="a-btn a-btn--primary" type="submit" style="margin-top:.5rem;width:100%">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
               style="vertical-align:middle;margin-right:.3rem" aria-hidden="true">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
            <polyline points="22,6 12,13 2,6"/>
          </svg>
          Send Invitation
        </button>
      </form>
    </div>
  </div>

</div>

<?php endif; // tab=add ?>

<!-- ============================================================
     Assign Courses Modal
     ============================================================ -->
<div class="a-modal-bg" id="assignModal" style="display:none" onclick="if(event.target===this)closeAssignModal()">
  <div class="a-modal" style="max-width:520px;width:92%">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.1rem">
      <h3 style="font-size:1rem;font-weight:700;margin:0">Assign Courses</h3>
      <button
        onclick="closeAssignModal()"
        style="background:none;border:none;cursor:pointer;color:var(--a-text-muted);padding:.25rem;line-height:1"
        aria-label="Close"
      >
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>

    <form method="post" id="assignForm">
      <input type="hidden" name="action"         value="assign_courses">
      <input type="hidden" name="target_user_id" id="assignInstrId" value="">

      <p style="font-size:.82rem;color:var(--a-text-muted);margin-bottom:.85rem">
        Select the courses this instructor can manage. Unchecking removes the assignment.
      </p>

      <?php if (empty($allCourses)): ?>
      <p style="color:var(--a-text-muted);font-size:.875rem">No active courses available.</p>
      <?php else: ?>
      <div style="max-height:320px;overflow-y:auto;border:1px solid var(--a-border);border-radius:8px;padding:.5rem">
        <?php foreach ($allCourses as $course): ?>
        <label style="display:flex;align-items:center;gap:.6rem;padding:.5rem .65rem;border-radius:6px;cursor:pointer;
                       font-size:.875rem;transition:background .15s" class="course-option">
          <input
            type="checkbox"
            name="course_ids[]"
            value="<?= (int)$course['id'] ?>"
            class="assign-checkbox"
            data-course-id="<?= (int)$course['id'] ?>"
          >
          <?= htmlspecialchars((string)$course['title']) ?>
        </label>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div style="display:flex;gap:.6rem;justify-content:flex-end;margin-top:1.1rem">
        <button type="button" class="a-btn a-btn--ghost" onclick="closeAssignModal()">Cancel</button>
        <button type="submit" class="a-btn a-btn--primary">Save Assignments</button>
      </div>
    </form>
  </div>
</div>

<!-- ============================================================
     JavaScript
     ============================================================ -->
<script>
// ---- Assign Courses Modal ----
function openAssignModal(instrId, currentCourseIds) {
  document.getElementById('assignInstrId').value = instrId;

  // Reset all checkboxes, then check current assignments
  document.querySelectorAll('.assign-checkbox').forEach(cb => {
    cb.checked = currentCourseIds.includes(parseInt(cb.dataset.courseId, 10));
  });

  document.getElementById('assignModal').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeAssignModal() {
  document.getElementById('assignModal').style.display = 'none';
  document.body.style.overflow = '';
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeAssignModal();
});

// ---- Promote user search ----
async function searchUserByEmail() {
  const email     = document.getElementById('promoteEmailInput').value.trim();
  const resultDiv = document.getElementById('promoteUserResult');
  const errorDiv  = document.getElementById('promoteError');
  const promoteBtn = document.getElementById('promoteBtn');
  const userIdInput = document.getElementById('promoteUserId');

  resultDiv.style.display = 'none';
  errorDiv.style.display  = 'none';
  promoteBtn.disabled     = true;
  userIdInput.value       = '';

  if (!email) {
    errorDiv.textContent    = 'Please enter an email address.';
    errorDiv.style.display  = 'block';
    return;
  }

  try {
    const resp = await fetch(
      '<?= BASE ?>/api/admin-user-search.php?email=' + encodeURIComponent(email)
    );

    // If the endpoint doesn't exist yet, fall back gracefully
    if (resp.status === 404) {
      // Use a simple form-based POST fallback: just set a hidden search field
      // and let the server verify on promote_to_instructor submission.
      // For now, show a manual-entry note.
      errorDiv.textContent   = 'User lookup API not available. Enter the User ID manually below.';
      errorDiv.style.display = 'block';
      return;
    }

    const data = await resp.json();

    if (!data.ok || !data.user) {
      errorDiv.textContent   = data.error === 'not_found'
        ? 'No account found with that email address.'
        : (data.message || 'Search failed.');
      errorDiv.style.display = 'block';
      return;
    }

    const u = data.user;

    if (u.role === 'admin') {
      errorDiv.textContent   = 'This user is already an administrator.';
      errorDiv.style.display = 'block';
      return;
    }

    if (u.role === 'instructor') {
      errorDiv.textContent   = 'This user is already an instructor.';
      errorDiv.style.display = 'block';
      return;
    }

    // Show result card
    const initials = (u.full_name || u.email || '?').slice(0, 2).toUpperCase();
    document.getElementById('promoteUserAvatar').textContent = initials;
    document.getElementById('promoteUserName').textContent   = u.full_name || '(No name)';
    document.getElementById('promoteUserEmail').textContent  = u.email;
    document.getElementById('promoteUserRole').innerHTML     =
      '<span class="a-badge">' + (u.role || 'user') + '</span>';

    userIdInput.value       = u.id;
    promoteBtn.disabled     = false;
    resultDiv.style.display = 'block';

  } catch (err) {
    errorDiv.textContent   = 'Network error: ' + err.message;
    errorDiv.style.display = 'block';
  }
}

// Allow pressing Enter in the email field to trigger search
document.getElementById('promoteEmailInput')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') { e.preventDefault(); searchUserByEmail(); }
});

// Hover highlight for course options
document.querySelectorAll('.course-option').forEach(lbl => {
  lbl.addEventListener('mouseenter', () => lbl.style.background = 'var(--a-surface-alt,#f8f9fb)');
  lbl.addEventListener('mouseleave', () => lbl.style.background = '');
});
</script>

<?php require_once __DIR__ . '/_foot.php'; ?>
