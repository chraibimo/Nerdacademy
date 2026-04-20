<?php

/**
 * GET /payment/card?order=ORD_...
 *
 * Standalone payment page — rendered inside an Elementor popup iframe.
 * No site layout. Renders Stripe Card Elements (CardNumber / CardExpiry / CardCvc).
 */

require_once __DIR__ . '/_config.php';
require_once __DIR__ . '/_helpers.php';
require_once dirname(__DIR__) . '/includes/db.php'; // provides $mysqli

payment_ensure_tables($mysqli);

$orderToken = trim((string) ($_GET['order'] ?? ''));
$orderData  = null;
$disabled   = true;

if ($orderToken !== '') {
    $orderData = payment_resolve_order($mysqli, $orderToken);
    if ($orderData !== null) {
        $disabled = false;
    }
}

// Values injected into HTML — always escaped
$productName    = $disabled ? '' : htmlspecialchars((string) $orderData['product_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
$amountCents    = $disabled ? 0  : (int) $orderData['amount'];
$currency       = $disabled ? 'CAD' : strtoupper((string) $orderData['currency']);
$amountDisplay  = $disabled ? '0.00' : number_format($amountCents / 100, 2);

// These go into JS via json_encode — safe against XSS
$jsPublishableKey = $disabled ? '' : (string) PAYMENT_STRIPE_PK;
$jsClientSecret   = $disabled ? '' : (string) $orderData['client_secret'];
$jsCurrency       = $disabled ? '' : strtolower((string) $orderData['currency']);
$jsAmount         = $disabled ? '0.00' : $amountDisplay;
$jsOrderToken     = $orderToken;
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $disabled ? 'Payment Unavailable' : 'Complete Your Payment' ?></title>

  <!-- iframe-resizer: makes this iframe auto-size inside the parent popup -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/iframe-resizer/4.3.9/iframeResizer.contentWindow.min.js"
          integrity="sha512-fui6CpkZZtzPoKTH4+Dh62p2RQflM0L3+hBqxQBfPVBM6w8Vy0/9eXyR+TiD5GfZjRHKQBBhFJq0BCVPUPFQ=="
          crossorigin="anonymous"></script>

  <style>
    *, *::before, *::after { box-sizing: border-box; }

    body {
      margin: 0;
      padding: 24px 16px 32px;
      background: #f4f6fb;
      font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
      color: #1f2937;
      min-height: 100vh;
    }

    .payment-card {
      background: #ffffff;
      border-radius: 16px;
      box-shadow: 0 4px 24px rgba(0, 0, 0, .09);
      padding: 28px 28px 32px;
      max-width: 480px;
      margin: 0 auto;
    }

    /* ── Header ── */
    .pay-header { text-align: center; margin-bottom: 24px; }
    .pay-header .product-name { font-size: .85rem; color: #6b7280; margin-bottom: 6px; }
    .pay-header .product-title { font-size: 1.15rem; font-weight: 700; color: #111827; margin-bottom: 10px; }
    .pay-header .amount-badge {
      display: inline-block;
      background: #eff0ff;
      color: #4338ca;
      font-weight: 700;
      font-size: 1.25rem;
      padding: 6px 20px;
      border-radius: 999px;
    }

    /* ── Labels & inputs ── */
    .field-label {
      display: block;
      font-size: .8rem;
      font-weight: 600;
      color: #374151;
      margin-bottom: 5px;
    }
    .field-row { margin-bottom: 16px; }
    .field-row-half { display: flex; gap: 14px; margin-bottom: 16px; }
    .field-row-half .field-half { flex: 1; min-width: 0; }

    input[type="text"] {
      width: 100%;
      border: 1.5px solid #d1d5db;
      border-radius: 8px;
      padding: 11px 13px;
      font-size: .925rem;
      color: #1f2937;
      background: #fff;
      outline: none;
      transition: border-color .18s, box-shadow .18s;
    }
    input[type="text"]:focus {
      border-color: #6366f1;
      box-shadow: 0 0 0 3px rgba(99,102,241,.15);
    }

    /* ── Stripe Elements wrappers ── */
    .stripe-el {
      border: 1.5px solid #d1d5db;
      border-radius: 8px;
      padding: 11px 13px;
      background: #fff;
      transition: border-color .18s, box-shadow .18s;
    }
    .stripe-el.is-focused {
      border-color: #6366f1;
      box-shadow: 0 0 0 3px rgba(99,102,241,.15);
    }
    .stripe-el.is-invalid {
      border-color: #ef4444;
      box-shadow: 0 0 0 3px rgba(239,68,68,.12);
    }

    /* ── Divider ── */
    .divider {
      border: none;
      border-top: 1px solid #f0f0f4;
      margin: 20px 0;
    }

    /* ── Pay button ── */
    .btn-pay {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      padding: 13px;
      background: #4f46e5;
      color: #fff;
      font-size: 1rem;
      font-weight: 700;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: background .18s, opacity .18s;
      margin-bottom: 4px;
    }
    .btn-pay:hover:not(:disabled) { background: #4338ca; }
    .btn-pay:disabled { opacity: .65; cursor: not-allowed; }

    /* ── Error message ── */
    .error-msg {
      min-height: 1.25rem;
      color: #dc2626;
      font-size: .85rem;
      margin-top: 8px;
    }

    /* ── Secure note ── */
    .secure-note {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      margin-top: 14px;
      font-size: .75rem;
      color: #9ca3af;
    }
    .secure-note strong { color: #635BFF; }

    /* ── Spinner ── */
    @keyframes spin { to { transform: rotate(360deg); } }
    .spinner {
      width: 16px; height: 16px;
      border: 2px solid rgba(255,255,255,.4);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin .6s linear infinite;
      flex-shrink: 0;
    }

    /* ── Disabled state ── */
    .disabled-state {
      text-align: center;
      padding: 32px 16px;
    }
    .disabled-icon {
      width: 60px; height: 60px;
      background: #fee2e2;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 18px;
    }
    .disabled-state h2 { font-size: 1.1rem; font-weight: 700; color: #111827; margin-bottom: 8px; }
    .disabled-state p  { font-size: .875rem; color: #6b7280; line-height: 1.5; margin: 0; }
  </style>
</head>
<body>
<div class="payment-card">

<?php if ($disabled): ?>
  <!-- ── Disabled / invalid order state ─────────────────────────────────── -->
  <div class="disabled-state">
    <div class="disabled-icon">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M18 6L6 18M6 6l12 12" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
    </div>
    <h2>Payment Link Unavailable</h2>
    <p>This payment link is invalid or has expired.<br>
       Please return to the checkout page to start again.</p>
  </div>

<?php else: ?>
  <!-- ── Order header ───────────────────────────────────────────────────── -->
  <div class="pay-header">
    <p class="product-name">You're paying for</p>
    <p class="product-title"><?= $productName ?></p>
    <span class="amount-badge"><?= $currency ?> $<?= $amountDisplay ?></span>
  </div>

  <hr class="divider">

  <!-- ── Payment form ──────────────────────────────────────────────────── -->
  <form id="payment-form" novalidate>

    <div class="field-row">
      <label class="field-label" for="card-holder-name">Name on card</label>
      <input type="text" id="card-holder-name" placeholder="Jane Smith"
             autocomplete="cc-name" spellcheck="false" maxlength="200">
    </div>

    <div class="field-row">
      <label class="field-label">Card number</label>
      <div id="card-number" class="stripe-el" aria-label="Card number"></div>
    </div>

    <div class="field-row-half">
      <div class="field-half">
        <label class="field-label">Expiry date</label>
        <div id="card-expiry" class="stripe-el" aria-label="Expiry date"></div>
      </div>
      <div class="field-half">
        <label class="field-label">Security code (CVC)</label>
        <div id="card-cvc" class="stripe-el" aria-label="CVC"></div>
      </div>
    </div>

    <div class="field-row">
      <label class="field-label" for="postal-code">Postal / ZIP code</label>
      <input type="text" id="postal-code" placeholder="A1A 1A1"
             autocomplete="postal-code" maxlength="10" spellcheck="false">
    </div>

    <button type="submit" class="btn-pay" id="pay-btn">
      Pay <?= $currency ?> $<?= $amountDisplay ?>
    </button>

    <div class="error-msg" id="card-errors" role="alert" aria-live="assertive"></div>
  </form>

  <div class="secure-note">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z" fill="#9ca3af"/>
    </svg>
    Secured by <strong>Stripe</strong>
  </div>

<?php endif; ?>
</div><!-- /.payment-card -->

<?php if (!$disabled): ?>
<script src="https://js.stripe.com/v3/"></script>
<script>
(function () {
  'use strict';

  var PUBLISHABLE_KEY = <?= json_encode($jsPublishableKey, JSON_UNESCAPED_SLASHES) ?>;
  var CLIENT_SECRET   = <?= json_encode($jsClientSecret,   JSON_UNESCAPED_SLASHES) ?>;
  var AMOUNT_LABEL    = <?= json_encode($currency . ' $' . $jsAmount) ?>;
  var ORDER_TOKEN     = <?= json_encode($jsOrderToken) ?>;

  var stripe   = Stripe(PUBLISHABLE_KEY);
  var elements = stripe.elements();

  var elementStyle = {
    base: {
      fontSize: '15px',
      color: '#1f2937',
      fontFamily: "'Inter', 'Segoe UI', system-ui, sans-serif",
      '::placeholder': { color: '#9ca3af' }
    },
    invalid: { color: '#ef4444' }
  };

  var cardNumber = elements.create('cardNumber', { style: elementStyle });
  var cardExpiry = elements.create('cardExpiry', { style: elementStyle });
  var cardCvc    = elements.create('cardCvc',    { style: elementStyle, placeholder: '•••' });

  cardNumber.mount('#card-number');
  cardExpiry.mount('#card-expiry');
  cardCvc.mount('#card-cvc');

  // Focus / blur / validation styles
  [
    { el: cardNumber, id: 'card-number' },
    { el: cardExpiry, id: 'card-expiry' },
    { el: cardCvc,    id: 'card-cvc'    }
  ].forEach(function (item) {
    var wrapper = document.getElementById(item.id);
    item.el.on('focus', function () { wrapper.classList.add('is-focused'); });
    item.el.on('blur',  function () { wrapper.classList.remove('is-focused'); });
    item.el.on('change', function (e) {
      wrapper.classList.toggle('is-invalid', !!e.error);
    });
  });

  // ── Form submit ──────────────────────────────────────────────────────────
  var form   = document.getElementById('payment-form');
  var btn    = document.getElementById('pay-btn');
  var errBox = document.getElementById('card-errors');

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    errBox.textContent = '';

    var holderName = document.getElementById('card-holder-name').value.trim();
    var postal     = document.getElementById('postal-code').value.trim();

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner" aria-hidden="true"></span> Processing\u2026';

    var billingDetails = {};
    if (holderName) billingDetails.name = holderName;
    if (postal)     billingDetails.address = { postal_code: postal };

    stripe.confirmCardPayment(CLIENT_SECRET, {
      payment_method: {
        card: cardNumber,
        billing_details: billingDetails
      }
    }).then(function (result) {
      if (result.error) {
        errBox.textContent = result.error.message || 'Payment failed. Please try again.';
        btn.disabled = false;
        btn.textContent = 'Pay ' + AMOUNT_LABEL;
        return;
      }

      if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
        var piId = result.paymentIntent.id;

        // 1. Notify the parent window (closes popup / shows success state)
        var msg = { type: 'payment_success', paymentIntentId: piId, order: ORDER_TOKEN };
        try {
          // iframe-resizer channel
          window.parentIFrame.sendMessage(msg);
        } catch (_) {}
        // Standard postMessage — parent listens for this
        window.parent.postMessage(msg, '*');

        // 2. Redirect iframe to success page
        window.location.href = '/payment/success?pi=' + encodeURIComponent(piId);
      }
    });
  });
})();
</script>
<?php endif; ?>
</body>
</html>
