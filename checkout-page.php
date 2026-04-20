<?php

if (!defined('BASE')) define('BASE', '');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/courses-repo.php';
require_once __DIR__ . '/includes/purchases-repo.php';
require_once __DIR__ . '/includes/coupons-repo.php';
require_once __DIR__ . '/includes/stripe-api.php';
require_once __DIR__ . '/includes/stripe-config.php';

$courseId = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
if ($courseId <= 0) {
    header('Location: ' . BASE . '/courses.php');
    exit;
}

$currentUser = auth_current_user();
if (!$currentUser) {
    header('Location: ' . BASE . '/login.php?redirect=' . urlencode(BASE . '/course.php?id=' . $courseId));
    exit;
}

$clientId = (int) $currentUser['id'];

ensure_purchases_table($mysqli);
$course = find_course_by_id($mysqli, $courseId);
if (!$course) {
    header('Location: ' . BASE . '/courses.php');
    exit;
}

if (has_user_enrolled_course($mysqli, $clientId, $courseId)) {
    header('Location: ' . BASE . '/course-player.php?course=' . $courseId);
    exit;
}

$couponCodeInput = trim((string) ($_GET['coupon_code'] ?? ''));
$couponCode      = '';
$discountPercent = 0.0;

if ($couponCodeInput !== '') {
    $couponCheck = validate_coupon_code($mysqli, $couponCodeInput);
    if ($couponCheck['ok'] ?? false) {
        $couponCode      = normalize_coupon_code($couponCodeInput);
        $discountPercent = (float) ($couponCheck['discount_percent'] ?? 0);
    }
}

$originalPrice = (float) $course['price'];
$finalPrice    = round($originalPrice * (1 - ($discountPercent / 100)), 2);
$priceCents    = (int) round($finalPrice * 100);

if ($priceCents <= 0) {
    header('Location: ' . BASE . '/checkout.php');
    exit;
}

$piParams = [
    'amount'                             => (string) $priceCents,
    'currency'                           => 'usd',
    'payment_method_types[]'             => 'card',   // card covers Google Pay & Apple Pay via Payment Request API
    'metadata[course_id]'                => (string) $courseId,
    'metadata[client_id]'                => (string) $clientId,
    'metadata[coupon_code]'              => $couponCode,
    'metadata[discount_percent]'         => (string) $discountPercent,
];

try {
    $intent = stripe_create_payment_intent($piParams);
} catch (RuntimeException $e) {
    error_log('checkout-page: PaymentIntent error: ' . $e->getMessage());
    header('Location: ' . BASE . '/course.php?id=' . $courseId . '&payment_error=1');
    exit;
}

$userEmail   = htmlspecialchars((string) $currentUser['email'],        ENT_QUOTES | ENT_HTML5, 'UTF-8');
$userName    = htmlspecialchars((string) ($currentUser['full_name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$amountDisplay = number_format($finalPrice, 2);

$jsPublishableKey = json_encode(STRIPE_PUBLISHABLE_KEY);
$jsClientSecret   = json_encode((string) $intent['client_secret']);
$jsAmount         = json_encode($priceCents);
$jsCurrency       = json_encode('usd');
$jsCourseTitle    = json_encode((string) $course['title']);
$jsCourseId       = json_encode($courseId);
$jsBaseUrl        = json_encode(BASE);

$courseTitle     = htmlspecialchars((string) $course['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
$courseThumb     = !empty($course['thumbnail']) ? htmlspecialchars((string) $course['thumbnail'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
$originalDisplay = number_format($originalPrice, 2);
$finalDisplay    = number_format($finalPrice, 2);
$savingsDisplay  = $discountPercent > 0 ? number_format($originalPrice - $finalPrice, 2) : '';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Checkout – <?= $courseTitle ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --indigo: #4f46e5;
      --indigo-light: #6366f1;
      --radius: 14px;
      --border: #e5e7eb;
      --text: #111827;
      --muted: #6b7280;
      --bg-left: #f8f7ff;
    }

    html, body { height: 100%; }

    body {
      font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
      background: #fff;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── Two-column shell ──────────────────────────────────── */
    .checkout-shell {
      display: grid;
      grid-template-columns: 1fr 1fr;
      min-height: 100vh;
    }

    /* ── LEFT: order summary ──────────────────────────────── */
    .summary-col {
      background: var(--bg-left);
      padding: 56px 48px 56px 56px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 44px;
    }
    .brand-icon {
      width: 34px; height: 34px;
      background: linear-gradient(135deg, var(--indigo), var(--indigo-light));
      border-radius: 9px;
      display: flex; align-items: center; justify-content: center;
    }
    .brand-icon svg { width: 18px; height: 18px; }
    .brand-name {
      font-size: 1.05rem;
      font-weight: 800;
      color: var(--text);
      letter-spacing: -.02em;
    }

    .course-thumb {
      width: 100%;
      aspect-ratio: 16/9;
      object-fit: cover;
      border-radius: var(--radius);
      margin-bottom: 24px;
      background: #e0e7ff;
    }
    .course-thumb-placeholder {
      width: 100%;
      aspect-ratio: 16/9;
      border-radius: var(--radius);
      background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 24px;
    }
    .course-thumb-placeholder svg { width: 40px; height: 40px; color: var(--indigo); opacity: .5; }

    .course-label {
      font-size: .7rem;
      font-weight: 700;
      letter-spacing: .1em;
      text-transform: uppercase;
      color: var(--indigo);
      margin-bottom: 8px;
    }

    .course-title {
      font-size: 1.35rem;
      font-weight: 800;
      color: var(--text);
      line-height: 1.3;
      margin-bottom: 24px;
      letter-spacing: -.02em;
    }

    .price-box {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 18px 20px;
    }

    .price-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: .9rem;
      color: var(--muted);
    }
    .price-row + .price-row { margin-top: 10px; }
    .price-row.total {
      border-top: 1px solid var(--border);
      margin-top: 14px;
      padding-top: 14px;
      font-size: 1.1rem;
      font-weight: 800;
      color: var(--text);
    }
    .price-row .strike { text-decoration: line-through; color: #9ca3af; }
    .badge-discount {
      background: #ecfdf5;
      color: #059669;
      font-size: .72rem;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 99px;
      letter-spacing: .02em;
    }

    .trust-row {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 22px;
      color: var(--muted);
      font-size: .78rem;
      font-weight: 500;
    }
    .trust-row svg { flex-shrink: 0; color: #10b981; }

    /* ── RIGHT: payment form ──────────────────────────────── */
    .payment-col {
      padding: 56px 56px 56px 48px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .payment-heading {
      font-size: 1.45rem;
      font-weight: 800;
      color: var(--text);
      letter-spacing: -.025em;
      margin-bottom: 6px;
    }
    .payment-sub {
      font-size: .88rem;
      color: var(--muted);
      margin-bottom: 28px;
    }

    /* ── Express buttons ───────────────────────────────────── */
    .express-wrap { margin-bottom: 22px; }

    .express-label {
      text-align: center;
      font-size: .75rem;
      font-weight: 600;
      color: #9ca3af;
      letter-spacing: .06em;
      text-transform: uppercase;
      margin-bottom: 12px;
    }

    .express-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    .express-row.single { grid-template-columns: 1fr; }

    .btn-express {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      height: 52px;
      border: 1.5px solid #d1d5db;
      border-radius: 11px;
      font-size: .88rem;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      background: #000;
      color: #fff;
      transition: opacity .15s, border-color .15s;
    }
    .btn-express:hover { opacity: .85; }
    .btn-express:active { opacity: .72; }

    .or-divider {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 20px 0 24px;
    }
    .or-divider::before,
    .or-divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }
    .or-divider span {
      font-size: .72rem;
      font-weight: 600;
      color: #9ca3af;
      letter-spacing: .08em;
      text-transform: uppercase;
    }

    /* ── Card fields ───────────────────────────────────────── */
    .field { margin-bottom: 16px; }
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px; }

    label {
      display: block;
      font-size: .78rem;
      font-weight: 600;
      color: var(--text);
      margin-bottom: 7px;
      letter-spacing: .01em;
    }

    .stripe-el {
      border: 1.5px solid var(--border);
      border-radius: 10px;
      padding: 13px 14px;
      background: #fff;
      transition: border-color .15s, box-shadow .15s;
    }
    .stripe-el.focused {
      border-color: var(--indigo);
      box-shadow: 0 0 0 3px rgba(79,70,229,.1);
    }
    .stripe-el.invalid {
      border-color: #ef4444;
      box-shadow: 0 0 0 3px rgba(239,68,68,.08);
    }

    /* ── Error ─────────────────────────────────────────────── */
    .error-msg {
      min-height: 1.1rem;
      color: #dc2626;
      font-size: .8rem;
      margin-bottom: 10px;
      font-weight: 500;
    }

    /* ── Pay button ────────────────────────────────────────── */
    .btn-pay {
      width: 100%;
      padding: 15px;
      background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
      color: #fff;
      font-size: .97rem;
      font-weight: 700;
      font-family: inherit;
      border: none;
      border-radius: 11px;
      cursor: pointer;
      transition: opacity .15s, transform .1s;
      letter-spacing: .01em;
    }
    .btn-pay:hover:not(:disabled) { opacity: .92; transform: translateY(-1px); }
    .btn-pay:active:not(:disabled) { transform: translateY(0); }
    .btn-pay:disabled { opacity: .55; cursor: not-allowed; transform: none; }

    /* ── Spinner ───────────────────────────────────────────── */
    @keyframes spin { to { transform: rotate(360deg); } }
    .spinner {
      display: inline-block;
      width: 14px; height: 14px;
      border: 2px solid rgba(255,255,255,.3);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin .5s linear infinite;
      vertical-align: middle;
      margin-right: 7px;
    }

    .secure-note {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      margin-top: 14px;
      font-size: .75rem;
      color: #9ca3af;
      font-weight: 500;
    }
    .secure-note svg { flex-shrink: 0; }

    /* ── Responsive ────────────────────────────────────────── */
    @media (max-width: 860px) {
      .checkout-shell { grid-template-columns: 1fr; }
      .summary-col { padding: 40px 28px 32px; }
      .payment-col { padding: 32px 28px 48px; }
      .course-title { font-size: 1.15rem; }
    }
    @media (max-width: 480px) {
      .summary-col, .payment-col { padding: 28px 20px; }
      .field-row { grid-template-columns: 1fr; }
      .express-row { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<div class="checkout-shell">

  <!-- ── LEFT: Order summary ──────────────────────────────────────────────── -->
  <div class="summary-col">

    <div class="brand">
      <div class="brand-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
        </svg>
      </div>
      <span class="brand-name">NerdAcademy</span>
    </div>

    <?php if ($courseThumb): ?>
      <img src="<?= $courseThumb ?>" alt="<?= $courseTitle ?>" class="course-thumb">
    <?php else: ?>
      <div class="course-thumb-placeholder">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>
        </svg>
      </div>
    <?php endif; ?>

    <p class="course-label">Course enrollment</p>
    <h2 class="course-title"><?= $courseTitle ?></h2>

    <div class="price-box">
      <?php if ($discountPercent > 0): ?>
      <div class="price-row">
        <span>Original price</span>
        <span class="strike">$<?= $originalDisplay ?></span>
      </div>
      <div class="price-row">
        <span>Discount (<?= (int) $discountPercent ?>% off)</span>
        <span class="badge-discount">−$<?= $savingsDisplay ?></span>
      </div>
      <?php endif; ?>
      <div class="price-row total">
        <span>Total due today</span>
        <span>$<?= $finalDisplay ?></span>
      </div>
    </div>

    <div class="trust-row">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
      </svg>
      30-day money-back guarantee &nbsp;·&nbsp; Lifetime access
    </div>

  </div><!-- /summary-col -->

  <!-- ── RIGHT: Payment form ──────────────────────────────────────────────── -->
  <div class="payment-col">

    <h1 class="payment-heading">Complete your purchase</h1>
    <p class="payment-sub">Pay securely with your preferred method below.</p>

    <!-- Express checkout (Google Pay / Apple Pay) -->
    <div class="express-wrap">
      <p class="express-label">Express checkout</p>
      <div class="express-row">

        <!-- Apple Pay -->
        <button type="button" class="btn-express" id="btn-apay" aria-label="Pay with Apple Pay">
          <svg width="19" height="19" viewBox="0 0 814 1000" fill="white" aria-hidden="true">
            <path d="M788.1 340.9c-5.8 4.5-108.2 62.2-108.2 190.5 0 148.4 130.3 200.9 134.2 202.2-.6 3.2-20.7 71.9-68.7 141.9-42.8 61.6-87.5 123.1-155.5 123.1s-85.5-39.5-164-39.5c-76 0-103.7 40.8-165.9 40.8s-105-57.8-155.5-127.4C46 790.8 0 663.5 0 541.8c0-207.4 134.4-317 267.2-317 70.5 0 128.9 46.5 168.4 46.5 37.5 0 105.5-50.5 185.5-50.5 29.4 0 108.4 2.6 168.4 74.5zm-244.7-175.3c35.2-43 60.5-103.3 60.5-163.6 0-8.3-.6-16.6-2-24.9-57.3 2.2-124.1 38.4-164.4 86.3-31.2 36.7-61.8 97-61.8 158.1 0 9 1.3 18 2 20.7 3.5.6 9 1.3 14.5 1.3 51.4 0 113.8-34.7 151.2-77.9z"/>
          </svg>
          <span>Apple Pay</span>
        </button>

        <!-- Google Pay -->
        <button type="button" class="btn-express" id="btn-gpay" aria-label="Pay with Google Pay">
          <svg width="19" height="19" viewBox="0 0 48 48" aria-hidden="true">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
          </svg>
          <span style="font-weight:700;letter-spacing:-.01em">G Pay</span>
        </button>

      </div>
    </div>

    <div class="or-divider"><span>Or pay by card</span></div>

    <!-- Card Number -->
    <div class="field">
      <label>Card number</label>
      <div id="card-number" class="stripe-el" aria-label="Card number"></div>
    </div>

    <!-- Expiry + CVC -->
    <div class="field-row">
      <div>
        <label>Expiry date</label>
        <div id="card-expiry" class="stripe-el" aria-label="Expiry date"></div>
      </div>
      <div>
        <label>CVC</label>
        <div id="card-cvc" class="stripe-el" aria-label="CVC"></div>
      </div>
    </div>

    <!-- Error -->
    <div id="card-errors" class="error-msg" role="alert" aria-live="assertive"></div>

    <!-- Pay -->
    <button type="button" id="pay-btn" class="btn-pay">
      Pay $<?= $finalDisplay ?> now
    </button>

    <div class="secure-note">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
      </svg>
      Secured by Stripe. Your payment info is never stored.
    </div>

  </div><!-- /payment-col -->

</div><!-- /checkout-shell -->

<script src="https://js.stripe.com/v3/"></script>
<script>
(function () {
  'use strict';

  var PK           = <?= $jsPublishableKey ?>;
  var CLIENT_SECRET= <?= $jsClientSecret ?>;
  var AMOUNT       = <?= $jsAmount ?>;
  var CURRENCY     = <?= $jsCurrency ?>;
  var COURSE_TITLE = <?= $jsCourseTitle ?>;
  var COURSE_ID    = <?= $jsCourseId ?>;
  var BASE_URL     = <?= $jsBaseUrl ?>;

  var stripe   = Stripe(PK);
  var elements = stripe.elements();

  var elStyle = {
    base: {
      fontSize: '14.5px',
      color: '#111827',
      fontFamily: "'Inter','Segoe UI',system-ui,sans-serif",
      fontSmoothing: 'antialiased',
      '::placeholder': { color: '#c4c8d0' }
    },
    invalid: { color: '#ef4444' }
  };

  var cardNumber = elements.create('cardNumber', { style: elStyle });
  var cardExpiry = elements.create('cardExpiry', { style: elStyle });
  var cardCvc    = elements.create('cardCvc',    { style: elStyle, placeholder: 'CVC' });

  cardNumber.mount('#card-number');
  cardExpiry.mount('#card-expiry');
  cardCvc.mount('#card-cvc');

  [
    { el: cardNumber, id: 'card-number' },
    { el: cardExpiry, id: 'card-expiry' },
    { el: cardCvc,    id: 'card-cvc'    }
  ].forEach(function (item) {
    var wrap = document.getElementById(item.id);
    item.el.on('focus',  function ()  { wrap.classList.add('focused'); });
    item.el.on('blur',   function ()  { wrap.classList.remove('focused'); });
    item.el.on('change', function (e) { wrap.classList.toggle('invalid', !!e.error); });
  });

  // ── Payment Request (Google Pay / Apple Pay) ───────────────────────────────
  var pr = stripe.paymentRequest({
    country: 'US',
    currency: CURRENCY,
    total: { label: COURSE_TITLE, amount: AMOUNT },
    requestPayerName: false,
    requestPayerEmail: false,
    requestPayerPhone: false
  });

  var prReady = false;
  var btnApay = document.getElementById('btn-apay');
  var btnGpay = document.getElementById('btn-gpay');
  var expressRow = document.querySelector('.express-row');

  pr.canMakePayment().then(function (result) {
    prReady = !!result;
    if (!result) return;

    var hasApple  = !!result.applePay;
    var hasGoogle = !!result.googlePay;

    if (!hasApple)  btnApay.style.display = 'none';
    if (!hasGoogle) btnGpay.style.display = 'none';
    if (hasApple !== hasGoogle) expressRow.classList.add('single');
  });

  function triggerPR() {
    if (prReady) {
      pr.show();
    } else {
      showError('This payment method is not available in your browser. Please use card payment.');
    }
  }

  btnApay.addEventListener('click', triggerPR);
  btnGpay.addEventListener('click', triggerPR);

  pr.on('paymentmethod', function (ev) {
    setLoading(true);
    stripe.confirmCardPayment(CLIENT_SECRET, { payment_method: ev.paymentMethod.id }, { handleActions: false })
      .then(function (result) {
        if (result.error) {
          ev.complete('fail');
          showError(result.error.message || 'Payment failed. Please try again.');
          return;
        }
        ev.complete('success');
        if (result.paymentIntent.status === 'requires_action') {
          stripe.confirmCardPayment(CLIENT_SECRET).then(function (r) {
            if (r.error) { showError(r.error.message || 'Authentication failed.'); }
            else { completeEnrollment(r.paymentIntent.id); }
          });
        } else {
          completeEnrollment(result.paymentIntent.id);
        }
      })
      .catch(function () {
        ev.complete('fail');
        showError('Payment failed. Please try again.');
      });
  });

  // ── Card submit ────────────────────────────────────────────────────────────
  var payBtn = document.getElementById('pay-btn');
  var errBox = document.getElementById('card-errors');

  payBtn.addEventListener('click', function () {
    errBox.textContent = '';
    setLoading(true);
    stripe.confirmCardPayment(CLIENT_SECRET, {
      payment_method: { card: cardNumber }
    }).then(function (result) {
      if (result.error) { showError(result.error.message || 'Payment failed. Please try again.'); return; }
      if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
        completeEnrollment(result.paymentIntent.id);
      }
    });
  });

  function showError(msg) {
    errBox.textContent = msg;
    setLoading(false);
  }

  function setLoading(on) {
    payBtn.disabled = on;
    payBtn.innerHTML = on
      ? '<span class="spinner"></span>Processing\u2026'
      : 'Pay $<?= $finalDisplay ?> now';
  }

  function completeEnrollment(piId) {
    fetch(BASE_URL + '/api/complete-enrollment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ payment_intent_id: piId, course_id: COURSE_ID })
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.success) { window.location.href = data.redirect_url; }
      else { showError(data.message || 'Enrollment failed. Please contact support.'); }
    })
    .catch(function () {
      showError('Network error. Please contact support with your payment confirmation.');
    });
  }

})();
</script>
</body>
</html>
