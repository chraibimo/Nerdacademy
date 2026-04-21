<?php
if (!function_exists('_env')) {
    function _env(string $k, string $d = ''): string { $v = getenv($k); return ($v !== false && $v !== '') ? $v : ($_SERVER[$k] ?? $_ENV[$k] ?? $d); }
}

// ── Stripe keys ──────────────────────────────────────────────────────────────
// Override with real environment variables in production. Never commit live keys.
define('PAYMENT_STRIPE_PK', _env('STRIPE_PK'));
define('PAYMENT_STRIPE_SK', _env('STRIPE_SK'));

// ── Shared secret: n8n → /payment/store-order ─────────────────────────────────
// Set PAYMENT_N8N_SECRET as an env var in production before going live.
define('PAYMENT_N8N_SECRET', _env('PAYMENT_N8N_SECRET', 'CHANGE_THIS_BEFORE_PRODUCTION'));

// ── API Key for checkout link generation: n8n → /api/create-checkout-link ────
// Set CHECKOUT_API_KEY as an env var in production before going live.
define('CHECKOUT_API_KEY', _env('CHECKOUT_API_KEY', 'CHANGE_THIS_BEFORE_PRODUCTION'));

// ── Allowed origins for /payment/resolve ─────────────────────────────────────
define('PAYMENT_ALLOWED_ORIGINS', ['https://nerdacademy.ai', 'https://www.nerdacademy.ai']);

// ── Order token expiry: 2 hours ───────────────────────────────────────────────
define('PAYMENT_ORDER_TTL', 7200);
