-- =============================================================================
-- payment_orders — stores order tokens issued by n8n mapped to Stripe
--                  PaymentIntents.  Records expire after 2 hours.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `payment_orders` (
    `id`                        INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `order_token`               VARCHAR(64)     NOT NULL,
    `stripe_payment_intent_id`  VARCHAR(128)    NOT NULL,
    `stripe_client_secret`      TEXT            NOT NULL,
    `customer_first_name`       VARCHAR(100)    NOT NULL DEFAULT '',
    `customer_last_name`        VARCHAR(100)    NOT NULL DEFAULT '',
    `customer_email`            VARCHAR(255)    NOT NULL DEFAULT '',
    `postal_code`               VARCHAR(20)     NOT NULL DEFAULT '',
    `plan_id`                   VARCHAR(100)    NOT NULL DEFAULT '',
    `amount`                    INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Amount in smallest currency unit (cents)',
    `currency`                  VARCHAR(10)     NOT NULL DEFAULT 'cad',
    `product_name`              VARCHAR(255)    NOT NULL DEFAULT '',
    `created_at`                DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`                DATETIME        NOT NULL,
    `used_at`                   DATETIME                 DEFAULT NULL COMMENT 'Set by Stripe webhook after payment_intent.succeeded',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_token` (`order_token`),
    KEY `idx_expires_at`        (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- resolve_rate_limit — per-IP request buckets for /payment/resolve.
--                      One row per unique IP hash; reset every 60 seconds.
-- =============================================================================
CREATE TABLE IF NOT EXISTS `resolve_rate_limit` (
    `ip_hash`       VARCHAR(64)         NOT NULL  COMMENT 'SHA-256 of "rl_v1_payment_" + IP',
    `request_count` SMALLINT UNSIGNED   NOT NULL  DEFAULT 0,
    `window_start`  DATETIME            NOT NULL,
    PRIMARY KEY (`ip_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
