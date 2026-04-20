<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Payment Successful</title>

  <!-- iframe-resizer: keep the iframe sized correctly after navigation -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/iframe-resizer/4.3.9/iframeResizer.contentWindow.min.js"
          integrity="sha512-fui6CpkZZtzPoKTH4+Dh62p2RQflM0L3+hBqxQBfPVBM6w8Vy0/9eXyR+TiD5GfZjRHKQBBhFJq0BCVPUPFQ=="
          crossorigin="anonymous"></script>

  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0;
      padding: 32px 16px;
      background: #f4f6fb;
      font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
      color: #1f2937;
    }
    .success-card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 24px rgba(0,0,0,.09);
      padding: 40px 28px;
      max-width: 480px;
      margin: 0 auto;
      text-align: center;
    }
    .check-circle {
      width: 64px; height: 64px;
      background: #dcfce7;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 20px;
    }
    h2 { font-size: 1.2rem; font-weight: 700; color: #111827; margin-bottom: 10px; }
    p  { font-size: .9rem; color: #6b7280; line-height: 1.6; margin: 0; }
    .pi-ref { margin-top: 16px; font-size: .75rem; color: #9ca3af; word-break: break-all; }
  </style>
</head>
<body>
<div class="success-card">
  <div class="check-circle">
    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <path d="M5 13l4 4L19 7" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </div>
  <h2>Payment Successful!</h2>
  <p>Thank you for your purchase. A confirmation email is on its way to you.</p>
  <?php
    $pi = htmlspecialchars(trim((string) ($_GET['pi'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($pi !== ''):
  ?>
  <p class="pi-ref">Reference: <?= $pi ?></p>
  <?php endif; ?>
</div>

<script>
(function () {
  // Tell the parent window (WordPress popup) the payment succeeded
  var msg = {
    type: 'payment_success',
    paymentIntentId: <?= json_encode($pi) ?>
  };
  try { window.parentIFrame.sendMessage(msg); } catch (_) {}
  window.parent.postMessage(msg, '*');
})();
</script>
</body>
</html>
