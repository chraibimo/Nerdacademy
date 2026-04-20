<?php
$page_title = 'Verify Email';
$page_desc = 'Activate your NerdAcademy account.';

if (!defined('BASE')) define('BASE', '');
require_once __DIR__ . '/includes/email-verification.php';

$token = trim((string)($_GET['token'] ?? ''));
$result = verify_email_token($mysqli, $token);

$ok = (bool)($result['ok'] ?? false);
$message = $ok
    ? 'Your email has been verified successfully. You can now sign in.'
    : 'Verification failed: ' . (string)($result['error'] ?? 'unknown-error');

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page-bg"></div>
<div class="auth-page-wrap">
  <div class="auth-page-card" style="text-align:center;max-width:520px">
    <h1 class="auth-page-title"><?php echo $ok ? 'Email Verified' : 'Verification Error'; ?></h1>
    <p class="auth-page-subtitle" style="margin-bottom:1.25rem"><?php echo htmlspecialchars($message); ?></p>
    <a href="<?php echo BASE; ?>/login.php" class="btn-primary btn-full btn-lg" style="display:flex;justify-content:center">Go to Sign In</a>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
