<?php

/**
 * POST /api/create-checkout-link
 *
 * Generates a secure checkout link for a course/product.
 * Can be called from n8n or any external system with API key.
 *
 * Auth: Authorization: Bearer <CHECKOUT_API_KEY>
 *
 * Body (JSON):
 *   course_id (int), amount (int, cents), currency (string, e.g. "USD", "EUR")
 *   [optional] customer_email, customer_first_name, customer_last_name, postal_code
 *
 * Response (JSON):
 *   {
 *     "ok": true,
 *     "checkout_link": "https://nerdacademy.ai/checkout/?order=ORD_1776810249741",
 *     "order_token": "ORD_1776810249741"
 *   }
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/payment/_config.php';
require_once dirname(__DIR__) . '/payment/_helpers.php';

payment_ensure_tables($mysqli);

// ── Method ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

// ── Auth: Bearer token ────────────────────────────────────────────────────────
$authHeader = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$token      = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';

$apiKey = getenv('CHECKOUT_API_KEY') ?: $_SERVER['CHECKOUT_API_KEY'] ?? $_ENV['CHECKOUT_API_KEY'] ?? '';

if ($token === '' || !hash_equals($apiKey, $token) || $apiKey === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

// ── Parse JSON body ───────────────────────────────────────────────────────────
$raw  = (string) file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_json']);
    exit;
}

// ── Validate required fields ──────────────────────────────────────────────────
$courseId = (int) ($body['course_id'] ?? 0);
$amount   = (int) ($body['amount']    ?? 0);
$currency = strtoupper((string) ($body['currency'] ?? 'USD'));

if ($courseId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_course_id']);
    exit;
}

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_amount']);
    exit;
}

if (!preg_match('/^[A-Z]{3}$/', $currency)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_currency']);
    exit;
}

// ── Generate order token ──────────────────────────────────────────────────────
// Format: ORD_{timestamp}_{8alphanumeric}
$timestamp = (int)(microtime(true) * 1000); // milliseconds since epoch
$randomStr = bin2hex(random_bytes(4)); // 8 hex chars = 4 bytes
$orderToken = 'ORD_' . $timestamp . '_' . $randomStr;

// ── Optional fields ───────────────────────────────────────────────────────────
$email     = substr((string) ($body['customer_email']      ?? ''), 0, 255);
$firstName = substr((string) ($body['customer_first_name'] ?? ''), 0, 100);
$lastName  = substr((string) ($body['customer_last_name']  ?? ''), 0, 100);
$postalCode = substr((string) ($body['postal_code']        ?? ''), 0, 20);

// Product name is derived from course ID (can be enhanced with course lookup)
$productName = 'Course #' . $courseId;
if (!empty($body['product_name'])) {
    $productName = substr((string)$body['product_name'], 0, 255);
}

// ── Insert order ──────────────────────────────────────────────────────────────
$expiresAt = date('Y-m-d H:i:s', time() + PAYMENT_ORDER_TTL);

$stmt = $mysqli->prepare(
    'INSERT INTO payment_orders
        (order_token, stripe_payment_intent_id, stripe_client_secret,
         customer_first_name, customer_last_name, customer_email,
         postal_code, plan_id, amount, currency, product_name, expires_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
}

// Pass empty strings for Stripe fields (not applicable for n8n-generated links)
$stripeIntentId = '';
$stripeSecret = '';
$planId = 'course_' . $courseId;

$stmt->bind_param(
    'ssssssssiss',
    $orderToken,
    $stripeIntentId,
    $stripeSecret,
    $firstName,
    $lastName,
    $email,
    $postalCode,
    $planId,
    $amount,
    $currency,
    $productName,
    $expiresAt
);

if (!$stmt->execute()) {
    // Duplicate token (extremely unlikely)
    if ($mysqli->errno === 1062) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'duplicate_order_token']);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'db_insert_failed']);
    }
    $stmt->close();
    exit;
}

$stmt->close();

// ── Build checkout link ───────────────────────────────────────────────────────
// Determine the base URL (production vs local)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'nerdacademy.ai';
$baseUrl = $protocol . '://' . $host;

// For production, force https and the canonical domain
if (strpos($host, 'localhost') === false && strpos($host, '127.0.0.1') === false) {
    $baseUrl = 'https://nerdacademy.ai';
}

$checkoutLink = $baseUrl . '/checkout/?order=' . $orderToken;

// ── Return success ────────────────────────────────────────────────────────────
http_response_code(200);
echo json_encode([
    'ok' => true,
    'checkout_link' => $checkoutLink,
    'order_token' => $orderToken,
    'expires_at' => $expiresAt
]);
