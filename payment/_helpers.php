<?php

/**
 * Creates payment_orders and resolve_rate_limit tables if they do not exist.
 */
function payment_ensure_tables(mysqli $db): void
{
    $db->query("
        CREATE TABLE IF NOT EXISTS `payment_orders` (
            `id`                        INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            `order_token`               VARCHAR(64)     NOT NULL,
            `stripe_payment_intent_id`  VARCHAR(128)    NOT NULL,
            `stripe_client_secret`      TEXT            NOT NULL,
            `customer_first_name`       VARCHAR(100)    NOT NULL DEFAULT '',
            `customer_last_name`        VARCHAR(100)    NOT NULL DEFAULT '',
            `customer_email`            VARCHAR(255)    NOT NULL DEFAULT '',
            `customer_phone`            VARCHAR(20)     NOT NULL DEFAULT '',
            `customer_country_code`     VARCHAR(2)      NOT NULL DEFAULT '',
            `postal_code`               VARCHAR(20)     NOT NULL DEFAULT '',
            `plan_id`                   VARCHAR(100)    NOT NULL DEFAULT '',
            `amount`                    INT UNSIGNED    NOT NULL DEFAULT 0,
            `currency`                  VARCHAR(10)     NOT NULL DEFAULT 'cad',
            `product_name`              VARCHAR(255)    NOT NULL DEFAULT '',
            `created_at`                DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `expires_at`                DATETIME        NOT NULL,
            `used_at`                   DATETIME                 DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_order_token` (`order_token`),
            KEY `idx_expires_at` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Add phone column if it doesn't exist
    $result = $db->query("SHOW COLUMNS FROM payment_orders LIKE 'customer_phone'");
    if ($result && $result->num_rows === 0) {
        $db->query("ALTER TABLE payment_orders ADD COLUMN customer_phone VARCHAR(20) NOT NULL DEFAULT '' AFTER customer_email");
    }

    // Add country_code column if it doesn't exist
    $result = $db->query("SHOW COLUMNS FROM payment_orders LIKE 'customer_country_code'");
    if ($result && $result->num_rows === 0) {
        $db->query("ALTER TABLE payment_orders ADD COLUMN customer_country_code VARCHAR(2) NOT NULL DEFAULT '' AFTER customer_phone");
    }

    $db->query("
        CREATE TABLE IF NOT EXISTS `resolve_rate_limit` (
            `ip_hash`       VARCHAR(64)     NOT NULL,
            `request_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `window_start`  DATETIME        NOT NULL,
            PRIMARY KEY (`ip_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Looks up a valid, unexpired, unused order by token.
 *
 * @return array|null  Associative array with keys: client_secret, amount, currency,
 *                     product_name, stripe_payment_intent_id — or null on failure.
 */
function payment_resolve_order(mysqli $db, string $orderToken): ?array
{
    if ($orderToken === '' || !preg_match('/^ORD_\d+_[A-Za-z0-9]{8}$/', $orderToken)) {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT stripe_client_secret, stripe_payment_intent_id, amount, currency, product_name
           FROM payment_orders
          WHERE order_token = ?
            AND expires_at  > NOW()
            AND used_at     IS NULL
          LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $orderToken);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'client_secret'             => (string) $row['stripe_client_secret'],
        'stripe_payment_intent_id'  => (string) $row['stripe_payment_intent_id'],
        'amount'                    => (int)    $row['amount'],
        'currency'                  => (string) $row['currency'],
        'product_name'              => (string) $row['product_name'],
    ];
}

/**
 * Checks and increments rate-limit for the given IP.
 * Returns true if the request is allowed, false if rate-limited.
 * Window: 60 seconds, max 10 requests per window.
 */
function payment_check_rate_limit(mysqli $db, string $ip): bool
{
    $WINDOW = 60;
    $LIMIT  = 10;

    // Hash IP — no secret needed, we're just bucketing, not authenticating
    $ipHash = hash('sha256', 'rl_v1_payment_' . $ip);
    $now    = time();

    $sel = $db->prepare('SELECT request_count, window_start FROM resolve_rate_limit WHERE ip_hash = ?');
    $sel->bind_param('s', $ipHash);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    $sel->close();

    if ($row === null) {
        // First request from this IP
        $ins = $db->prepare(
            'INSERT INTO resolve_rate_limit (ip_hash, request_count, window_start)
             VALUES (?, 1, FROM_UNIXTIME(?))'
        );
        $ins->bind_param('si', $ipHash, $now);
        $ins->execute();
        $ins->close();
        return true;
    }

    $windowStart = (int) strtotime((string) $row['window_start']);

    if (($now - $windowStart) >= $WINDOW) {
        // Window expired — reset
        $upd = $db->prepare(
            'UPDATE resolve_rate_limit SET request_count = 1, window_start = FROM_UNIXTIME(?) WHERE ip_hash = ?'
        );
        $upd->bind_param('is', $now, $ipHash);
        $upd->execute();
        $upd->close();
        return true;
    }

    if ((int) $row['request_count'] >= $LIMIT) {
        return false;
    }

    $inc = $db->prepare(
        'UPDATE resolve_rate_limit SET request_count = request_count + 1 WHERE ip_hash = ?'
    );
    $inc->bind_param('s', $ipHash);
    $inc->execute();
    $inc->close();

    return true;
}

/**
 * Returns the client's real IP address.
 */
function payment_client_ip(): string
{
    // Prioritise X-Forwarded-For only when behind a trusted proxy
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}
