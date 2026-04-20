<?php

require_once __DIR__ . '/stripe-config.php';

/**
 * Make a raw curl request to the Stripe API.
 *
 * @param  string  $method    HTTP method: GET or POST
 * @param  string  $endpoint  Stripe endpoint, e.g. 'checkout/sessions'
 * @param  array   $data      Request body parameters (for POST)
 * @return array              Decoded JSON response
 * @throws RuntimeException   On curl failure or non-2xx HTTP status
 */
function stripe_request(string $method, string $endpoint, array $data = []): array
{
    $url = 'https://api.stripe.com/v1/' . ltrim($endpoint, '/');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ':');
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    } elseif (!empty($data)) {
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($data));
    }

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        throw new RuntimeException('Stripe curl error: ' . $curlError);
    }

    $decoded = json_decode((string) $response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Stripe returned invalid JSON. HTTP ' . $httpCode . ': ' . $response);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = (string) ($decoded['error']['message'] ?? 'Unknown Stripe error');
        throw new RuntimeException('Stripe API error (HTTP ' . $httpCode . '): ' . $message);
    }

    return $decoded;
}

/**
 * Create a Stripe Checkout Session.
 *
 * @param  array $params  Checkout session parameters
 * @return array          Decoded session object
 */
function stripe_create_checkout_session(array $params): array
{
    return stripe_request('POST', 'checkout/sessions', $params);
}

/**
 * Retrieve a Stripe Checkout Session by ID.
 *
 * @param  string $sessionId  The cs_xxx session ID
 * @return array              Decoded session object
 */
function stripe_retrieve_checkout_session(string $sessionId): array
{
    return stripe_request('GET', 'checkout/sessions/' . rawurlencode($sessionId));
}

/**
 * Create a Stripe PaymentIntent.
 *
 * @param  array $params  PaymentIntent parameters (amount, currency, metadata, etc.)
 * @return array          Decoded PaymentIntent object
 */
function stripe_create_payment_intent(array $params): array
{
    return stripe_request('POST', 'payment_intents', $params);
}

/**
 * Retrieve a Stripe PaymentIntent by ID.
 *
 * @param  string $paymentIntentId  The pi_xxx ID
 * @return array                    Decoded PaymentIntent object
 */
function stripe_retrieve_payment_intent(string $paymentIntentId): array
{
    return stripe_request('GET', 'payment_intents/' . rawurlencode($paymentIntentId));
}

/**
 * Validate and decode a Stripe webhook event from raw payload + signature header.
 *
 * Manually implements Stripe webhook signature verification (no SDK required).
 *
 * @param  string $payload    Raw request body string
 * @param  string $sigHeader  Value of the Stripe-Signature header
 * @param  string $secret     Webhook signing secret (whsec_...)
 * @return array              Decoded event array
 * @throws RuntimeException   If signature is invalid or timestamp is stale
 */
function stripe_construct_webhook_event(string $payload, string $sigHeader, string $secret): array
{
    // Parse the Stripe-Signature header: t=timestamp,v1=signature[,v1=signature2,...]
    $parts = explode(',', $sigHeader);
    $timestamp = null;
    $signatures = [];

    foreach ($parts as $part) {
        $part = trim($part);
        if (str_starts_with($part, 't=')) {
            $timestamp = substr($part, 2);
        } elseif (str_starts_with($part, 'v1=')) {
            $signatures[] = substr($part, 3);
        }
    }

    if ($timestamp === null || $timestamp === '' || empty($signatures)) {
        throw new RuntimeException('Invalid Stripe-Signature header: missing timestamp or signatures.');
    }

    // Check timestamp freshness (300-second tolerance)
    $ts = (int) $timestamp;
    if (abs(time() - $ts) > 300) {
        throw new RuntimeException('Stripe webhook timestamp is too old or too far in the future.');
    }

    // Reconstruct the signed payload
    $signedPayload = $timestamp . '.' . $payload;

    // Compute expected HMAC-SHA256
    $expected = hash_hmac('sha256', $signedPayload, $secret);

    // Timing-safe comparison against all v1 signatures
    $valid = false;
    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) {
            $valid = true;
            break;
        }
    }

    if (!$valid) {
        throw new RuntimeException('Stripe webhook signature verification failed.');
    }

    // Decode and return the event
    $event = json_decode($payload, true);
    if (!is_array($event)) {
        throw new RuntimeException('Stripe webhook payload is not valid JSON.');
    }

    return $event;
}
