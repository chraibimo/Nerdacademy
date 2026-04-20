<?php

/**
 * POST /payment/store-order
 *
 * Called by n8n after it creates a Stripe PaymentIntent.
 * Registers the order token → PaymentIntent mapping in the DB.
 *
 * Auth: Authorization: Bearer <PAYMENT_N8N_SECRET>
 *
 * Body (JSON):
 *   order_token, stripe_payment_intent_id, stripe_client_secret,
 *   amount (int, cents), currency, product_name,
 *   [optional] customer_first_name, customer_last_name, customer_email, postal_code, plan_id
 */

header('Content-Type: application/json; charset=utf-8');
// Not browser-facing — no CORS headers needed
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/_config.php';
require_once __DIR__ . '/_helpers.php';
require_once dirname(__DIR__) . '/includes/db.php'; // provides $mysqli

payment_ensure_tables($mysqli);

// ── Method ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

// ── Auth: Bearer token ────────────────────────────────────────────────────────
$authHeader = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$token      = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';

if ($token === '' || !hash_equals(PAYMENT_N8N_SECRET, $token)) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

// ── Parse JSON body ───────────────────────────────────────────────────────────
$raw  = (string) file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json']);
    exit;
}

// ── Validate required fields ──────────────────────────────────────────────────
$required = ['order_token', 'stripe_payment_intent_id', 'stripe_client_secret', 'amount', 'currency', 'product_name'];
foreach ($required as $field) {
    if (empty($body[$field])) {
        http_response_code(400);
        echo json_encode(['error' => 'missing_field', 'field' => $field]);
        exit;
    }
}

$orderToken   = (string) $body['order_token'];
$intentId     = (string) $body['stripe_payment_intent_id'];
$clientSecret = (string) $body['stripe_client_secret'];
$amount       = (int)    $body['amount'];
$currency     = strtolower((string) $body['currency']);
$productName  = (string) $body['product_name'];

// Validate order token format: ORD_{timestamp}_{8alphanumeric}
if (!preg_match('/^ORD_\d+_[A-Za-z0-9]{8}$/', $orderToken)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_order_token_format']);
    exit;
}

// Validate PaymentIntent ID format
if (!preg_match('/^pi_[A-Za-z0-9_]+$/', $intentId)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_payment_intent_id']);
    exit;
}

// Validate client secret format: pi_..._secret_...
if (!preg_match('/^pi_[A-Za-z0-9_]+_secret_[A-Za-z0-9_]+$/', $clientSecret)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_client_secret_format']);
    exit;
}

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_amount']);
    exit;
}

// ── Insert order ──────────────────────────────────────────────────────────────
$firstName   = substr((string) ($body['customer_first_name'] ?? ''), 0, 100);
$lastName    = substr((string) ($body['customer_last_name']  ?? ''), 0, 100);
$email       = substr((string) ($body['customer_email']      ?? ''), 0, 255);
$postalCode  = substr((string) ($body['postal_code']         ?? ''), 0, 20);
$planId      = substr((string) ($body['plan_id']             ?? ''), 0, 100);
$expiresAt   = date('Y-m-d H:i:s', time() + PAYMENT_ORDER_TTL);

$stmt = $mysqli->prepare(
    'INSERT INTO payment_orders
        (order_token, stripe_payment_intent_id, stripe_client_secret,
         customer_first_name, customer_last_name, customer_email,
         postal_code, plan_id, amount, currency, product_name, expires_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'db_prepare_failed']);
    exit;
}

$stmt->bind_param(
    'ssssssssiss',
    $orderToken,
    $intentId,
    $clientSecret,
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
    // Duplicate token
    if ($mysqli->errno === 1062) {
        http_response_code(409);
        echo json_encode(['error' => 'duplicate_order_token']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'db_insert_failed']);
    }
    $stmt->close();
    exit;
}

$stmt->close();

http_response_code(201);
echo json_encode(['ok' => true, 'order' => $orderToken]);
