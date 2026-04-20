<?php
$page_title = 'Forgot Password';
$page_desc  = 'Reset your NerdAcademy password securely.';

if (!defined('BASE')) define('BASE', '');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/email-verification.php';

if (auth_current_user()) {
    header('Location: ' . BASE . '/profile.php#password');
    exit;
}

function forgot_password_mask_email(string $email): string
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

if (isset($_SESSION['password_reset_verified_client_id'], $_SESSION['password_reset_verified_email'], $_SESSION['password_reset_verified_at'])) {
    $verifiedAt = (int)$_SESSION['password_reset_verified_at'];
    if ($verifiedAt >= (time() - 900)) {
        header('Location: ' . BASE . '/reset-password.php');
        exit;
    }

    unset($_SESSION['password_reset_verified_client_id'], $_SESSION['password_reset_verified_email'], $_SESSION['password_reset_verified_at']);
}

$error = '';
$success = '';
$emailValue = trim((string)($_POST['email'] ?? ($_SESSION['password_reset_email'] ?? '')));
$showVerifyForm = $emailValue !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_reset_code'])) {
        $emailValue = trim((string)($_POST['email'] ?? ''));
        $showVerifyForm = false;

        if (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $_SESSION['password_reset_email'] = $emailValue;

            $stmt = $mysqli->prepare('SELECT id, full_name, email, account_status FROM clients WHERE email = ? LIMIT 1');
            if (!$stmt) {
                $error = 'Unable to prepare password reset request.';
            } else {
                $stmt->bind_param('s', $emailValue);
                if (!$stmt->execute()) {
                    $error = 'Unable to process your request right now.';
                } else {
                    $res = $stmt->get_result();
                    $row = $res ? $res->fetch_assoc() : null;

                    if ($row && ($row['account_status'] ?? '') === 'suspended') {
                        $error = 'This account is suspended. Please contact support.';
                        $showVerifyForm = false;
                        unset($_SESSION['password_reset_email']);
                    } elseif ($row) {
                        $mailError = null;
                        if (send_password_reset_code_email($mysqli, (int)$row['id'], (string)($row['full_name'] ?? ''), (string)($row['email'] ?? ''), $mailError)) {
                            $maskedEmail = forgot_password_mask_email((string)$row['email']);
                            $success = 'A 6-digit verification code was sent to ' . $maskedEmail . '. Enter it below to continue.';
                            $showVerifyForm = true;
                        } else {
                            $error = $mailError ?: 'We could not send the verification code right now.';
                            $showVerifyForm = false;
                        }
                    } else {
                        $success = 'If an account exists for that email, a 6-digit verification code has been sent.';
                        $showVerifyForm = true;
                    }
                }
                $stmt->close();
            }
        }
    }

    if (isset($_POST['verify_reset_code'])) {
        $emailValue = trim((string)($_POST['email'] ?? ($_SESSION['password_reset_email'] ?? '')));
        $code = preg_replace('/\D+/', '', (string)($_POST['verification_code'] ?? ''));
        $showVerifyForm = true;

        if (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter the same email address used to request the code.';
        } elseif ($code === '' || strlen($code) !== 6) {
            $error = 'Enter the 6-digit verification code from your email.';
        } else {
            $stmt = $mysqli->prepare('SELECT id FROM clients WHERE email = ? LIMIT 1');
            if (!$stmt) {
                $error = 'Unable to verify your password reset request.';
            } else {
                $stmt->bind_param('s', $emailValue);
                if (!$stmt->execute()) {
                    $error = 'Unable to verify your password reset request.';
                } else {
                    $res = $stmt->get_result();
                    $row = $res ? $res->fetch_assoc() : null;
                    if (!$row) {
                        $error = 'That code or email combination is invalid.';
                    } else {
                        $result = verify_password_reset_code_only($mysqli, (int)$row['id'], $code);
                        if (!($result['ok'] ?? false)) {
                            $reason = (string)($result['error'] ?? '');
                            if ($reason === 'invalid-code') {
                                $error = 'That verification code is not correct.';
                            } elseif ($reason === 'expired') {
                                $error = 'This verification code expired. Send a new one and try again.';
                            } else {
                                $error = 'Unable to verify that code right now.';
                            }
                        } else {
                            $_SESSION['password_reset_verified_client_id'] = (int)$row['id'];
                            $_SESSION['password_reset_verified_email'] = $emailValue;
                            $_SESSION['password_reset_verified_at'] = time();
                            unset($_SESSION['password_reset_email']);
                            header('Location: ' . BASE . '/reset-password.php');
                            exit;
                        }
                    }
                }
                $stmt->close();
            }
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
                <circle cx="14" cy="14" r="13" stroke="url(#fpg)" stroke-width="2"/>
                <circle cx="14" cy="8"  r="2.5" fill="#a78bfa"/>
                <circle cx="8"  cy="18" r="2.5" fill="#38bdf8"/>
                <circle cx="20" cy="18" r="2.5" fill="#34d399"/>
                <line x1="14" y1="10.5" x2="8"  y2="15.5" stroke="#ffffff40" stroke-width="1.5"/>
                <line x1="14" y1="10.5" x2="20" y2="15.5" stroke="#ffffff40" stroke-width="1.5"/>
                <line x1="8"  y1="18"   x2="20" y2="18"   stroke="#ffffff40" stroke-width="1.5"/>
                <defs><linearGradient id="fpg" x1="0" y1="0" x2="28" y2="28" gradientUnits="userSpaceOnUse"><stop stop-color="#7c3aed"/><stop offset="1" stop-color="#0ea5e9"/></linearGradient></defs>
            </svg>
            <span class="logo-text">Nerd<span class="logo-accent">Academy</span></span>
        </a>

        <h1 class="auth-page-title">Forgot your password?</h1>
        <p class="auth-page-subtitle">Step 1: request your email code. Step 2: verify it. Step 3: create a brand-new password.</p>

        <?php if ($error): ?>
            <div class="auth-page-error show"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="auth-page-error show" style="color:var(--accent-green);background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2)"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div style="margin-bottom:1rem;padding:1rem 1.1rem;border-radius:14px;background:var(--bg-elevated);border:1px solid var(--border);color:var(--text-secondary);line-height:1.7">
            Start by entering your email address. We will send you a <strong style="color:var(--text-primary)">6-digit verification code</strong>. Once the code is correct, you will be taken to a separate page to create your new password.
        </div>

        <form method="post" novalidate>
            <input type="hidden" name="send_reset_code" value="1">
            <div class="form-group">
                <label for="resetEmail">Email Address</label>
                <input type="email" id="resetEmail" name="email" class="form-input" placeholder="your@email.com" value="<?php echo htmlspecialchars($emailValue); ?>" required>
            </div>
            <button type="submit" class="btn-primary btn-full btn-lg">Send Verification Code</button>
        </form>

        <?php if ($showVerifyForm): ?>
            <form method="post" style="margin-top:1rem" novalidate>
                <input type="hidden" name="verify_reset_code" value="1">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($emailValue); ?>">
                <div class="form-group">
                    <label for="resetVerificationCode">Verification Code</label>
                    <input type="text" id="resetVerificationCode" name="verification_code" class="form-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Enter the 6-digit code" required>
                </div>
                <button type="submit" class="btn-ghost btn-full">Verify Code</button>
            </form>
        <?php endif; ?>

        <p class="auth-page-switch">
            Remembered it? <a href="<?php echo BASE; ?>/login.php">Back to sign in</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>