<?php

// ── Stripe keys ──────────────────────────────────────────────────────────────
// Override with real environment variables in production. Never commit live keys.
define('PAYMENT_STRIPE_PK', getenv('STRIPE_PK') ?: '');
define('PAYMENT_STRIPE_SK', getenv('STRIPE_SK') ?: '');

// ── Shared secret: n8n → /payment/store-order ─────────────────────────────────
// Set PAYMENT_N8N_SECRET as an env var in production before going live.
define('PAYMENT_N8N_SECRET', getenv('PAYMENT_N8N_SECRET') ?: 'CHANGE_THIS_BEFORE_PRODUCTION');

// ── Allowed origins for /payment/resolve ─────────────────────────────────────
define('PAYMENT_ALLOWED_ORIGINS', ['https://nerdacademy.ai', 'https://www.nerdacademy.ai']);

// ── Order token expiry: 2 hours ───────────────────────────────────────────────
define('PAYMENT_ORDER_TTL', 7200);
