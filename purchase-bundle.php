<?php
if (!defined('BASE')) define('BASE', '');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/bundles-repo.php';
require_once __DIR__ . '/includes/mailer.php';

/* ── Only allow POST ─────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE . '/bundles.php');
    exit;
}

/* ── Must be logged in ───────────────────────────────────────────────────── */
$user = auth_current_user();
if (!$user) {
    header('Location: ' . BASE . '/login.php?redirect=' . urlencode(BASE . '/bundles.php'));
    exit;
}

$clientId = (int)$user['id'];
$bundleId = (int)($_POST['bundle_id'] ?? 0);

if ($bundleId <= 0) {
    header('Location: ' . BASE . '/bundles.php');
    exit;
}

ensure_bundle_tables($mysqli);

/* ── Load bundle ─────────────────────────────────────────────────────────── */
$bundle = get_bundle_by_id($mysqli, $bundleId);
if (!$bundle || !(int)$bundle['is_active']) {
    header('Location: ' . BASE . '/bundles.php?error=not_found');
    exit;
}

/* ── Check not already purchased ─────────────────────────────────────────── */
if (has_user_purchased_bundle($mysqli, $clientId, $bundleId)) {
    header('Location: ' . BASE . '/my-courses.php?bundle_enrolled=1');
    exit;
}

/* ── Process purchase ────────────────────────────────────────────────────── */
$amount = (float)$bundle['price'];

if (!purchase_bundle($mysqli, $clientId, $bundleId, $amount)) {
    header('Location: ' . BASE . '/bundles.php?error=purchase_failed');
    exit;
}

/* ── Send confirmation email ─────────────────────────────────────────────── */
$toEmail  = (string)($user['email']     ?? '');
$toName   = (string)($user['full_name'] ?? 'Student');
$subject  = 'Your NerdAcademy Bundle is ready!';
$myCoursesUrl = 'http' . ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE . '/my-courses.php';

$courseListHtml = '';
$courseListText = '';
foreach ($bundle['courses'] as $c) {
    $t = htmlspecialchars($c['title'] ?? '', ENT_QUOTES);
    $courseListHtml .= "<li style='margin:.45rem 0;color:#334155'>✨ {$t}</li>";
    $courseListText .= '  - ' . ($c['title'] ?? '') . "\n";
}

$htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"></head>
<body style="font-family:Inter,Arial,sans-serif;background:linear-gradient(160deg,#eef2ff 0%,#f8fafc 48%,#e0f2fe 100%);padding:2rem;color:#1e293b">
  <div style="max-width:640px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:20px;overflow:hidden;box-shadow:0 18px 40px rgba(79,70,229,.14)">
    <div style="background:linear-gradient(135deg,#4338ca 0%,#6366f1 50%,#8b5cf6 100%);padding:2rem;text-align:center;color:#fff">
      <div style="font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;opacity:.9">Bundle Purchase Confirmed</div>
      <h1 style="color:#fff;margin:.6rem 0 0;font-size:1.8rem;line-height:1.25">Bundle Unlocked 🚀</h1>
    </div>
    <div style="padding:2rem">
      <p style="margin:0 0 1rem;font-size:1rem;line-height:1.8">Hi <strong>{$toName}</strong>,</p>
      <p style="margin:0 0 1.2rem;font-size:.96rem;line-height:1.8;color:#475569">Your <strong style="color:#312e81">{$bundle['title']}</strong> bundle is now active. We have added every included course to your learning library.</p>

      <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:16px;padding:1rem 1.1rem;margin:0 0 1.4rem">
        <div style="font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#4f46e5;margin-bottom:.65rem">Included courses</div>
        <ul style="padding-left:1.1rem;margin:0;line-height:1.9">
          {$courseListHtml}
        </ul>
      </div>

      <div style="text-align:center">
        <a href="{$myCoursesUrl}"
           style="display:inline-block;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;padding:.9rem 2rem;border-radius:10px;text-decoration:none;font-weight:700">
          Open My Courses
        </a>
      </div>
      <p style="margin:1.4rem 0 0;font-size:.84rem;color:#64748b;text-align:center;line-height:1.8">
        Happy learning — your next AI breakthrough could start with the very first lesson.
      </p>
    </div>
  </div>
</body>
</html>
HTML;

$textBody = "Hi {$toName},\n\n"
          . "Your \"{$bundle['title']}\" bundle is now active.\n\n"
          . "Included courses:\n{$courseListText}\n"
          . "Open your library: {$myCoursesUrl}\n\n"
          . "Happy learning!\n— The NerdAcademy Team";
if ($toEmail !== '') {
    send_smtp_mail($toEmail, $toName, $subject, $htmlBody, $textBody);
}

/* ── Redirect to my-courses ──────────────────────────────────────────────── */
header('Location: ' . BASE . '/my-courses.php?bundle_enrolled=1');
exit;
