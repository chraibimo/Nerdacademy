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
 *   course_id (int), final_price (int, cents), currency (string, e.g. "USD", "EUR", "MAD", etc)
 *   first_name (string), last_name (string), email (string), phone (string), country_code (string)
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

// Some hosts enable mysqli exceptions globally; keep this endpoint in return-code mode
// so we can send consistent JSON errors to n8n instead of blank HTTP 500 responses.
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

set_exception_handler(static function (Throwable $e): void {
    error_log('[create-checkout-link] Unhandled exception: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'ok' => false,
        'error' => 'server_error',
        'detail' => $e->getMessage(),
        'where' => basename($e->getFile()) . ':' . $e->getLine(),
    ]);
    exit;
});

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

// n8n can send form-encoded parameters depending on node body mode.
// Accept both JSON and form payloads to avoid fragile integration failures.
if (!is_array($body) && !empty($_POST)) {
    $body = $_POST;
}

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_json']);
    exit;
}

// ── Validate required fields ──────────────────────────────────────────────────
$courseId = (int) ($body['course_id'] ?? 0);
$finalPrice = (int) ($body['final_price'] ?? 0);
$currency = strtoupper((string) ($body['currency'] ?? ''));

if ($courseId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_course_id']);
    exit;
}

if ($finalPrice <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_final_price']);
    exit;
}

if (empty($currency) || strlen($currency) < 2) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_currency']);
    exit;
}

// Extract customer fields
$firstName = trim((string) ($body['first_name'] ?? ''));
$lastName = trim((string) ($body['last_name'] ?? ''));
$email = trim((string) ($body['email'] ?? ''));
$phone = trim((string) ($body['phone'] ?? ''));
$countryCode = strtoupper(trim((string) ($body['country_code'] ?? '')));

if (empty($firstName)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'first_name_required']);
    exit;
}

if (empty($lastName)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'last_name_required']);
    exit;
}

if (empty($email)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'email_required']);
    exit;
}

if (empty($phone)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'phone_required']);
    exit;
}

if (empty($countryCode)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'country_code_required']);
    exit;
}

// ── Generate order token ──────────────────────────────────────────────────────
// Format: ORD_{timestamp}_{8alphanumeric}
$timestamp = (int)(microtime(true) * 1000); // milliseconds since epoch
$randomStr = bin2hex(random_bytes(4)); // 8 hex chars = 4 bytes
$orderToken = 'ORD_' . $timestamp . '_' . $randomStr;

// ── Product name ──────────────────────────────────────────────────────────────
$productName = 'Course #' . $courseId;
if (!empty($body['product_name'])) {
    $productName = substr((string)$body['product_name'], 0, 255);
}

// Ensure field lengths match DB schema
$firstName  = substr($firstName, 0, 100);
$lastName   = substr($lastName, 0, 100);
$email      = substr($email, 0, 255);
$phone      = substr($phone, 0, 20);
$countryCode = substr($countryCode, 0, 2);

// plan_id can encode course info
$planId = 'course_' . $courseId;

// ── Insert order ──────────────────────────────────────────────────────────────
$expiresAt = date('Y-m-d H:i:s', time() + PAYMENT_ORDER_TTL);

// Pass empty strings for Stripe fields (not applicable for n8n-generated links)
$stripeIntentId = '';
$stripeSecret = '';
$postalCode = ''; // Not provided in n8n request, keep empty

$stmt = $mysqli->prepare(
    'INSERT INTO payment_orders
        (order_token, stripe_payment_intent_id, stripe_client_secret,
         customer_first_name, customer_last_name, customer_email,
         customer_phone, customer_country_code, postal_code, plan_id, amount, currency, product_name, expires_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
}

$stmt->bind_param(
    'ssssssssssiss',
    $orderToken,
    $stripeIntentId,
    $stripeSecret,
    $firstName,
    $lastName,
    $email,
    $phone,
    $countryCode,
    $postalCode,
    $planId,
    $finalPrice,
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
