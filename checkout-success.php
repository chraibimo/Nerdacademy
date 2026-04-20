<?php

if (!defined('BASE')) define('BASE', '');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/courses-repo.php';
require_once __DIR__ . '/includes/purchases-repo.php';
require_once __DIR__ . '/includes/stripe-api.php';
require_once __DIR__ . '/includes/mailer.php';

// ── 1. Read GET params ────────────────────────────────────────────────────────
$sessionId = trim((string) ($_GET['session_id'] ?? ''));
$courseId  = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;

if ($sessionId === '' || $courseId <= 0) {
    header('Location: ' . BASE . '/courses.php');
    exit;
}

// ── 2. User must be logged in ─────────────────────────────────────────────────
$currentUser = auth_current_user();
if (!$currentUser) {
    header('Location: ' . BASE . '/login.php');
    exit;
}

$clientId = (int) $currentUser['id'];

// ── 3. Retrieve Stripe session ────────────────────────────────────────────────
try {
    $session = stripe_retrieve_checkout_session($sessionId);
} catch (RuntimeException $e) {
    error_log('checkout-success: failed to retrieve Stripe session ' . $sessionId . ': ' . $e->getMessage());
    header('Location: ' . BASE . '/courses.php?payment_error=1');
    exit;
}

// ── 4. Payment status must be 'paid' ──────────────────────────────────────────
if (($session['payment_status'] ?? '') !== 'paid') {
    header('Location: ' . BASE . '/course.php?id=' . $courseId . '&payment_error=1');
    exit;
}

// ── 5. Security: metadata client_id must match current user ───────────────────
$metaClientId = (int) ($session['metadata']['client_id'] ?? 0);
if ($metaClientId !== $clientId) {
    error_log('checkout-success: client_id mismatch. Session client=' . $metaClientId . ' current=' . $clientId);
    header('Location: ' . BASE . '/courses.php');
    exit;
}

// ── 6. Idempotency: check not already enrolled ────────────────────────────────
ensure_purchases_table($mysqli);

if (has_user_enrolled_course($mysqli, $clientId, $courseId)) {
    // Already enrolled — just redirect to player
    header('Location: ' . BASE . '/course-player.php?course=' . $courseId);
    exit;
}

// ── 7. Load course for email content ─────────────────────────────────────────
$course = find_course_by_id($mysqli, $courseId);
if (!$course) {
    header('Location: ' . BASE . '/courses.php');
    exit;
}

// ── 8. Insert purchase row ────────────────────────────────────────────────────
$couponCode      = (string) ($session['metadata']['coupon_code'] ?? '');
$discountPercent = (float)  ($session['metadata']['discount_percent'] ?? 0);
$originalAmount  = (float)  $course['price'];

// Amount actually charged is in session's amount_total (cents)
$amountCharged   = isset($session['amount_total']) ? round((float) $session['amount_total'] / 100, 2) : 0.0;

$stmt = $mysqli->prepare(
    'INSERT INTO purchases (client_id, course_id, original_amount, amount, coupon_code, discount_percent, currency, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);

if (!$stmt) {
    error_log('checkout-success: prepare failed for purchase insert, course=' . $courseId);
    header('Location: ' . BASE . '/course.php?id=' . $courseId . '&payment_error=1');
    exit;
}

$currency = 'USD';
$status   = 'completed';
$stmt->bind_param(
    'iiddsdss',
    $clientId, $courseId,
    $originalAmount, $amountCharged,
    $couponCode, $discountPercent,
    $currency, $status
);

$inserted = $stmt->execute();
$stmt->close();

if (!$inserted) {
    // Duplicate key = already enrolled (race condition), safe to proceed
    if ($mysqli->errno !== 1062) {
        error_log('checkout-success: insert failed for course=' . $courseId . ' error=' . $mysqli->error);
        header('Location: ' . BASE . '/course.php?id=' . $courseId . '&payment_error=1');
        exit;
    }
}

// ── 9. Record coupon usage ────────────────────────────────────────────────────
if ($couponCode !== '') {
    require_once __DIR__ . '/includes/coupons-repo.php';
    consume_coupon_code($mysqli, $couponCode);
}

// ── 10. Send purchase confirmation email ──────────────────────────────────────
try {
    $courseTitle = (string) $course['title'];
    $playerUrl   = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . BASE . '/course-player.php?course=' . $courseId;
    $userName    = (string) $currentUser['full_name'];
    $safeName    = htmlspecialchars($userName);
    $safeTitle   = htmlspecialchars($courseTitle);
    $safeUrl     = htmlspecialchars($playerUrl);
    $supportUrl  = htmlspecialchars('http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . BASE . '/support.php');

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#eef2ff;font-family:Inter,'Segoe UI',Roboto,Arial,sans-serif;color:#111827">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(160deg,#eef2ff 0%,#f8fafc 48%,#e0f2fe 100%);padding:32px 12px">
    <tr><td align="center">
      <table width="680" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid #e5e7eb;border-radius:20px;overflow:hidden;max-width:680px;width:100%;box-shadow:0 18px 45px rgba(79,70,229,.14)">
        <tr><td style="background:linear-gradient(135deg,#4338ca 0%,#6366f1 50%,#8b5cf6 100%);padding:30px 30px 24px;text-align:center;color:#ffffff">
          <div style="font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;opacity:.92">Purchase Confirmed</div>
          <h1 style="margin:12px 0 8px;font-size:30px;line-height:1.2;font-weight:800">You're enrolled in {$safeTitle}</h1>
          <p style="margin:0;font-size:15px;line-height:1.75;color:rgba(255,255,255,.92)">Everything is ready — lessons, resources, and your progress tracker are waiting.</p>
        </td></tr>
        <tr><td style="padding:30px">
          <p style="color:#111827;font-size:16px;line-height:1.75;margin:0 0 14px">Hi {$safeName},</p>
          <p style="color:#475569;font-size:15px;line-height:1.85;margin:0 0 18px">Thank you for your purchase. You now have full access to <strong style="color:#312e81">{$safeTitle}</strong> and can start learning whenever you're ready.</p>
          <div style="margin:18px 0;padding:18px;border-radius:16px;border:1px solid #e5e7eb;background:#f8fafc">
            <div style="font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#4f46e5;margin-bottom:10px">What's included</div>
            <ul style="margin:0;padding-left:18px;color:#334155;font-size:14px;line-height:1.9">
              <li>Lifetime access to your course materials</li>
              <li>Downloadable resources and project assets</li>
              <li>Certificate eligibility on completion</li>
              <li>Support from the NerdAcademy team</li>
            </ul>
          </div>
          <div style="text-align:center;margin:28px 0">
            <a href="{$safeUrl}" style="display:inline-block;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;text-decoration:none;padding:15px 34px;border-radius:10px;font-size:16px;font-weight:800;letter-spacing:.01em">
              Open My Course
            </a>
          </div>
          <p style="color:#64748b;font-size:13px;line-height:1.8;margin:0;text-align:center">
            Questions? Visit our <a href="{$supportUrl}" style="color:#4f46e5;text-decoration:none">support page</a> or reply to this email.
          </p>
        </td></tr>
        <tr><td style="padding:18px 30px;border-top:1px solid #e5e7eb;text-align:center;background:#fcfcff">
          <p style="color:#64748b;font-size:12px;margin:0">NerdAcademy · Practical AI learning for ambitious builders</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    $textBody = "Hi {$userName},\n\nThank you for your purchase. You are now enrolled in {$courseTitle}.\n\nOpen your course: {$playerUrl}\n\nNeed help? Visit {$supportUrl}\n\nWelcome to NerdAcademy.";

    send_smtp_mail(
        (string) $currentUser['email'],
        $userName,
        "You're enrolled in {$courseTitle}! 🎓",
        $htmlBody,
        $textBody
    );
} catch (Throwable $e) {
    // Never block enrollment due to email failure
    error_log('checkout-success: email send failed: ' . $e->getMessage());
}

// ── 11. Redirect to course player ─────────────────────────────────────────────
header('Location: ' . BASE . '/course-player.php?course=' . $courseId . '&enrolled=1');
exit;
