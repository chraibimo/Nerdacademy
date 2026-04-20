<?php
/**
 * POST /api/create-payment-intent
 * Called by the integrated checkout JS to create a PaymentIntent.
 * Returns { clientSecret, paymentIntentId, amount, currency }
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$currentUser = auth_current_user();
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthenticated']);
    exit;
}

$raw  = (string) file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json']);
    exit;
}

$courseId        = isset($body['course_id']) ? (int) $body['course_id'] : 0;
$couponCodeInput = trim((string) ($body['coupon_code'] ?? ''));

if ($courseId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_course']);
    exit;
}

$clientId = (int) $currentUser['id'];

ensure_purchases_table($mysqli);
$course = find_course_by_id($mysqli, $courseId);
if (!$course) {
    http_response_code(404);
    echo json_encode(['error' => 'course_not_found']);
    exit;
}

if (has_user_enrolled_course($mysqli, $clientId, $courseId)) {
    http_response_code(409);
    echo json_encode(['error' => 'already_enrolled']);
    exit;
}

$couponCode      = '';
$discountPercent = 0.0;

if ($couponCodeInput !== '') {
    $couponCheck = validate_coupon_code($mysqli, $couponCodeInput);
    if (!($couponCheck['ok'] ?? false)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_coupon']);
        exit;
    }
    $couponCode      = normalize_coupon_code($couponCodeInput);
    $discountPercent = (float) ($couponCheck['discount_percent'] ?? 0);
}

$originalPrice = (float) $course['price'];
$finalPrice    = round($originalPrice * (1 - ($discountPercent / 100)), 2);
$priceCents    = (int) round($finalPrice * 100);

if ($priceCents <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'use_free_enrollment']);
    exit;
}

$params = [
    'amount'                     => (string) $priceCents,
    'currency'                   => 'usd',
    'payment_method_types[]'     => 'card',   // card covers Google Pay & Apple Pay via Payment Request API
    'metadata[course_id]'        => (string) $courseId,
    'metadata[client_id]'        => (string) $clientId,
    'metadata[coupon_code]'      => $couponCode,
    'metadata[discount_percent]' => (string) $discountPercent,
];

try {
    $intent = stripe_create_payment_intent($params);
} catch (RuntimeException $e) {
    error_log('create-payment-intent error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'stripe_error']);
    exit;
}

echo json_encode([
    'clientSecret'    => $intent['client_secret'],
    'paymentIntentId' => $intent['id'],
    'amount'          => $priceCents,
    'currency'        => 'usd',
]);
