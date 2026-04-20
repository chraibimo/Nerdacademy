<?php
$page_title = 'Create Account';
$page_desc  = 'Join NerdAcademy and start learning AI today.';

if (!defined('BASE')) define('BASE', '');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/email-verification.php';

if (auth_current_user()) {
    header('Location: ' . BASE . '/index.php');
    exit;
}

$error = '';
$success = false;
$mailWarn = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($name === '') {
        $error = 'Please enter your full name.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $passwordHash = password_hash($pass, PASSWORD_DEFAULT);
        $uid = 'local_' . bin2hex(random_bytes(16));
        $providerJson = json_encode(['password']);

        $sql = "INSERT INTO clients (
            user_uid, full_name, email, password_hash, email_verified, account_status, role, is_admin, provider_ids_json
        ) VALUES (?, ?, ?, ?, 0, 'pending_verification', 'user', 0, ?)";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            $error = 'Unable to prepare registration query.';
        } else {
            $stmt->bind_param('sssss', $uid, $name, $email, $passwordHash, $providerJson);
            if (!$stmt->execute()) {
                if ((int)$stmt->errno === 1062) {
                    $error = 'An account with this email already exists.';
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            } else {
                $success = true;
                $clientId = (int)$stmt->insert_id;
                $mailError = null;
                if (!send_verification_email($mysqli, $clientId, $name, $email, $mailError)) {
                    $mailWarn = 'Account created, but verification email could not be sent right now. ' . ($mailError ?: 'Try again from sign-in page.');
                }
            }
            $stmt->close();
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
                <circle cx="14" cy="14" r="13" stroke="url(#rlg)" stroke-width="2"/>
                <circle cx="14" cy="8"  r="2.5" fill="#a78bfa"/>
                <circle cx="8"  cy="18" r="2.5" fill="#38bdf8"/>
                <circle cx="20" cy="18" r="2.5" fill="#34d399"/>
                <line x1="14" y1="10.5" x2="8"  y2="15.5" stroke="#ffffff40" stroke-width="1.5"/>
                <line x1="14" y1="10.5" x2="20" y2="15.5" stroke="#ffffff40" stroke-width="1.5"/>
                <line x1="8"  y1="18"   x2="20" y2="18"   stroke="#ffffff40" stroke-width="1.5"/>
                <defs><linearGradient id="rlg" x1="0" y1="0" x2="28" y2="28" gradientUnits="userSpaceOnUse"><stop stop-color="#7c3aed"/><stop offset="1" stop-color="#0ea5e9"/></linearGradient></defs>
            </svg>
            <span class="logo-text">Nerd<span class="logo-accent">Academy</span></span>
        </a>

        <h1 class="auth-page-title">Let's get you started</h1>
        <p class="auth-page-subtitle">You're about to be in very good company.</p>

        <?php if ($error): ?>
            <div class="auth-page-error show"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="auth-page-error show" style="color:var(--accent-green);background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2)">
                You're in! Check your inbox to verify your email and you're good to go.
            </div>
            <?php if ($mailWarn): ?>
            <div class="auth-page-error show" style="margin-top:.75rem"><?php echo htmlspecialchars($mailWarn); ?></div>
            <?php endif; ?>
            <a href="<?php echo BASE; ?>/login.php" class="btn-primary btn-full btn-lg" style="display:flex;justify-content:center">Go to Sign In</a>
        <?php else: ?>
            <form method="post" novalidate>
                <div class="form-group">
                    <label for="regName">Full Name</label>
                    <input type="text" id="regName" name="name" class="form-input" placeholder="Your full name" value="<?php echo htmlspecialchars((string)($_POST['name'] ?? '')); ?>" required>
                </div>
                <div class="form-group">
                    <label for="regEmail">Email Address</label>
                    <input type="email" id="regEmail" name="email" class="form-input" placeholder="your@email.com" value="<?php echo htmlspecialchars((string)($_POST['email'] ?? '')); ?>" required>
                </div>
                <div class="form-group">
                    <label for="regPass">Password</label>
                    <input type="password" id="regPass" name="password" class="form-input" placeholder="Min. 6 characters" required>
                </div>
                <div class="form-group">
                    <label for="regConfirm">Confirm Password</label>
                    <input type="password" id="regConfirm" name="confirm_password" class="form-input" placeholder="Repeat your password" required>
                </div>
                <button type="submit" class="btn-primary btn-full btn-lg">Create Account</button>
            </form>
        <?php endif; ?>

        <p class="auth-page-switch">
            Been here before? <a href="<?php echo BASE; ?>/login.php">Sign in</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
