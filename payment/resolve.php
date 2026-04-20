<?php

/**
 * POST /payment/resolve
 *
 * Internal resolver — validates an order token and returns the data
 * needed by the payment page to initialise Stripe Elements.
 *
 * CORS: only accepts requests from nerdacademy.ai (same origin).
 * Rate limit: 10 requests per IP per 60 seconds.
 *
 * Request body (JSON): { "order": "ORD_..." }
 *
 * Success response:
 *   { "client_secret": "pi_xxx_secret_xxx", "amount": 7000,
 *     "currency": "cad", "product_name": "6 Month Plan" }
 *
 * Error response:
 *   { "error": "invalid_order" }   (HTTP 400)
 *   { "error": "rate_limited" }    (HTTP 429)
 *   { "error": "forbidden" }       (HTTP 403)
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
// Do NOT add Access-Control-Allow-Origin — this endpoint must not be
// accessible cross-origin. Absence of the header blocks cross-origin
// JS fetch on all modern browsers.

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

// ── Origin check — block cross-origin requests ────────────────────────────────
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');

if ($origin !== '') {
    // Origin header present → verify it is our own domain
    $allowed = PAYMENT_ALLOWED_ORIGINS;
    if (!in_array($origin, $allowed, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }
}
// If Origin is absent the request is either server-side or same-origin — allow.

// ── Rate limit ────────────────────────────────────────────────────────────────
$clientIp = payment_client_ip();
if (!payment_check_rate_limit($mysqli, $clientIp)) {
    http_response_code(429);
    header('Retry-After: 60');
    echo json_encode(['error' => 'rate_limited']);
    exit;
}

// ── Parse body ────────────────────────────────────────────────────────────────
$raw  = (string) file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body) || !isset($body['order'])) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_order']);
    exit;
}

// ── Resolve ───────────────────────────────────────────────────────────────────
$orderToken = trim((string) $body['order']);
$orderData  = payment_resolve_order($mysqli, $orderToken);

if ($orderData === null) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_order']);
    exit;
}

echo json_encode([
    'client_secret' => $orderData['client_secret'],
    'amount'        => $orderData['amount'],
    'currency'      => $orderData['currency'],
    'product_name'  => $orderData['product_name'],
]);
