<?php
// Fill in your Stripe keys from https://dashboard.stripe.com/apikeys
if (!function_exists('_env')) {
    function _env(string $k, string $d = ''): string { $v = getenv($k); return ($v !== false && $v !== '') ? $v : ($_SERVER[$k] ?? $_ENV[$k] ?? $d); }
}
define('STRIPE_SECRET_KEY',      _env('STRIPE_SECRET_KEY'));
define('STRIPE_PUBLISHABLE_KEY', _env('STRIPE_PUBLISHABLE_KEY'));
define('STRIPE_WEBHOOK_SECRET',  _env('STRIPE_WEBHOOK_SECRET'));
