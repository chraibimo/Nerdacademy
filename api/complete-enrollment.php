<?php
/**
 * POST /api/complete-enrollment.php
 *
 * Called by checkout-page.php JS after stripe.confirmCardPayment() succeeds.
 * Verifies the PaymentIntent with Stripe, then creates the purchase record
 * and sends the confirmation email.
 *
 * Request body (JSON):
 *   { "payment_intent_id": "pi_xxx", "course_id": 42 }
 *
 * Response (JSON):
 *   { "success": true,  "redirect_url": "/ai-courses/course-player.php?course=42&enrolled=1" }
 *   { "success": false, "message": "..." }
 */
if (!defined('BASE')) define('BASE', '');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/courses-repo.php';
require_once dirname(__DIR__) . '/includes/purchases-repo.php';
require_once dirname(__DIR__) . '/includes/coupons-repo.php';
require_once dirname(__DIR__) . '/includes/stripe-api.php';
require_once dirname(__DIR__) . '/includes/checkout-orders-repo.php';

function json_fail(string $message, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// ── 1. Method ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_fail('Method not allowed.', 405);
}

// ── 2. Auth ───────────────────────────────────────────────────────────────────
$currentUser = auth_current_user();
if (!$currentUser) {
    json_fail('You must be logged in to complete a purchase.', 401);
}

$clientId = (int) $currentUser['id'];

// ── 3. Parse body ─────────────────────────────────────────────────────────────
$raw  = (string) file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    json_fail('Invalid request body.');
}

$paymentIntentId = trim((string) ($body['payment_intent_id'] ?? ''));
$courseId        = isset($body['course_id']) ? (int) $body['course_id'] : 0;
$orderId         = trim((string) ($body['order_id'] ?? ''));

if ($paymentIntentId === '' || !preg_match('/^pi_[A-Za-z0-9_]+$/', $paymentIntentId)) {
    json_fail('Invalid payment_intent_id.');
}
if ($courseId <= 0) {
    json_fail('Invalid course_id.');
}

// ── 4. Retrieve and verify PaymentIntent from Stripe ─────────────────────────
try {
    $intent = stripe_retrieve_payment_intent($paymentIntentId);
} catch (RuntimeException $e) {
    error_log('complete-enrollment: Stripe retrieve failed: ' . $e->getMessage());
    json_fail('Could not verify payment. Please contact support.', 500);
}

if (($intent['status'] ?? '') !== 'succeeded') {
    json_fail('Payment has not succeeded. Status: ' . ($intent['status'] ?? 'unknown'));
}

// ── 5. Security: validate via order or metadata ───────────────────────────────
$metaClientId = (int) ($intent['metadata']['client_id'] ?? 0);
$metaCourseId = (int) ($intent['metadata']['course_id'] ?? 0);
$metaOrderId  = (string) ($intent['metadata']['order_id'] ?? '');

// If an order_id was submitted, validate through the orders table
if ($orderId !== '') {
    ensure_checkout_orders_table($mysqli);
    $order = get_checkout_order($mysqli, $orderId);

    if (!$order) {
        // Already completed or expired — idempotent success if enrolled
        ensure_purchases_table($mysqli);
        if (has_user_enrolled_course($mysqli, $clientId, $courseId)) {
            echo json_encode(['success' => true, 'redirect_url' => BASE . '/course-player.php?course=' . $courseId]);
            exit;
        }
        json_fail('Order not found or has expired.', 404);
    }

    if ((int) $order['client_id'] !== $clientId) {
        json_fail('Payment verification failed.', 403);
    }

    if ((int) $order['course_id'] !== $courseId) {
        json_fail('Payment verification failed.', 403);
    }

    // Also ensure the PI belongs to this order
    if (!empty($order['payment_intent_id']) && $order['payment_intent_id'] !== $paymentIntentId) {
        json_fail('Payment verification failed.', 403);
    }
} else {
    // Fallback: metadata-based check (for legacy checkout-page.php)
    if ($metaClientId !== $clientId) {
        error_log('complete-enrollment: client_id mismatch. PI client=' . $metaClientId . ' session=' . $clientId);
        json_fail('Payment verification failed.', 403);
    }
    if ($metaCourseId !== $courseId) {
        error_log('complete-enrollment: course_id mismatch. PI course=' . $metaCourseId . ' requested=' . $courseId);
        json_fail('Payment verification failed.', 403);
    }
}

// ── 6. Load course ────────────────────────────────────────────────────────────
ensure_purchases_table($mysqli);
$course = find_course_by_id($mysqli, $courseId);
if (!$course) {
    json_fail('Course not found.', 404);
}

// ── 7. Idempotency: already enrolled? ────────────────────────────────────────
if (has_user_enrolled_course($mysqli, $clientId, $courseId)) {
    echo json_encode([
        'success'      => true,
        'redirect_url' => BASE . '/course-player.php?course=' . $courseId,
    ]);
    exit;
}

// ── 8. Extract metadata ───────────────────────────────────────────────────────
$couponCode      = (string) ($intent['metadata']['coupon_code']      ?? '');
$discountPercent = (float)  ($intent['metadata']['discount_percent'] ?? 0);
$originalPrice   = (float) $course['price'];
$amountCharged   = isset($intent['amount_received'])
    ? round((float) $intent['amount_received'] / 100, 2)
    : round($originalPrice * (1 - ($discountPercent / 100)), 2);

// ── 9. Insert purchase record ─────────────────────────────────────────────────
$stmt = $mysqli->prepare(
    'INSERT IGNORE INTO purchases
        (client_id, course_id, original_amount, amount, coupon_code, discount_percent, currency, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);

if (!$stmt) {
    error_log('complete-enrollment: prepare failed: ' . $mysqli->error);
    json_fail('Enrollment failed. Please contact support.', 500);
}

$currency = strtoupper((string) ($intent['currency'] ?? 'usd'));
$status   = 'completed';

$stmt->bind_param(
    'iiddsdss',
    $clientId, $courseId,
    $originalPrice, $amountCharged,
    $couponCode, $discountPercent,
    $currency, $status
);

if (!$stmt->execute()) {
    error_log('complete-enrollment: execute failed: ' . $stmt->error);
    $stmt->close();
    json_fail('Enrollment failed. Please contact support.', 500);
}
$stmt->close();

// ── 10. Consume coupon ────────────────────────────────────────────────────────
if ($couponCode !== '') {
    consume_coupon_code($mysqli, $couponCode);
}

// ── 10b. Mark checkout order as completed ────────────────────────────────────
if ($orderId !== '') {
    complete_checkout_order($mysqli, $orderId);
}

// ── 11. Send confirmation email ───────────────────────────────────────────────
try {
    require_once dirname(__DIR__) . '/includes/mailer.php';
    $playerUrl  = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '')
                . '://' . $_SERVER['HTTP_HOST']
                . BASE . '/course-player.php?course=' . $courseId;
    $emailHtml  = _build_enrollment_email_html_ce(
        (string) $course['title'],
        $playerUrl,
        (string) ($currentUser['full_name'] ?? 'Student')
    );
    $emailText  = 'Congratulations! You are now enrolled in ' . $course['title'] . ".\n\nStart learning: " . $playerUrl;
    send_smtp_mail(
        (string) $currentUser['email'],
        (string) ($currentUser['full_name'] ?? ''),
        'Welcome to ' . $course['title'] . '!',
        $emailHtml,
        $emailText
    );
} catch (Throwable $e) {
    // Email errors are non-fatal
    error_log('complete-enrollment: email error: ' . $e->getMessage());
}

// ── 12. Success ───────────────────────────────────────────────────────────────
echo json_encode([
    'success'      => true,
    'redirect_url' => BASE . '/course-player.php?course=' . $courseId . '&enrolled=1',
]);
exit;

// ── Email helper ──────────────────────────────────────────────────────────────
function _build_enrollment_email_html_ce(string $courseTitle, string $playerUrl, string $userName): string
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
          <h1 style="margin:12px 0 8px;font-size:28px;font-weight:800">Welcome to {$safeTitle}</h1>
          <p style="margin:0;font-size:15px;color:rgba(255,255,255,.9)">Your next skill-building adventure starts today.</p>
        </td></tr>
        <tr><td style="padding:30px">
          <p style="color:#111827;font-size:16px;line-height:1.75;margin:0 0 14px">Hi {$safeName},</p>
          <p style="color:#475569;font-size:15px;line-height:1.85;margin:0 0 18px">Your access to <strong style="color:#312e81">{$safeTitle}</strong> is now live. Dive in whenever you're ready.</p>
          <div style="text-align:center;margin:28px 0">
            <a href="{$safeUrl}" style="display:inline-block;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;text-decoration:none;padding:15px 34px;border-radius:10px;font-size:16px;font-weight:800">Open My Course</a>
          </div>
          <p style="color:#64748b;font-size:13px;line-height:1.8;margin:0;text-align:center">Need help? Contact NerdAcademy support anytime.</p>
        </td></tr>
        <tr><td style="padding:16px 30px;border-top:1px solid #e5e7eb;text-align:center;background:#fcfcff">
          <p style="color:#64748b;font-size:12px;margin:0">NerdAcademy · Practical AI learning for ambitious builders</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}
