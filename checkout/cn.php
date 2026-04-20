<?php
if (!defined('BASE')) define('BASE', '');

// Allow cross-origin iframe embedding
if (!headers_sent()) {
    header('Access-Control-Allow-Origin: *');
    header('X-Frame-Options: ALLOWALL');
    header('Permissions-Policy: payment=*');
}

require_once dirname(__DIR__) . '/includes/stripe-config.php';

$baseJson       = json_encode(BASE);
$jsPublishableKey = json_encode(STRIPE_PUBLISHABLE_KEY);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Checkout</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --indigo:      #4f46e5;
      --indigo-dark: #4338ca;
      --border:      #e5e7eb;
      --text:        #111827;
      --muted:       #6b7280;
    }

    body {
      font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
      background: #ffffff;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 24px 16px 32px;
    }

    /* ── Loading / Error states ──────────────────────────── */
    #state-loading,
    #state-error,
    #state-preview-banner {
      width: 100%;
      max-width: 480px;
      margin-bottom: 20px;
    }

    #state-loading {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 14px;
      padding: 60px 0;
      color: var(--muted);
      font-size: .88rem;
      font-weight: 500;
    }
    .loading-ring {
      width: 36px; height: 36px;
      border: 3px solid #e0e7ff;
      border-top-color: var(--indigo);
      border-radius: 50%;
      animation: spin .6s linear infinite;
    }

    #state-error {
      background: #fef2f2;
      border: 1.5px solid #fca5a5;
      border-radius: 10px;
      padding: 16px;
      color: #991b1b;
      font-size: .84rem;
      font-weight: 600;
      display: flex;
      align-items: flex-start;
      gap: 10px;
    }
    #state-error a {
      color: var(--indigo);
      text-decoration: underline;
    }

    #state-preview-banner {
      background: #fef3c7;
      border: 1.5px solid #fcd34d;
      border-radius: 10px;
      padding: 10px 14px;
      font-size: .78rem;
      font-weight: 600;
      color: #92400e;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* ── Payment card ─────────────────────────────────────── */
    #pay-card {
      width: 100%;
      max-width: 480px;
      display: none;
    }

    /* ── Mini order summary ───────────────────────────────── */
    .order-summary {
      display: flex;
      align-items: center;
      gap: 14px;
      background: transparent;
      padding: 0 0 20px;
      border-bottom: 1px solid #e5e7eb;
      margin-bottom: 20px;
    }
    .order-thumb {
      width: 52px; height: 52px;
      border-radius: 8px;
      object-fit: cover;
      flex-shrink: 0;
    }
    .order-thumb-placeholder {
      width: 52px; height: 52px;
      border-radius: 8px;
      background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
      flex-shrink: 0;
    }
    .order-title {
      flex: 1;
      font-size: .88rem;
      font-weight: 700;
      color: var(--text);
      line-height: 1.3;
    }
    .order-price {
      font-size: 1rem;
      font-weight: 800;
      color: var(--indigo);
      white-space: nowrap;
    }

    .pay-heading {
      font-size: 1.1rem;
      font-weight: 800;
      color: var(--text);
      margin-bottom: 18px;
      letter-spacing: -.02em;
    }

    /* ── Express checkout (custom buttons) ──── */
    .express-label {
      text-align: center;
      font-size: .7rem;
      font-weight: 700;
      color: #9ca3af;
      letter-spacing: .08em;
      text-transform: uppercase;
      margin-bottom: 10px;
    }
    .express-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-bottom: 16px;
    }
    .express-row.single { grid-template-columns: 1fr; }

    .btn-express {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      height: 48px;
      border: 1.5px solid #d1d5db;
      border-radius: 10px;
      font-size: .86rem;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      background: #000;
      color: #fff;
      transition: opacity .15s;
    }
    .btn-express:hover { opacity: .85; }

    .or-divider {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 0 0 20px;
    }
    .or-divider::before,
    .or-divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }
    .or-divider span {
      font-size: .7rem; font-weight: 700;
      color: #9ca3af; letter-spacing: .08em; text-transform: uppercase;
    }

    /* ── Card fields ──────────────────────────────────────── */
    .field { margin-bottom: 14px; }
    .field-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-bottom: 14px;
    }
    label {
      display: block;
      font-size: .78rem; font-weight: 600;
      color: var(--text); margin-bottom: 6px;
    }
    .stripe-el {
      border: 1.5px solid var(--border);
      border-radius: 10px;
      padding: 13px 14px;
      background: #fff;
      transition: border-color .15s, box-shadow .15s;
    }
    .stripe-el.focused { border-color: var(--indigo); box-shadow: 0 0 0 3px rgba(79,70,229,.1); }
    .stripe-el.invalid { border-color: #ef4444; box-shadow: 0 0 0 3px rgba(239,68,68,.08); }

    .error-msg {
      min-height: 1.1rem;
      color: #dc2626; font-size: .8rem;
      margin-bottom: 10px; font-weight: 500;
    }

    /* ── Pay button ───────────────────────────────────────── */
    .btn-pay {
      width: 100%; padding: 14px;
      background: linear-gradient(135deg, #4f46e5, #6366f1);
      color: #fff; font-size: .95rem; font-weight: 700;
      font-family: inherit; border: none; border-radius: 11px;
      cursor: pointer; transition: opacity .15s, transform .1s;
    }
    .btn-pay:hover:not(:disabled) { opacity: .92; transform: translateY(-1px); }
    .btn-pay:active:not(:disabled) { transform: translateY(0); }
    .btn-pay:disabled { opacity: .55; cursor: not-allowed; transform: none; }

    @keyframes spin { to { transform: rotate(360deg); } }
    .spinner {
      display: inline-block; width: 13px; height: 13px;
      border: 2px solid rgba(255,255,255,.35);
      border-top-color: #fff; border-radius: 50%;
      animation: spin .5s linear infinite;
      vertical-align: middle; margin-right: 6px;
    }

    .secure-note {
      display: flex; align-items: center;
      justify-content: center; gap: 6px;
      margin-top: 12px; font-size: .72rem;
      color: #9ca3af; font-weight: 500;
    }

    @media (max-width: 480px) {
      body { padding: 20px 12px 40px; }
      .express-row { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

  <!-- Loading spinner -->
  <div id="state-loading">
    <div class="loading-ring"></div>
    <span>Loading payment details&hellip;</span>
  </div>

  <!-- Error -->
  <div id="state-error" style="display:none"></div>

  <!-- Preview banner (shown when no order_id) -->
  <div id="state-preview-banner" style="display:none">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    Preview mode &mdash; no real payment will be processed.
  </div>

  <!-- Payment card -->
  <div id="pay-card">

    <p class="express-label">Express checkout</p>
    <div class="express-row" id="express-row">

      <button type="button" class="btn-express" id="btn-apay" aria-label="Pay with Apple Pay">
        <svg width="18" height="18" viewBox="0 0 814 1000" fill="white" aria-hidden="true">
          <path d="M788.1 340.9c-5.8 4.5-108.2 62.2-108.2 190.5 0 148.4 130.3 200.9 134.2 202.2-.6 3.2-20.7 71.9-68.7 141.9-42.8 61.6-87.5 123.1-155.5 123.1s-85.5-39.5-164-39.5c-76 0-103.7 40.8-165.9 40.8s-105-57.8-155.5-127.4C46 790.8 0 663.5 0 541.8c0-207.4 134.4-317 267.2-317 70.5 0 128.9 46.5 168.4 46.5 37.5 0 105.5-50.5 185.5-50.5 29.4 0 108.4 2.6 168.4 74.5zm-244.7-175.3c35.2-43 60.5-103.3 60.5-163.6 0-8.3-.6-16.6-2-24.9-57.3 2.2-124.1 38.4-164.4 86.3-31.2 36.7-61.8 97-61.8 158.1 0 9 1.3 18 2 20.7 3.5.6 9 1.3 14.5 1.3 51.4 0 113.8-34.7 151.2-77.9z"/>
        </svg>
        <span style="font-weight:500;font-size:.92rem;letter-spacing:.01em">Pay</span>
      </button>

      <button type="button" class="btn-express" id="btn-gpay" aria-label="Pay with Google Pay">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 48 48" aria-hidden="true">
          <path fill="#4285F4" d="M47.532 24.5528C47.532 22.9214 47.3997 21.2811 47.1175 19.6761H24.48V28.9181H37.4434C36.9055 31.8988 35.177 34.5356 32.6461 36.2111V42.1608H40.3801C44.9217 38.0088 47.532 31.8312 47.532 24.5528Z"/>
          <path fill="#34A853" d="M24.48 48.0016C30.9529 48.0016 36.4116 45.8764 40.3888 42.1608L32.6549 36.2111C30.5031 37.675 27.7252 38.5039 24.4888 38.5039C18.2275 38.5039 12.9187 34.2798 11.0139 28.6006H3.03296V34.7272C7.10718 42.8493 15.4056 48.0016 24.48 48.0016Z"/>
          <path fill="#FBBC04" d="M11.0051 28.6006C9.99973 25.6199 9.99973 22.3922 11.0051 19.4115V13.2849H3.03298C-0.371021 20.0138 -0.371021 28.0016 3.03298 34.7272L11.0051 28.6006Z"/>
          <path fill="#EA4335" d="M24.48 9.49932C27.9016 9.44641 31.2086 10.7339 33.6866 13.0973L40.5387 6.24523C36.2 2.17101 30.4414 -0.068932 24.48 0.00161733C15.4055 0.00161733 7.10718 5.14323 3.03296 13.2849L11.005 19.4115C12.901 13.7235 18.2187 9.49932 24.48 9.49932Z"/>
        </svg>
        <span style="font-weight:500;font-size:.92rem;letter-spacing:.01em;font-family:'Google Sans','Roboto',Arial,sans-serif">Pay</span>
      </button>

    </div><!-- /express-row -->

    <div class="or-divider"><span>Or pay by card</span></div>

    <div class="field">
      <label>Card number</label>
      <div id="card-number" class="stripe-el"></div>
    </div>
    <div class="field-row">
      <div>
        <label>Expiry date</label>
        <div id="card-expiry" class="stripe-el"></div>
      </div>
      <div>
        <label>CVC</label>
        <div id="card-cvc" class="stripe-el"></div>
      </div>
    </div>

    <div id="card-errors" class="error-msg" role="alert" aria-live="assertive"></div>

    <button type="button" id="pay-btn" class="btn-pay">Pay</button>

    <div class="secure-note">
      🔒 Secure payment &middot; Your information is safe with us
    </div>

  </div><!-- /pay-card -->

<script src="https://js.stripe.com/v3/"></script>
<script src="https://pay.google.com/gp/p/js/pay.js"></script>
<script>
(function () {
  'use strict';

  var BASE_URL = <?= $baseJson ?>;
  var PUBLISHABLE_KEY = <?= $jsPublishableKey ?>;

  /* ── State ──────────────────────────────────────────────── */
  var gOrderId      = '';
  var gClientSecret = '';
  var gAmount       = 0;
  var gCurrency     = 'usd';
  var gCourseTitle  = '';
  var gCourseId     = 0;
  var gFinalDisplay = '0.00';
  var gIsPreview    = false;

  var elLoading       = document.getElementById('state-loading');
  var elError         = document.getElementById('state-error');
  var elPreviewBanner = document.getElementById('state-preview-banner');
  var elPayCard       = document.getElementById('pay-card');
  var elPayBtn        = document.getElementById('pay-btn');
  var elErrBox        = document.getElementById('card-errors');

  /* ── Read order_id from URL ─────────────────────────────── */
  var params    = new URLSearchParams(window.location.search);
  var rawOrderId = (params.get('order_id') || '').trim();
  var UUID_RE   = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

  if (!rawOrderId || !UUID_RE.test(rawOrderId)) {
    gIsPreview = true;
    gCourseTitle = 'Course Title';
    gFinalDisplay = '0.00';
    gAmount = 0;
    gCurrency = 'usd';
    gCourseId = 0;
    showPreview();
  } else {
    gOrderId = rawOrderId;
    loadPaymentIntent(rawOrderId);
  }

  /* ── Fetch PaymentIntent from backend ───────────────────── */
  function loadPaymentIntent(orderId) {
    fetch(BASE_URL + '/api/payment-intent.php?order=' + encodeURIComponent(orderId), {
      method: 'GET',
      credentials: 'include'
    })
    .then(function (r) {
      return r.json();
    })
    .then(function (data) {
      if (!data) return;
      if (!data.success) {
        showError(data.message || 'Failed to load payment details.');
        return;
      }
      gClientSecret = data.client_secret;
      gAmount       = data.amount;
      gCurrency     = data.currency;
      gCourseTitle  = data.course_title;
      gCourseId     = data.course_id;
      gFinalDisplay = data.final_display;

      document.title = 'Checkout \u2013 ' + data.course_title;
      elPayBtn.textContent = 'Pay';

      mountStripe(data.publishable_key);
    })
    .catch(function () {
      showError('Network error loading payment details. Please refresh and try again.');
    });
  }

  /* ── Mount Stripe elements ───────────────────────────────── */
  function mountStripe(pk) {
    elLoading.style.display = 'none';
    elPayCard.style.display = 'block';

    var stripe   = Stripe(pk);
    var elements = stripe.elements();
    var isTestMode = pk.indexOf('pk_test_') === 0;

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

    if (gIsPreview) {
      cardNumber.update({ disabled: true });
      cardExpiry.update({ disabled: true });
      cardCvc.update({ disabled: true });
      elPayBtn.disabled = true;
      elPayBtn.style.opacity = '0.5';
      elPayBtn.style.cursor = 'not-allowed';
    }

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

    /* ── Apple Pay via Stripe Payment Request API ────────── */
    var pr = stripe.paymentRequest({
      country:  'US',
      currency: gCurrency,
      total:    { label: gCourseTitle || 'Course', amount: Math.max(gAmount, 100) },
      requestPayerName:  false,
      requestPayerEmail: false,
      requestPayerPhone: false,
      disableWallets:    ['link', 'browserCard', 'googlePay']
    });

    var btnApay = document.getElementById('btn-apay');
    var prReadyPromise = pr.canMakePayment();

    if (btnApay) btnApay.addEventListener('click', function () {
      if (gIsPreview) return;
      prReadyPromise.then(function (result) {
        if (!result || !result.applePay) {
          showFieldError('Apple Pay is not available on this device. Please pay by card below.');
          return;
        }
        if (window.parent !== window) window.parent.postMessage({ type: 'express_pay_open' }, '*');
        pr.show();
      });
    });

    pr.on('paymentmethod', function (ev) {
      if (gIsPreview) { ev.complete('fail'); return; }
      setLoading(true);
      stripe.confirmCardPayment(gClientSecret, { payment_method: ev.paymentMethod.id }, { handleActions: false })
        .then(function (res) {
          if (res.error) {
            ev.complete('fail');
            if (window.parent !== window) window.parent.postMessage({ type: 'express_pay_close' }, '*');
            showFieldError(res.error.message || 'Payment failed.');
            return;
          }
          ev.complete('success');
          if (window.parent !== window) window.parent.postMessage({ type: 'express_pay_close' }, '*');
          completeEnrollment(res.paymentIntent.id);
        })
        .catch(function () {
          ev.complete('fail');
          if (window.parent !== window) window.parent.postMessage({ type: 'express_pay_close' }, '*');
          showFieldError('Payment failed. Please try again.');
        });
    });

    /* ── Google Pay via Google Pay JS API ─────────────────── */
    var btnGpay = document.getElementById('btn-gpay');
    var gpayClient = new google.payments.api.PaymentsClient({
      environment: isTestMode ? 'TEST' : 'PRODUCTION'
    });

    var baseCardMethod = {
      type: 'CARD',
      parameters: {
        allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
        allowedCardNetworks: ['VISA', 'MASTERCARD', 'AMEX', 'DISCOVER']
      }
    };

    var tokenizedCardMethod = {
      type: 'CARD',
      parameters: baseCardMethod.parameters,
      tokenizationSpecification: {
        type: 'PAYMENT_GATEWAY',
        parameters: {
          gateway: 'stripe',
          'stripe:version': '2020-08-27',
          'stripe:publishableKey': pk
        }
      }
    };

    if (btnGpay) btnGpay.addEventListener('click', function () {
      if (gIsPreview) return;
      setLoading(true);
      if (window.parent !== window) window.parent.postMessage({ type: 'express_pay_open' }, '*');

      var priceStr = (Math.max(gAmount, 100) / 100).toFixed(2);

      gpayClient.loadPaymentData({
        apiVersion: 2,
        apiVersionMinor: 0,
        allowedPaymentMethods: [tokenizedCardMethod],
        transactionInfo: {
          totalPriceStatus: 'FINAL',
          totalPrice: priceStr,
          currencyCode: gCurrency.toUpperCase(),
          countryCode: 'US'
        }
      }).then(function (paymentData) {
        var tokenData = JSON.parse(paymentData.paymentMethodData.tokenizationData.token);
        return stripe.confirmCardPayment(gClientSecret, {
          payment_method: { card: { token: tokenData.id } }
        });
      }).then(function (result) {
        if (window.parent !== window) window.parent.postMessage({ type: 'express_pay_close' }, '*');
        if (result.error) {
          showFieldError(result.error.message || 'Payment failed.');
          return;
        }
        if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
          completeEnrollment(result.paymentIntent.id);
        }
      }).catch(function (err) {
        if (window.parent !== window) window.parent.postMessage({ type: 'express_pay_close' }, '*');
        if (err.statusCode === 'CANCELED') {
          setLoading(false);
          return;
        }
        showFieldError('Google Pay failed. Please try card payment below.');
      });
    });

    /* ── Card button ─────────────────────────────────────── */
    elPayBtn.addEventListener('click', function () {
      if (gIsPreview) return;
      elErrBox.textContent = '';
      setLoading(true);
      stripe.confirmCardPayment(gClientSecret, {
        payment_method: { card: cardNumber }
      }).then(function (result) {
        if (result.error) { showFieldError(result.error.message || 'Payment failed.'); return; }
        if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
          completeEnrollment(result.paymentIntent.id);
        }
      });
    });

    /* ── Helpers ─────────────────────────────────────────── */
    function showFieldError(msg) {
      elErrBox.textContent = msg;
      setLoading(false);
    }

    function setLoading(on) {
      elPayBtn.disabled = on;
      elPayBtn.innerHTML = on
        ? '<span class="spinner"></span>Processing\u2026'
        : 'Pay';
    }
  }

  /* ── Complete enrollment (called after payment succeeds) ── */
  function completeEnrollment(piId) {
    fetch(BASE_URL + '/api/complete-enrollment.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        payment_intent_id: piId,
        order_id:          gOrderId,
        course_id:         gCourseId
      })
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.success) {
        if (window.parent !== window) {
          window.parent.postMessage({
            type:         'checkout_complete',
            course_id:    gCourseId,
            order_id:     gOrderId,
            redirect_url: data.redirect_url
          }, '*');
        } else {
          window.location.href = data.redirect_url;
        }
      } else {
        document.getElementById('card-errors').textContent =
          data.message || 'Enrollment failed. Please contact support.';
        elPayBtn.disabled = false;
        elPayBtn.textContent = 'Pay';
      }
    });
  }

  /* ── Show preview mode ───────────────────────────────────── */
  function showPreview() {
    elLoading.style.display = 'none';
    elPreviewBanner.style.display = 'none';
    elPayCard.style.display = 'block';

    elPayBtn.textContent = 'Pay';
    document.title = 'Checkout';

    mountStripe(PUBLISHABLE_KEY);
  }

  /* ── Show fatal error ────────────────────────────────────── */
  function showError(msg) {
    elLoading.style.display = 'none';
    elError.style.display   = 'flex';
    elError.innerHTML =
      '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' +
      '<span>' + escHtml(msg) + '</span>';
  }

  function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

})();
</script>
</body>
</html>