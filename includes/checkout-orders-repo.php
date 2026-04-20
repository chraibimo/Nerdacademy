<?php

require_once __DIR__ . '/db.php';

// ── Table bootstrap ────────────────────────────────────────────────────────────
function ensure_checkout_orders_table(mysqli $mysqli): void
{
    $mysqli->query("CREATE TABLE IF NOT EXISTS checkout_orders (
        id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id              CHAR(36)        NOT NULL,
        client_id             BIGINT UNSIGNED NOT NULL,
        course_id             INT             NOT NULL,
        coupon_code           VARCHAR(50)     NULL,
        discount_percent      DECIMAL(5,2)    NOT NULL DEFAULT 0,
        original_amount       DECIMAL(10,2)   NOT NULL DEFAULT 0,
        final_amount          DECIMAL(10,2)   NOT NULL DEFAULT 0,
        currency              VARCHAR(10)     NOT NULL DEFAULT 'USD',
        payment_intent_id     VARCHAR(100)    NULL,
        payment_intent_secret TEXT            NULL,
        status                ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
        created_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at            DATETIME        NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_order_id (order_id),
        KEY idx_co_client (client_id),
        KEY idx_co_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// ── UUID v4 generator ──────────────────────────────────────────────────────────
function generate_order_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // version 4
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variant bits

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

// ── Create a new pending checkout order ───────────────────────────────────────
function create_checkout_order(
    mysqli $mysqli,
    int    $clientId,
    int    $courseId,
    float  $originalAmount,
    float  $finalAmount,
    string $couponCode      = '',
    float  $discountPercent = 0.0,
    string $currency        = 'USD'
): string {
    ensure_checkout_orders_table($mysqli);

    $orderId   = generate_order_uuid();
    $expiresAt = date('Y-m-d H:i:s', time() + 7200); // 2-hour window

    $stmt = $mysqli->prepare(
        'INSERT INTO checkout_orders
            (order_id, client_id, course_id, coupon_code, discount_percent,
             original_amount, final_amount, currency, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$stmt) {
        throw new RuntimeException('checkout_orders insert prepare failed: ' . $mysqli->error);
    }

    $couponOrNull = $couponCode !== '' ? $couponCode : null;

    $stmt->bind_param(
        'siisddsss',
        $orderId, $clientId, $courseId, $couponOrNull, $discountPercent,
        $originalAmount, $finalAmount, $currency, $expiresAt
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('checkout_orders insert failed: ' . $mysqli->error);
    }
    $stmt->close();

    return $orderId;
}

// ── Load a valid pending order ─────────────────────────────────────────────────
function get_checkout_order(mysqli $mysqli, string $orderId): ?array
{
    ensure_checkout_orders_table($mysqli);

    $stmt = $mysqli->prepare(
        'SELECT * FROM checkout_orders
         WHERE order_id = ? AND status = ? AND expires_at > NOW()
         LIMIT 1'
    );

    if (!$stmt) return null;

    $status = 'pending';
    $stmt->bind_param('ss', $orderId, $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

// ── Attach PaymentIntent to order (called on first load of checkout/cn.php) ───
function attach_payment_intent_to_order(
    mysqli $mysqli,
    string $orderId,
    string $paymentIntentId,
    string $paymentIntentSecret
): void {
    $stmt = $mysqli->prepare(
        'UPDATE checkout_orders
         SET payment_intent_id = ?, payment_intent_secret = ?
         WHERE order_id = ? AND payment_intent_id IS NULL'
    );

    if (!$stmt) return;

    $stmt->bind_param('sss', $paymentIntentId, $paymentIntentSecret, $orderId);
    $stmt->execute();
    $stmt->close();
}

// ── Mark order as completed ────────────────────────────────────────────────────
function complete_checkout_order(mysqli $mysqli, string $orderId): void
{
    $stmt = $mysqli->prepare(
        "UPDATE checkout_orders SET status = 'completed' WHERE order_id = ?"
    );

    if (!$stmt) return;

    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $stmt->close();
}
