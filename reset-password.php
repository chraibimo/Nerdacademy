<?php
$page_title = 'Create New Password';
$page_desc  = 'Create a new password for your NerdAcademy account.';

if (!defined('BASE')) define('BASE', '');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/email-verification.php';

$verifiedClientId = isset($_SESSION['password_reset_verified_client_id']) ? (int)$_SESSION['password_reset_verified_client_id'] : 0;
$verifiedEmail = (string)($_SESSION['password_reset_verified_email'] ?? '');
$verifiedAt = isset($_SESSION['password_reset_verified_at']) ? (int)$_SESSION['password_reset_verified_at'] : 0;

if ($verifiedClientId <= 0 || $verifiedEmail === '' || $verifiedAt < (time() - 900)) {
    unset($_SESSION['password_reset_verified_client_id'], $_SESSION['password_reset_verified_email'], $_SESSION['password_reset_verified_at']);
    header('Location: ' . BASE . '/forgot-password.php');
    exit;
}

function reset_password_mask_email(string $email): string
{
    if ($email === '' || strpos($email, '@') === false) {
        return $email;
    }

    [$local, $domain] = explode('@', $email, 2);
    $localMasked = strlen($local) <= 2
        ? substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 1))
        : substr($local, 0, 2) . str_repeat('*', max(2, strlen($local) - 2));

    $domainParts = explode('.', $domain);
    $domainName = $domainParts[0] ?? $domain;
    $tld = isset($domainParts[1]) ? '.' . implode('.', array_slice($domainParts, 1)) : '';
    $domainMasked = strlen($domainName) <= 2
        ? substr($domainName, 0, 1) . '*'
        : substr($domainName, 0, 1) . str_repeat('*', max(2, strlen($domainName) - 1));

    return $localMasked . '@' . $domainMasked . $tld;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPass = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (strlen($newPass) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $newPass) || !preg_match('/\d/', $newPass)) {
        $error = 'Use at least one letter and one number in your new password.';
    } elseif ($newPass !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } else {
        $result = finalize_password_reset_after_verification($mysqli, $verifiedClientId, $newPass);
        if (!($result['ok'] ?? false)) {
            $reason = (string)($result['error'] ?? '');
            if ($reason === 'expired') {
                $error = 'Your verified reset session expired. Please request a new code.';
            } elseif ($reason === 'no-request') {
                $error = 'No verified password reset request was found. Please start again.';
            } else {
                $error = 'Unable to reset your password right now.';
            }
        } else {
            unset($_SESSION['password_reset_verified_client_id'], $_SESSION['password_reset_verified_email'], $_SESSION['password_reset_verified_at'], $_SESSION['password_reset_email']);
            $success = 'Your password has been updated successfully. You can sign in now.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page-bg"></div>

<div class="auth-page-wrap">
    <div class="auth-page-card">
        <a href="<?php echo BASE; ?>/index.php" class="auth-page-logo">
            <svg width="26" height="26" viewBox="0 0 28 28" fill="none">
                <circle cx="14" cy="14" r="13" stroke="url(#rpg)" stroke-width="2"/>
                <circle cx="14" cy="8"  r="2.5" fill="#a78bfa"/>
                <circle cx="8"  cy="18" r="2.5" fill="#38bdf8"/>
                <circle cx="20" cy="18" r="2.5" fill="#34d399"/>
                <line x1="14" y1="10.5" x2="8"  y2="15.5" stroke="#ffffff40" stroke-width="1.5"/>
                <line x1="14" y1="10.5" x2="20" y2="15.5" stroke="#ffffff40" stroke-width="1.5"/>
                <line x1="8"  y1="18"   x2="20" y2="18"   stroke="#ffffff40" stroke-width="1.5"/>
                <defs><linearGradient id="rpg" x1="0" y1="0" x2="28" y2="28" gradientUnits="userSpaceOnUse"><stop stop-color="#7c3aed"/><stop offset="1" stop-color="#0ea5e9"/></linearGradient></defs>
            </svg>
            <span class="logo-text">Nerd<span class="logo-accent">Academy</span></span>
        </a>

        <h1 class="auth-page-title">Create a new password</h1>
        <p class="auth-page-subtitle">Your code has been verified for <strong><?php echo htmlspecialchars(reset_password_mask_email($verifiedEmail)); ?></strong>. Set your new password below.</p>

        <?php if ($error): ?>
            <div class="auth-page-error show"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="auth-page-error show" style="color:var(--accent-green);background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2)"><?php echo htmlspecialchars($success); ?></div>
            <a href="<?php echo BASE; ?>/login.php" class="btn-primary btn-full btn-lg" style="display:flex;justify-content:center">Go to Sign In</a>
        <?php else: ?>
            <div style="margin-bottom:1rem;padding:1rem 1.1rem;border-radius:14px;background:var(--bg-elevated);border:1px solid var(--border);color:var(--text-secondary);line-height:1.7">
                Choose a strong password with at least <strong style="color:var(--text-primary)">8 characters</strong>, including <strong style="color:var(--text-primary)">letters and numbers</strong>.
            </div>

            <form method="post" novalidate>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-input" placeholder="At least 8 characters" minlength="8" autocomplete="new-password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Repeat your new password" minlength="8" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn-primary btn-full btn-lg">Update Password</button>
            </form>
        <?php endif; ?>

        <p class="auth-page-switch">
            Need another code? <a href="<?php echo BASE; ?>/forgot-password.php">Start over</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>