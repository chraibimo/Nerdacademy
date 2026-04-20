<?php
$page_title = 'Profile';
$page_desc  = 'Manage your NerdAcademy profile and password.';

if (!defined('BASE')) define('BASE', '');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/email-verification.php';

$user = auth_current_user();
if (!$user) {
    header('Location: ' . BASE . '/login.php');
    exit;
}

function profile_mask_email(string $email): string
{
    if ($email === '' || strpos($email, '@') === false) {
        return $email;
    }

    [$local, $domain] = explode('@', $email, 2);
    $localMasked = strlen($local) <= 2 ? substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 1)) : substr($local, 0, 2) . str_repeat('*', max(2, strlen($local) - 2));
    $domainParts = explode('.', $domain);
    $domainName = $domainParts[0] ?? $domain;
    $tld = isset($domainParts[1]) ? '.' . implode('.', array_slice($domainParts, 1)) : '';
    $domainMasked = strlen($domainName) <= 2 ? substr($domainName, 0, 1) . '*' : substr($domainName, 0, 1) . str_repeat('*', max(2, strlen($domainName) - 1));

    return $localMasked . '@' . $domainMasked . $tld;
}

$error = '';
$success = '';
$pendingPasswordChange = get_pending_password_change_request($mysqli, (int)$user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim((string)($_POST['full_name'] ?? ''));
        if ($name === '') {
            $error = 'Display name cannot be empty.';
        } else {
            $stmt = $mysqli->prepare('UPDATE clients SET full_name = ?, updated_at = NOW() WHERE id = ?');
            if ($stmt) {
                $id = (int)$user['id'];
                $stmt->bind_param('si', $name, $id);
                if ($stmt->execute()) {
                    $success = 'Profile updated.';
                    $user['full_name'] = $name;
                } else {
                    $error = 'Unable to update profile.';
                }
                $stmt->close();
            } else {
                $error = 'Unable to prepare profile update.';
            }
        }
    }

    if (isset($_POST['send_password_code'])) {
        $currentPass = (string)($_POST['current_password'] ?? '');
        $newPass = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $storedHash = (string)($user['password_hash'] ?? '');

        if ($storedHash === '') {
            $error = 'No password is currently set for this account. Please contact support to secure it.';
        } elseif ($currentPass === '' || !password_verify($currentPass, $storedHash)) {
            $error = 'Your current password is incorrect.';
        } elseif (strlen($newPass) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Za-z]/', $newPass) || !preg_match('/\d/', $newPass)) {
            $error = 'Use at least one letter and one number in your new password.';
        } elseif ($newPass !== $confirm) {
            $error = 'New password and confirmation do not match.';
        } elseif (password_verify($newPass, $storedHash)) {
            $error = 'Your new password must be different from the current password.';
        } else {
            $mailError = null;
            if (send_password_change_code_email($mysqli, (int)$user['id'], (string)($user['full_name'] ?? ''), (string)($user['email'] ?? ''), $newPass, $mailError)) {
                $pendingPasswordChange = get_pending_password_change_request($mysqli, (int)$user['id']);
                $maskedEmail = profile_mask_email((string)($user['email'] ?? ''));
                $success = 'A 6-digit verification code was sent to ' . $maskedEmail . '. Enter it below to finish updating your password.';
            } else {
                $error = $mailError ?: 'We could not send the verification code right now.';
            }
        }
    }

    if (isset($_POST['confirm_password_change'])) {
        $code = preg_replace('/\D+/', '', (string)($_POST['verification_code'] ?? ''));
        if ($code === '' || strlen($code) !== 6) {
            $error = 'Enter the 6-digit code sent to your email.';
        } else {
            $result = confirm_password_change_code($mysqli, (int)$user['id'], $code);
            if (!($result['ok'] ?? false)) {
                $reason = (string)($result['error'] ?? '');
                if ($reason === 'invalid-code') {
                    $error = 'That verification code is not correct.';
                } elseif ($reason === 'expired') {
                    $error = 'This verification code expired. Request a new one and try again.';
                } elseif ($reason === 'no-request') {
                    $error = 'No pending password change request was found. Start again to receive a fresh code.';
                } else {
                    $error = 'Unable to verify the code right now.';
                }
            } else {
                $success = 'Password updated successfully.';
            }
        }
    }

    $pendingPasswordChange = get_pending_password_change_request($mysqli, (int)$user['id']);
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="settings-wrap">
    <div style="margin-bottom:2rem;display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap">
        <div>
            <div class="section-tag" style="margin-bottom:.75rem">Account</div>
            <h1 style="font-family:'Space Grotesk',sans-serif;font-size:2rem;font-weight:800;color:var(--text-primary);letter-spacing:-.3px">Profile</h1>
            <p style="color:var(--text-muted);margin-top:.35rem">Update your personal details and confirm password changes with a secure email code.</p>
        </div>
        <a href="<?php echo BASE; ?>/settings.php" class="btn-ghost">Open Settings</a>
    </div>

    <?php if ($error): ?>
        <div class="auth-page-error show" style="margin-bottom:1rem"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="auth-page-error show" style="margin-bottom:1rem;color:var(--accent-green);background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2)"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="settings-section">
        <div class="settings-section-head"><h2>Profile</h2></div>
        <div class="settings-section-body">
            <form method="post">
                <input type="hidden" name="update_profile" value="1">
                <div class="form-group">
                    <label for="full_name">Display Name</label>
                    <input type="text" id="full_name" name="full_name" class="form-input" value="<?php echo htmlspecialchars((string)$user['full_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" class="form-input" value="<?php echo htmlspecialchars((string)$user['email']); ?>" disabled>
                </div>
                <button type="submit" class="btn-primary">Save Changes</button>
            </form>
        </div>
    </div>

    <div class="settings-section" id="password">
        <div class="settings-section-head"><h2>Password &amp; Security</h2></div>
        <div class="settings-section-body">
            <div style="margin-bottom:1rem;padding:1rem 1.1rem;border-radius:14px;background:var(--bg-elevated);border:1px solid var(--border);color:var(--text-secondary);line-height:1.7">
                Enter your <strong style="color:var(--text-primary)">current password</strong>, choose a new one, and we will send a <strong style="color:var(--text-primary)">6-digit verification code</strong> to your email before the change is applied.
            </div>

            <form method="post" style="margin-bottom:1.1rem">
                <input type="hidden" name="send_password_code" value="1">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-input" autocomplete="current-password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-input" autocomplete="new-password" minlength="8" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" autocomplete="new-password" minlength="8" required>
                </div>
                <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
                    <button type="submit" class="btn-primary">Send Verification Code</button>
                    <span style="color:var(--text-muted);font-size:.85rem">Your code will expire in 15 minutes.</span>
                </div>
            </form>

            <?php if ($pendingPasswordChange): ?>
                <div style="margin:1rem 0;padding:1rem 1.1rem;border-radius:14px;background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.2);color:var(--text-secondary)">
                    We have a pending password change for <strong style="color:var(--text-primary)"><?php echo htmlspecialchars(profile_mask_email((string)($user['email'] ?? ''))); ?></strong>.
                    Enter the verification code below to approve it.
                </div>
                <form method="post">
                    <input type="hidden" name="confirm_password_change" value="1">
                    <div class="form-group">
                        <label for="verification_code">Email Verification Code</label>
                        <input type="text" id="verification_code" name="verification_code" class="form-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Enter the 6-digit code" required>
                    </div>
                    <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
                        <button type="submit" class="btn-primary">Verify &amp; Update Password</button>
                        <span style="color:var(--text-muted);font-size:.85rem">Didn't receive it? Send a new code using the form above.</span>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
