<?php

if (!defined('BASE')) define('BASE', '');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/courses-repo.php';
require_once __DIR__ . '/includes/purchases-repo.php';
require_once __DIR__ . '/includes/coupons-repo.php';
require_once __DIR__ . '/includes/stripe-api.php';
require_once __DIR__ . '/includes/stripe-config.php';
require_once __DIR__ . '/includes/checkout-orders-repo.php';

$courseId = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;
if ($courseId <= 0) {
    header('Location: ' . BASE . '/courses.php');
    exit;
}

// ── 2. User must be logged in ────────────────────────────────────────────────
$currentUser = auth_current_user();
if (!$currentUser) {
    header('Location: ' . BASE . '/login.php?redirect=' . urlencode(BASE . '/course.php?id=' . $courseId));
    exit;
}

$clientId = (int) $currentUser['id'];

// ── 3. Load course ───────────────────────────────────────────────────────────
ensure_purchases_table($mysqli);
$course = find_course_by_id($mysqli, $courseId);
if (!$course) {
    header('Location: ' . BASE . '/courses.php');
    exit;
}

// ── 4. Check not already enrolled ───────────────────────────────────────────
if (has_user_enrolled_course($mysqli, $clientId, $courseId)) {
    header('Location: ' . BASE . '/course-player.php?course=' . $courseId);
    exit;
}

// ── 5. Validate coupon if provided ───────────────────────────────────────────
$couponCodeInput = trim((string) ($_POST['coupon_code'] ?? ''));
$couponCode      = '';
$discountPercent = 0.0;

if ($couponCodeInput !== '') {
    $couponCheck = validate_coupon_code($mysqli, $couponCodeInput);
    if (!($couponCheck['ok'] ?? false)) {
        // Invalid coupon — bounce back to course page with error flag
        header('Location: ' . BASE . '/course.php?id=' . $courseId . '&coupon_error=1');
        exit;
    }
    $couponCode      = normalize_coupon_code($couponCodeInput);
    $discountPercent = (float) ($couponCheck['discount_percent'] ?? 0);
}

// ── 6. Calculate final price ─────────────────────────────────────────────────
$originalPrice = (float) $course['price'];
$finalPrice    = round($originalPrice * (1 - ($discountPercent / 100)), 2);

// ── 7a. Free / 100% coupon — enroll directly ─────────────────────────────────
if ($finalPrice <= 0) {
    $stmt = $mysqli->prepare(
        'INSERT INTO purchases (client_id, course_id, original_amount, amount, coupon_code, discount_percent, currency, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if ($stmt) {
        $currency = 'USD';
        $status   = 'completed';
        $stmt->bind_param(
            'iiddsdss',
            $clientId, $courseId,
            $originalPrice, $finalPrice,
            $couponCode, $discountPercent,
            $currency, $status
        );
        if ($stmt->execute()) {
            if ($couponCode !== '') {
                consume_coupon_code($mysqli, $couponCode);
            }

            // Send enrollment confirmation email (silently ignore errors)
            try {
                require_once __DIR__ . '/includes/mailer.php';
                $courseTitle   = htmlspecialchars((string) $course['title']);
                $playerUrl     = 'http://' . $_SERVER['HTTP_HOST'] . BASE . '/course-player.php?course=' . $courseId;
                $emailHtml     = _build_enrollment_email_html($courseTitle, $playerUrl, (string) $currentUser['full_name']);
                $emailText     = "Congratulations! You are now enrolled in {$courseTitle}.\n\nStart learning: {$playerUrl}";
                send_smtp_mail(
                    (string) $currentUser['email'],
                    (string) $currentUser['full_name'],
                    'Welcome to ' . $course['title'] . '!',
                    $emailHtml,
                    $emailText
                );
            } catch (Throwable $e) {
                // Silently ignore mail errors
            }
        }
        $stmt->close();
    }
    header('Location: ' . BASE . '/course-player.php?course=' . $courseId . '&enrolled=1');
    exit;
}

// ── 7b. Paid — create order record and redirect to clean checkout URL ────────
try {
    $orderId = create_checkout_order(
        $mysqli,
        $clientId,
        $courseId,
        $originalPrice,
        $finalPrice,
        $couponCode,
        $discountPercent
    );
} catch (RuntimeException $e) {
    error_log('checkout.php: create_checkout_order failed: ' . $e->getMessage());
    header('Location: ' . BASE . '/course.php?id=' . $courseId . '&payment_error=1');
    exit;
}

header('Location: ' . BASE . '/checkout/?order_id=' . urlencode($orderId));
exit;

// ── Helper: build HTML enrollment confirmation email ─────────────────────────
function _build_enrollment_email_html(string $courseTitle, string $playerUrl, string $userName): string
{
    $safeName  = htmlspecialchars($userName);
    $safeTitle = htmlspecialchars($courseTitle);
    $safeUrl   = htmlspecialchars($playerUrl);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#eef2ff;font-family:Inter,'Segoe UI',Roboto,Arial,sans-serif;color:#111827">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(160deg,#eef2ff 0%,#f8fafc 48%,#e0f2fe 100%);padding:32px 12px">
    <tr><td align="center">
      <table width="680" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid #e5e7eb;border-radius:20px;overflow:hidden;max-width:680px;width:100%;box-shadow:0 18px 45px rgba(79,70,229,.14)">
        <tr><td style="background:linear-gradient(135deg,#4338ca 0%,#6366f1 50%,#8b5cf6 100%);padding:30px 30px 24px;text-align:center;color:#ffffff">
          <div style="font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;opacity:.92">Enrollment Confirmed</div>
          <h1 style="margin:12px 0 8px;font-size:30px;line-height:1.2;font-weight:800">Welcome to {$safeTitle}</h1>
          <p style="margin:0;font-size:15px;line-height:1.75;color:rgba(255,255,255,.92)">Your next skill-building adventure starts today.</p>
        </td></tr>
        <tr><td style="padding:30px">
          <p style="color:#111827;font-size:16px;line-height:1.75;margin:0 0 14px">Hi {$safeName},</p>
          <p style="color:#475569;font-size:15px;line-height:1.85;margin:0 0 18px">Great choice — your access to <strong style="color:#312e81">{$safeTitle}</strong> is now live. Dive in whenever you are ready and pick up right where you left off.</p>

          <div style="margin:18px 0;padding:18px;border-radius:16px;border:1px solid #e5e7eb;background:#f8fafc">
            <div style="font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#4f46e5;margin-bottom:10px">What happens next</div>
            <ol style="margin:0;padding-left:18px;color:#334155;font-size:14px;line-height:1.9">
              <li>Open your course player</li>
              <li>Complete lessons and track progress automatically</li>
              <li>Build real projects and earn your certificate</li>
            </ol>
          </div>

          <div style="text-align:center;margin:28px 0">
            <a href="{$safeUrl}" style="display:inline-block;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;text-decoration:none;padding:15px 34px;border-radius:10px;font-size:16px;font-weight:800;letter-spacing:.01em">
              Open My Course
            </a>
          </div>

          <p style="color:#64748b;font-size:13px;line-height:1.8;margin:0;text-align:center">Need a hand? Visit NerdAcademy support anytime and we will help you keep moving.</p>
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
}
