<?php
$page_title = 'Sign In';
$page_desc  = 'Sign in to your NerdAcademy account.';

if (!defined('BASE')) define('BASE', '');
require_once __DIR__ . '/includes/auth.php';

if (auth_current_user()) {
    header('Location: ' . BASE . '/index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $pass === '') {
        $error = 'Please provide your email and password.';
    } else {
        $stmt = $mysqli->prepare('SELECT id, password_hash, account_status, email_verified FROM clients WHERE email = ? LIMIT 1');
        if (!$stmt) {
            $error = 'Unable to prepare login query.';
        } else {
            $stmt->bind_param('s', $email);
            if (!$stmt->execute()) {
                $error = 'Login failed. Please try again.';
            } else {
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                if (!$row || empty($row['password_hash']) || !password_verify($pass, (string)$row['password_hash'])) {
                    $error = 'Invalid email or password.';
                } elseif (($row['account_status'] ?? '') === 'suspended') {
                    $error = 'Your account is suspended. Please contact support.';
                } elseif ((int)($row['email_verified'] ?? 0) !== 1) {
                    $error = 'Please verify your email before signing in.';
                } else {
                    $_SESSION['client_id'] = (int)$row['id'];
                    $up = $mysqli->prepare('UPDATE clients SET last_login_at = NOW(), updated_at = NOW() WHERE id = ?');
                    if ($up) {
                        $id = (int)$row['id'];
                        $up->bind_param('i', $id);
                        $up->execute();
                        $up->close();
                    }
                    header('Location: ' . BASE . '/index.php');
                    exit;
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
                <circle cx="14" cy="14" r="13" stroke="url(#llg)" stroke-width="2"/>
                <circle cx="14" cy="8"  r="2.5" fill="#a78bfa"/>
                <circle cx="8"  cy="18" r="2.5" fill="#38bdf8"/>
                <circle cx="20" cy="18" r="2.5" fill="#34d399"/>
                <line x1="14" y1="10.5" x2="8"  y2="15.5" stroke="#ffffff40" stroke-width="1.5"/>
                <line x1="14" y1="10.5" x2="20" y2="15.5" stroke="#ffffff40" stroke-width="1.5"/>
                <line x1="8"  y1="18"   x2="20" y2="18"   stroke="#ffffff40" stroke-width="1.5"/>
                <defs><linearGradient id="llg" x1="0" y1="0" x2="28" y2="28" gradientUnits="userSpaceOnUse"><stop stop-color="#7c3aed"/><stop offset="1" stop-color="#0ea5e9"/></linearGradient></defs>
            </svg>
            <span class="logo-text">Nerd<span class="logo-accent">Academy</span></span>
        </a>

        <h1 class="auth-page-title">Welcome back 👋</h1>
        <p class="auth-page-subtitle">Pick up right where you left off.</p>

        <?php if ($error): ?>
            <div class="auth-page-error show"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="auth-page-error show" style="color:var(--accent-green);background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2)"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <div class="form-group">
                <label for="loginEmail">Email Address</label>
                <input type="email" id="loginEmail" name="email" class="form-input" placeholder="your@email.com" value="<?php echo htmlspecialchars((string)($_POST['email'] ?? '')); ?>" required>
            </div>
            <div class="form-group">
                <label for="loginPass">Password</label>
                <input type="password" id="loginPass" name="password" class="form-input" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-primary btn-full btn-lg">Sign In</button>
        </form>

        <a href="<?php echo BASE; ?>/forgot-password.php" class="btn-ghost btn-full" style="margin-top:.9rem;display:flex;justify-content:center">Forgot Password?</a>

        <p class="auth-page-switch">
            New here? <a href="<?php echo BASE; ?>/register.php">Create a free account</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
