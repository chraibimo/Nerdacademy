<?php


require_once __DIR__ . '/db.php';

function ensure_coupons_table(mysqli $mysqli): void
{
    $mysqli->query("CREATE TABLE IF NOT EXISTS coupon_codes (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        code VARCHAR(50) NOT NULL,
        label VARCHAR(120) NOT NULL DEFAULT '',
        discount_percent DECIMAL(5,2) NOT NULL,
        max_uses INT NOT NULL DEFAULT 0,
        used_count INT NOT NULL DEFAULT 0,
        expires_at DATETIME NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_coupon_code (code),
        KEY idx_coupon_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function normalize_coupon_code(string $code): string
{
    return strtoupper(trim($code));
}

function get_coupon_by_code(mysqli $mysqli, string $code): ?array
{
    ensure_coupons_table($mysqli);

    $normalized = normalize_coupon_code($code);
    if ($normalized === '') {
        return null;
    }

    $stmt = $mysqli->prepare('SELECT * FROM coupon_codes WHERE code = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $normalized);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function validate_coupon_code(mysqli $mysqli, string $code): array
{
    $coupon = get_coupon_by_code($mysqli, $code);
    if (!$coupon) {
        return ['ok' => false, 'error' => 'invalid-coupon'];
    }

    if ((int)($coupon['is_active'] ?? 0) !== 1) {
        return ['ok' => false, 'error' => 'inactive-coupon', 'coupon' => $coupon];
    }

    $now = time();
    $expiresAt = (string)($coupon['expires_at'] ?? '');
    if ($expiresAt !== '' && strtotime($expiresAt) < $now) {
        return ['ok' => false, 'error' => 'expired-coupon', 'coupon' => $coupon];
    }

    $maxUses = (int)($coupon['max_uses'] ?? 0);
    $usedCount = (int)($coupon['used_count'] ?? 0);
    if ($maxUses > 0 && $usedCount >= $maxUses) {
        return ['ok' => false, 'error' => 'coupon-limit-reached', 'coupon' => $coupon];
    }

    return ['ok' => true, 'coupon' => $coupon, 'discount_percent' => (float)($coupon['discount_percent'] ?? 0)];
}

function consume_coupon_code(mysqli $mysqli, string $code): bool
{
    $coupon = get_coupon_by_code($mysqli, $code);
    if (!$coupon) {
        return false;
    }

    $maxUses = (int)($coupon['max_uses'] ?? 0);
    $stmt = $mysqli->prepare('UPDATE coupon_codes SET used_count = used_count + 1 WHERE code = ? AND is_active = 1 AND (? = 0 OR used_count < ?)');
    if (!$stmt) {
        return false;
    }

    $normalized = normalize_coupon_code($code);
    $stmt->bind_param('sii', $normalized, $maxUses, $maxUses);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();

    return $ok;
}
