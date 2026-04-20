<?php

if (!defined('BASE')) define('BASE', '');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/courses-repo.php';
require_once __DIR__ . '/../includes/purchases-repo.php';
require_once __DIR__ . '/../includes/stripe-api.php';
require_once __DIR__ . '/../includes/mailer.php';

// ── Respond only to POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ── 1. Read raw POST body ─────────────────────────────────────────────────────
$payload = (string) file_get_contents('php://input');

// ── 2. Get Stripe-Signature header ────────────────────────────────────────────
$sigHeader = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
if ($sigHeader === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing Stripe-Signature header']);
    exit;
}

// ── 3. Validate and decode webhook event ─────────────────────────────────────
try {
    $event = stripe_construct_webhook_event($payload, $sigHeader, STRIPE_WEBHOOK_SECRET);
} catch (RuntimeException $e) {
    error_log('Stripe webhook validation failed: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Webhook signature verification failed']);
    exit;
}

$eventType = (string) ($event['type'] ?? '');

// ── 4. Handle checkout.session.completed ─────────────────────────────────────
if ($eventType === 'checkout.session.completed') {
    $session = $event['data']['object'] ?? [];

    // Only process paid sessions
    if (($session['payment_status'] ?? '') !== 'paid') {
        http_response_code(200);
        echo json_encode(['received' => true]);
        exit;
    }

    $clientId        = (int)    ($session['metadata']['client_id']       ?? 0);
    $courseId        = (int)    ($session['metadata']['course_id']        ?? 0);
    $couponCode      = (string) ($session['metadata']['coupon_code']      ?? '');
    $discountPercent = (float)  ($session['metadata']['discount_percent'] ?? 0);

    if ($clientId <= 0 || $courseId <= 0) {
        error_log('Stripe webhook: missing client_id or course_id in metadata');
        http_response_code(200);
        echo json_encode(['received' => true]);
        exit;
    }

    ensure_purchases_table($mysqli);

    // Idempotency: skip if already enrolled
    if (!has_user_enrolled_course($mysqli, $clientId, $courseId)) {
        $course         = find_course_by_id($mysqli, $courseId);
        $originalAmount = $course ? (float) $course['price'] : 0.0;
        $amountCharged  = isset($session['amount_total']) ? round((float) $session['amount_total'] / 100, 2) : 0.0;

        $stmt = $mysqli->prepare(
            'INSERT IGNORE INTO purchases (client_id, course_id, original_amount, amount, coupon_code, discount_percent, currency, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if ($stmt) {
            $currency = 'USD';
            $status   = 'completed';
            $stmt->bind_param(
                'iiddsdss',
                $clientId, $courseId,
                $originalAmount, $amountCharged,
                $couponCode, $discountPercent,
                $currency, $status
            );
            $stmt->execute();
            $stmt->close();
        } else {
            error_log('Stripe webhook: prepare failed for purchase insert, course=' . $courseId);
        }

        // Consume coupon (only on successful first insert)
        if ($couponCode !== '') {
            require_once __DIR__ . '/../includes/coupons-repo.php';
            consume_coupon_code($mysqli, $couponCode);
        }

        // Send confirmation email if course data available
        if ($course) {
            try {
                // Fetch user email/name from DB
                $userStmt = $mysqli->prepare('SELECT full_name, email FROM clients WHERE id = ? LIMIT 1');
                if ($userStmt) {
                    $userStmt->bind_param('i', $clientId);
                    $userStmt->execute();
                    $userResult = $userStmt->get_result();
                    $userRow    = $userResult ? $userResult->fetch_assoc() : null;
                    $userStmt->close();

                    if ($userRow) {
                        $userName    = (string) ($userRow['full_name'] ?? '');
                        $userEmail   = (string) ($userRow['email']     ?? '');
                        $courseTitle = (string) $course['title'];
                        $playerUrl   = 'http://localhost' . BASE . '/course-player.php?course=' . $courseId;
                        $supportUrl  = 'http://localhost' . BASE . '/support.php';
                        $safeName    = htmlspecialchars($userName);
                        $safeTitle   = htmlspecialchars($courseTitle);
                        $safeUrl     = htmlspecialchars($playerUrl);
                        $safeSup     = htmlspecialchars($supportUrl);

                        $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#eef2ff;font-family:Inter,'Segoe UI',Roboto,Arial,sans-serif;color:#111827">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(160deg,#eef2ff 0%,#f8fafc 48%,#e0f2fe 100%);padding:32px 12px">
    <tr><td align="center">
      <table width="680" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid #e5e7eb;border-radius:20px;overflow:hidden;max-width:680px;width:100%;box-shadow:0 18px 45px rgba(79,70,229,.14)">
        <tr><td style="background:linear-gradient(135deg,#4338ca 0%,#6366f1 50%,#8b5cf6 100%);padding:30px 32px 24px;text-align:center;color:#ffffff">
          <div style="font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;opacity:.92">Enrollment Confirmed</div>
          <h1 style="margin:12px 0 8px;font-size:30px;line-height:1.2;font-weight:800">You're enrolled in {$safeTitle}</h1>
          <p style="margin:0;font-size:15px;line-height:1.75;color:rgba(255,255,255,.92)">Your course is now ready whenever inspiration strikes.</p>
        </td></tr>
        <tr><td style="padding:30px">
          <p style="color:#111827;font-size:16px;line-height:1.75;margin:0 0 14px">Hi {$safeName},</p>
          <p style="color:#475569;font-size:15px;line-height:1.85;margin:0 0 18px">Thank you for your purchase. You now have full access to <strong style="color:#312e81">{$safeTitle}</strong> and can continue building your AI skills right away.</p>
          <div style="text-align:center;margin:28px 0">
            <a href="{$safeUrl}" style="display:inline-block;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;text-decoration:none;padding:15px 34px;border-radius:10px;font-size:16px;font-weight:800">
              Open My Course
            </a>
          </div>
          <p style="color:#64748b;font-size:13px;line-height:1.8;margin:0;text-align:center">
            Questions? Visit our <a href="{$safeSup}" style="color:#4f46e5;text-decoration:none">support page</a> anytime.
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
                            $userEmail,
                            $userName,
                            "You're enrolled in {$courseTitle}! 🎓",
                            $htmlBody,
                            $textBody
                        );
                    }
                }
            } catch (Throwable $e) {
                error_log('Stripe webhook: email send failed for client=' . $clientId . ': ' . $e->getMessage());
            }
        }
    }
}

// ── 5. Handle payment_intent.payment_failed ───────────────────────────────────
elseif ($eventType === 'payment_intent.payment_failed') {
    $pi        = $event['data']['object'] ?? [];
    $piId      = (string) ($pi['id'] ?? 'unknown');
    $lastError = (string) ($pi['last_payment_error']['message'] ?? 'unknown error');
    error_log('Stripe payment failed: payment_intent=' . $piId . ' error=' . $lastError);
}

// ── 6. Return 200 ─────────────────────────────────────────────────────────────
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['received' => true]);
exit;
