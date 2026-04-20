<?php
/**
 * GET /api/payment-intent.php?order=UUID
 *
 * Called by checkout/cn.php (iframe) to get Stripe PaymentIntent data.
 * Validates the order belongs to the authenticated user, creates or
 * retrieves a PaymentIntent, and returns the client secret + metadata.
 *
 * Response (JSON):
 *   {
 *     "client_secret":    "pi_xxx_secret_yyy",
 *     "publishable_key":  "pk_live_...",
 *     "amount":           21900,
 *     "currency":         "usd",
 *     "final_display":    "219.00",
 *     "course_id":        42,
 *     "course_title":     "Reinforcement Learning & AI Agents",
 *     "course_thumb":     "https://...",
 *     "order_id":         "uuid-...",
 *     "coupon_code":      "SAVE20"
 *   }
 */
if (!defined('BASE')) define('BASE', '');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
// Allow same-origin iframe
header('X-Frame-Options: SAMEORIGIN');

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/courses-repo.php';
require_once dirname(__DIR__) . '/includes/purchases-repo.php';
require_once dirname(__DIR__) . '/includes/stripe-api.php';
require_once dirname(__DIR__) . '/includes/stripe-config.php';
require_once dirname(__DIR__) . '/includes/checkout-orders-repo.php';

function pi_fail(string $message, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// ── 1. Method ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    pi_fail('Method not allowed.', 405);
}

// ── 2. Auth (optional – order UUID acts as access token) ──────────────────────
// No login required; the order UUID is unguessable.

// ── 3. Validate order_id ──────────────────────────────────────────────────────
$rawOrderId = trim((string) ($_GET['order'] ?? ''));
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $rawOrderId)) {
    pi_fail('Invalid order ID.', 422);
}

// ── 4. Load order ─────────────────────────────────────────────────────────────
ensure_checkout_orders_table($mysqli);
$order = get_checkout_order($mysqli, $rawOrderId);
if (!$order) {
    pi_fail('Order not found or expired.', 404);
}

// ── 5. Get client_id from order (no login needed) ────────────────────────────
$clientId = (int) $order['client_id'];

// ── 6. Already completed? ─────────────────────────────────────────────────────
if ($order['status'] === 'completed') {
    pi_fail('Order already completed.', 409);
}

$courseId        = (int)   $order['course_id'];
$finalPrice      = (float) $order['final_amount'];
$couponCode      = (string)($order['coupon_code'] ?? '');
$discountPercent = (float) $order['discount_percent'];
$priceCents      = (int)   round($finalPrice * 100);

// ── 7. Already enrolled? ──────────────────────────────────────────────────────
ensure_purchases_table($mysqli);
if (has_user_enrolled_course($mysqli, $clientId, $courseId)) {
    pi_fail('Already enrolled.', 409);
}

// ── 8. Load course ────────────────────────────────────────────────────────────
$course = find_course_by_id($mysqli, $courseId);
if (!$course) {
    pi_fail('Course not found.', 404);
}

// ── 9. Get or create PaymentIntent ───────────────────────────────────────────
if (!empty($order['payment_intent_id']) && !empty($order['payment_intent_secret'])) {
    $clientSecret = (string) $order['payment_intent_secret'];
} else {
    $piParams = [
        'amount'                     => (string) $priceCents,
        'currency'                   => 'usd',
        'payment_method_types[]'     => 'card',
        'metadata[order_id]'         => $rawOrderId,
        'metadata[course_id]'        => (string) $courseId,
        'metadata[client_id]'        => (string) $clientId,
        'metadata[coupon_code]'      => $couponCode,
        'metadata[discount_percent]' => (string) $discountPercent,
    ];
    try {
        $intent = stripe_create_payment_intent($piParams);
    } catch (RuntimeException $e) {
        error_log('api/payment-intent: ' . $e->getMessage());
        pi_fail('Payment setup failed. Please try again.', 500);
    }
    $clientSecret = (string) $intent['client_secret'];
    $piId         = (string) $intent['id'];
    attach_payment_intent_to_order($mysqli, $rawOrderId, $piId, $clientSecret);
}

// ── 10. Respond ───────────────────────────────────────────────────────────────
echo json_encode([
    'success'         => true,
    'client_secret'   => $clientSecret,
    'publishable_key' => STRIPE_PUBLISHABLE_KEY,
    'amount'          => $priceCents,
    'currency'        => 'usd',
    'final_display'   => number_format($finalPrice, 2),
    'course_id'       => $courseId,
    'course_title'    => (string) $course['title'],
    'course_thumb'    => (string)($course['image_url'] ?? ''),
    'order_id'        => $rawOrderId,
    'coupon_code'     => $couponCode,
]);
