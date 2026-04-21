<?php

if (!defined('BASE')) define('BASE', '');

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/courses-repo.php';
require_once dirname(__DIR__) . '/includes/purchases-repo.php';
require_once dirname(__DIR__) . '/includes/stripe-api.php';
require_once dirname(__DIR__) . '/includes/stripe-config.php';
require_once dirname(__DIR__) . '/includes/checkout-orders-repo.php';

// — 1. Resolve order_id
$rawOrderId = trim((string) ($_GET['order_id'] ?? ''));

if ($rawOrderId === '' || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $rawOrderId)) {
    header('Location: ' . BASE . '/courses.php');
    exit;
}

// — 2. Auth
$currentUser = auth_current_user();
if (!$currentUser) {
    header('Location: ' . BASE . '/login.php?redirect=' . urlencode(BASE . '/checkout/?order_id=' . $rawOrderId));
    exit;
}
$clientId = (int) $currentUser['id'];

// — 3. Load order
ensure_checkout_orders_table($mysqli);
$order = get_checkout_order($mysqli, $rawOrderId);

if (!$order) {
    header('Location: ' . BASE . '/courses.php?checkout_expired=1');
    exit;
}

// — 4. Security: order must belong to this user
if ((int) $order['client_id'] !== $clientId) {
    header('Location: ' . BASE . '/courses.php');
    exit;
}

$courseId         = (int)    $order['course_id'];
$originalPrice   = (float)  $order['original_amount'];
$finalPrice      = (float)  $order['final_amount'];
$couponCode      = (string) ($order['coupon_code'] ?? '');
$discountPercent = (float)  $order['discount_percent'];
$priceCents      = (int)    round($finalPrice * 100);

// — 5. Already enrolled?
ensure_purchases_table($mysqli);
if (has_user_enrolled_course($mysqli, $clientId, $courseId)) {
    header('Location: ' . BASE . '/course-player.php?course=' . $courseId);
    exit;
}

// — 6. Load course
$course = find_course_by_id($mysqli, $courseId);
if (!$course) {
    header('Location: ' . BASE . '/courses.php');
    exit;
}

// — 7. Get or create PaymentIntent
$stripeError = null;
if (!empty($order['payment_intent_id']) && !empty($order['payment_intent_secret'])) {
    $clientSecret = (string) $order['payment_intent_secret'];
    $piId         = (string) $order['payment_intent_id'];
} else {
    $piParams = [
        'amount'                     => (string) $priceCents,
        'currency'                   => 'usd',
        'payment_method_types[]'     => 'card',
        'metadata[order_id]'         => $rawOrderId,
        'metadata[course_id]'        => (string) $courseId,
        'metadata[client_id]'        => (string) $clientId,
        'metadata[coupon_code]'      => $couponCode,
        'metadata[discount_percent]' => (string) $discountPercent,
    ];

    try {
        $intent = stripe_create_payment_intent($piParams);
    } catch (RuntimeException $e) {
        $stripeError = $e->getMessage();
        error_log('checkout/index: PaymentIntent error: ' . $stripeError);
    }

    if (!$stripeError) {
        $clientSecret = (string) $intent['client_secret'];
        $piId         = (string) $intent['id'];
        attach_payment_intent_to_order($mysqli, $rawOrderId, $piId, $clientSecret);
    }
}

// — 8. Template variables
$courseTitle     = htmlspecialchars((string) $course['title'],     ENT_QUOTES | ENT_HTML5, 'UTF-8');
$courseSubtitle  = htmlspecialchars((string) ($course['subtitle'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$courseDesc      = htmlspecialchars((string) ($course['description'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$courseThumb     = !empty($course['image_url']) ? htmlspecialchars((string) $course['image_url'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
$courseLevel     = htmlspecialchars((string) ($course['level'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$courseDuration  = htmlspecialchars((string) ($course['duration'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$courseLessons   = (int) ($course['lessons'] ?? 0);
$courseStudents   = (int) ($course['students'] ?? 0);
$courseRating     = (float) ($course['rating'] ?? 0);
$courseReviews    = (int) ($course['reviews'] ?? 0);
$courseInstructor = htmlspecialchars((string) ($course['instructor'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$courseInstructorTitle = htmlspecialchars((string) ($course['instructor_title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$courseOutcomes   = $course['outcomes'] ?? [];
$originalDisplay = number_format($originalPrice, 2);
$finalDisplay    = number_format($finalPrice, 2);
$savingsDisplay  = $discountPercent > 0 ? number_format($originalPrice - $finalPrice, 2) : '';

$jsBaseUrl   = json_encode(BASE);
$iframeSrc   = BASE . '/checkout/cn?order_id=' . urlencode($rawOrderId);

// Pre-fill user info
$userFullName = htmlspecialchars(trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')), ENT_QUOTES|ENT_HTML5, 'UTF-8');
$userEmail    = htmlspecialchars((string)($currentUser['email'] ?? ''), ENT_QUOTES|ENT_HTML5, 'UTF-8');
$userPhone    = htmlspecialchars((string)($currentUser['phone'] ?? ''), ENT_QUOTES|ENT_HTML5, 'UTF-8');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Checkout &ndash; <?= $courseTitle ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --indigo:       #4f46e5;
      --indigo-light: #6366f1;
      --radius:       14px;
      --border:       #e5e7eb;
      --text:         #111827;
      --muted:        #6b7280;
      --bg-left:      #f8f7ff;
      --bg-input:     #f9fafb;
      --green:        #10b981;
      --gold:         #f59e0b;
    }

    html, body { height: 100%; }
    body {
      font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
      background: #fff;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── Two-column shell ─────────────────────────── */
    .checkout-shell {
      display: grid;
      grid-template-columns: 1fr 1fr;
      min-height: 100vh;
      transition: filter .3s ease;
    }
    .checkout-shell.blurred {
      filter: blur(6px);
      pointer-events: none;
      user-select: none;
    }

    /* ── LEFT: order summary ────────────────────────── */
    .summary-col {
      background: var(--bg-left);
      padding: 48px 48px 48px 56px;
      display: flex;
      flex-direction: column;
      overflow-y: auto;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 36px;
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
      margin-bottom: 20px;
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
      margin-bottom: 20px;
    }
    .course-thumb-placeholder svg { width: 40px; height: 40px; color: var(--indigo); opacity: .5; }

    .course-label {
      font-size: .7rem;
      font-weight: 700;
      letter-spacing: .1em;
      text-transform: uppercase;
      color: var(--indigo);
      margin-bottom: 6px;
    }
    .course-title {
      font-size: 1.35rem;
      font-weight: 800;
      color: var(--text);
      line-height: 1.3;
      margin-bottom: 6px;
      letter-spacing: -.02em;
    }
    .course-subtitle {
      font-size: .9rem;
      color: var(--muted);
      line-height: 1.45;
      margin-bottom: 16px;
    }
    .course-desc {
      font-size: .84rem;
      color: #374151;
      line-height: 1.6;
      margin-bottom: 20px;
    }

    /* Meta pills */
    .course-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 20px;
    }
    .meta-pill {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 99px;
      padding: 5px 12px;
      font-size: .74rem;
      font-weight: 600;
      color: #374151;
    }
    .meta-pill svg { flex-shrink: 0; color: var(--indigo); }

    /* Outcomes */
    .outcomes-box {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 18px 20px;
      margin-bottom: 20px;
    }
    .outcomes-heading {
      font-size: .82rem;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 12px;
    }
    .outcomes-list {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .outcomes-list li {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      font-size: .82rem;
      color: #374151;
      line-height: 1.4;
    }
    .outcomes-list li svg {
      flex-shrink: 0;
      margin-top: 2px;
      color: var(--green);
    }

    /* Instructor */
    .instructor-row {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 18px;
    }
    .instructor-avatar {
      width: 36px; height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--indigo), var(--indigo-light));
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: .82rem;
      font-weight: 700;
    }
    .instructor-info { line-height: 1.3; }
    .instructor-name { font-size: .82rem; font-weight: 700; color: var(--text); }
    .instructor-title-text { font-size: .72rem; color: var(--muted); }

    /* Rating */
    .rating-row {
      display: flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 20px;
      font-size: .82rem;
      color: #374151;
    }
    .star { color: var(--gold); }

    /* Price box */
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
      font-size: .88rem;
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
    }

    .trust-row {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 18px;
      color: var(--muted);
      font-size: .78rem;
      font-weight: 500;
    }
    .trust-row svg { flex-shrink: 0; color: var(--green); }

    /* ── RIGHT: billing form ────────────────────────── */
    .billing-col {
      padding: 56px 56px 56px 48px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .billing-heading {
      font-size: 1.4rem;
      font-weight: 800;
      color: var(--text);
      letter-spacing: -.025em;
      margin-bottom: 4px;
    }
    .billing-sub {
      font-size: .86rem;
      color: var(--muted);
      margin-bottom: 28px;
    }

    .field { margin-bottom: 16px; }

    .field label {
      display: block;
      font-size: .78rem;
      font-weight: 600;
      color: var(--text);
      margin-bottom: 6px;
    }

    .inp {
      width: 100%;
      padding: 12px 14px;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      background: var(--bg-input);
      font-family: inherit;
      font-size: .9rem;
      color: var(--text);
      outline: none;
      transition: border-color .15s, box-shadow .15s;
    }
    .inp::placeholder { color: #c4c8d0; }
    .inp:focus {
      border-color: var(--indigo);
      box-shadow: 0 0 0 3px rgba(79,70,229,.1);
      background: #fff;
    }
    .inp.invalid {
      border-color: #ef4444;
      box-shadow: 0 0 0 3px rgba(239,68,68,.08);
    }

    /* Country dropdown */
    .country-wrapper { position: relative; }
    .country-display {
      width: 100%;
      padding: 12px 14px;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      background: var(--bg-input);
      font-family: inherit;
      font-size: .9rem;
      color: var(--text);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: space-between;
      transition: border-color .15s, box-shadow .15s;
    }
    .country-display.placeholder { color: #c4c8d0; }
    .country-display:focus, .country-wrapper.open .country-display {
      border-color: var(--indigo);
      box-shadow: 0 0 0 3px rgba(79,70,229,.1);
      background: #fff;
    }
    .country-display.invalid {
      border-color: #ef4444;
      box-shadow: 0 0 0 3px rgba(239,68,68,.08);
    }
    .country-display svg { flex-shrink: 0; color: var(--muted); transition: transform .2s; }
    .country-wrapper.open .country-display svg { transform: rotate(180deg); }
    .country-dropdown {
      position: absolute;
      top: calc(100% + 4px);
      left: 0; right: 0;
      background: #fff;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      box-shadow: 0 12px 32px rgba(0,0,0,.12);
      z-index: 50;
      max-height: 260px;
      display: flex;
      flex-direction: column;
      opacity: 0;
      pointer-events: none;
      transform: translateY(-4px);
      transition: opacity .15s, transform .15s;
    }
    .country-wrapper.open .country-dropdown {
      opacity: 1;
      pointer-events: all;
      transform: translateY(0);
    }
    .country-search {
      padding: 10px 12px;
      border: none;
      border-bottom: 1px solid var(--border);
      font-family: inherit;
      font-size: .84rem;
      outline: none;
      border-radius: 10px 10px 0 0;
    }
    .country-search::placeholder { color: #c4c8d0; }
    .country-list {
      overflow-y: auto;
      flex: 1;
      list-style: none;
    }
    .country-list li {
      padding: 9px 14px;
      font-size: .84rem;
      color: var(--text);
      cursor: pointer;
      transition: background .1s;
    }
    .country-list li:hover, .country-list li.highlighted { background: #f3f4f6; }
    .country-list li.hidden { display: none; }

    /* Address autocomplete */
    .address-wrapper { position: relative; }
    .address-suggestions {
      position: absolute;
      top: calc(100% + 4px);
      left: 0; right: 0;
      background: #fff;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      box-shadow: 0 12px 32px rgba(0,0,0,.12);
      z-index: 50;
      max-height: 220px;
      overflow-y: auto;
      list-style: none;
      opacity: 0;
      pointer-events: none;
      transform: translateY(-4px);
      transition: opacity .15s, transform .15s;
    }
    .address-suggestions.open {
      opacity: 1;
      pointer-events: all;
      transform: translateY(0);
    }
    .address-suggestions li {
      padding: 10px 14px;
      font-size: .84rem;
      color: var(--text);
      cursor: pointer;
      transition: background .1s;
      border-bottom: 1px solid #f3f4f6;
    }
    .address-suggestions li:last-child { border-bottom: none; }
    .address-suggestions li:hover { background: #f3f4f6; }
    .address-suggestions li small { display: block; color: var(--muted); font-size: .72rem; margin-top: 2px; }

    .form-error {
      color: #dc2626;
      font-size: .8rem;
      font-weight: 500;
      margin-bottom: 10px;
      min-height: 1.1rem;
    }

    .btn-continue {
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
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-top: 4px;
    }
    .btn-continue:hover { opacity: .92; transform: translateY(-1px); }
    .btn-continue:active { transform: translateY(0); }

    .secure-row {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      margin-top: 14px;
      font-size: .75rem;
      color: #9ca3af;
      font-weight: 500;
    }
    .secure-row svg { flex-shrink: 0; }

    /* ── Payment Modal Overlay ────────────────────────── */
    .modal-overlay {
      position: fixed;
      inset: 0;
      z-index: 1000;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      opacity: 0;
      pointer-events: none;
      transition: opacity .25s ease;
      background: rgba(0, 0, 0, .45);
    }
    .modal-overlay.open {
      opacity: 1;
      pointer-events: all;
    }

    .modal-box {
      background: #fff;
      border-radius: 20px;
      width: 100%;
      max-width: 500px;
      padding: 32px 32px 28px;
      box-shadow: 0 24px 60px rgba(0,0,0,.18), 0 0 0 1px rgba(0,0,0,.04);
      transform: translateY(20px) scale(.97);
      transition: transform .3s cubic-bezier(.34,1.4,.64,1), opacity .25s ease;
      opacity: 0;
      position: relative;
      display: flex;
      flex-direction: column;
    }
    .modal-overlay.open .modal-box {
      transform: translateY(0) scale(1);
      opacity: 1;
    }

    .modal-close {
      position: absolute;
      top: 14px; right: 14px;
      width: 34px; height: 34px;
      border: none;
      background: #f3f4f6;
      border-radius: 50%;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #6b7280;
      transition: background .15s, color .15s;
      z-index: 2;
    }
    .modal-close:hover { background: #e5e7eb; color: #111827; }

    .modal-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 20px;
    }
    .modal-header-icon {
      width: 40px; height: 40px;
      background: linear-gradient(135deg, #f0fdf4, #dcfce7);
      border: 1.5px solid #bbf7d0;
      border-radius: 11px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .modal-header-icon svg { color: #16a34a; }
    .modal-title { font-size: 1.1rem; font-weight: 800; color: var(--text); letter-spacing: -.02em; }
    .modal-subtitle { font-size: .76rem; color: var(--muted); font-weight: 500; margin-top: 2px; }

    .modal-iframe {
      width: 100%;
      min-height: 480px;
      border: none;
      border-radius: 12px;
      flex: 1;
    }

    /* ── Responsive ──────────────────────────────────── */
    @media (max-width: 860px) {
      .checkout-shell { grid-template-columns: 1fr; }
      .summary-col  { padding: 36px 24px 28px; }
      .billing-col  { padding: 32px 24px 48px; }
      .course-title { font-size: 1.15rem; }
      .modal-box { max-width: 95vw; padding: 24px 20px 20px; }
    }
    @media (max-width: 480px) {
      .summary-col  { padding: 24px 16px; }
      .billing-col  { padding: 24px 16px 40px; }
      .modal-box { padding: 20px 14px 16px; }
      .modal-iframe { min-height: 420px; }
    }
  </style>
</head>
<body>

<div class="checkout-shell" id="checkout-shell">

  <!-- ── LEFT: Order summary ─────────────────────────────────── -->
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

    <?php if ($courseSubtitle): ?>
      <p class="course-subtitle"><?= $courseSubtitle ?></p>
    <?php endif; ?>

    <?php if ($courseDesc): ?>
      <p class="course-desc"><?= $courseDesc ?></p>
    <?php endif; ?>

    <!-- Meta pills -->
    <div class="course-meta">
      <?php if ($courseLevel): ?>
      <span class="meta-pill">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 20h4V10H2z"/><path d="M10 20h4V4h-4z"/><path d="M18 20h4v-8h-4z"/></svg>
        <?= $courseLevel ?>
      </span>
      <?php endif; ?>
      <?php if ($courseDuration): ?>
      <span class="meta-pill">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        <?= $courseDuration ?>
      </span>
      <?php endif; ?>
      <?php if ($courseLessons > 0): ?>
      <span class="meta-pill">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        <?= $courseLessons ?> lessons
      </span>
      <?php endif; ?>
      <?php if ($courseStudents > 0): ?>
      <span class="meta-pill">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <?= number_format($courseStudents) ?> students
      </span>
      <?php endif; ?>
    </div>

    <?php if ($courseInstructor): ?>
    <div class="instructor-row">
      <div class="instructor-avatar"><?= strtoupper(mb_substr($courseInstructor, 0, 1)) ?></div>
      <div class="instructor-info">
        <div class="instructor-name"><?= $courseInstructor ?></div>
        <?php if ($courseInstructorTitle): ?>
          <div class="instructor-title-text"><?= $courseInstructorTitle ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($courseRating > 0): ?>
    <div class="rating-row">
      <span class="star">&#9733;</span>
      <strong><?= number_format($courseRating, 1) ?></strong>
      <?php if ($courseReviews > 0): ?>
        <span style="color:var(--muted)">(<?= number_format($courseReviews) ?> reviews)</span>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($courseOutcomes)): ?>
    <div class="outcomes-box">
      <p class="outcomes-heading">What you&rsquo;ll learn</p>
      <ul class="outcomes-list">
        <?php foreach (array_slice($courseOutcomes, 0, 6) as $outcome): ?>
        <li>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
          <?= htmlspecialchars((string)$outcome, ENT_QUOTES|ENT_HTML5, 'UTF-8') ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <div class="price-box">
      <?php if ($discountPercent > 0): ?>
      <div class="price-row">
        <span>Original price</span>
        <span class="strike">$<?= $originalDisplay ?></span>
      </div>
      <div class="price-row">
        <span>Discount (<?= (int)$discountPercent ?>% off)</span>
        <span class="badge-discount">&minus;$<?= $savingsDisplay ?></span>
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
      30-day money-back guarantee &nbsp;&middot;&nbsp; Lifetime access
    </div>

  </div><!-- /summary-col -->

  <!-- ── RIGHT: Billing form ─────────────────────────────────── -->
  <div class="billing-col">

    <h1 class="billing-heading">Customer Information</h1>
    <p class="billing-sub">Fill in your details to proceed to payment.</p>

    <div class="field">
      <label for="bf-name">Full Name <span style="color:#ef4444">*</span></label>
      <input class="inp" id="bf-name" type="text" placeholder="Jane Smith" value="<?= $userFullName ?>" autocomplete="name">
    </div>

    <div class="field">
      <label for="bf-email">Email <span style="color:#ef4444">*</span></label>
      <input class="inp" id="bf-email" type="email" placeholder="jane@example.com" value="<?= $userEmail ?>" autocomplete="email">
    </div>

    <div class="field">
      <label for="bf-phone">Phone Number <span style="color:#ef4444">*</span></label>
      <input class="inp" id="bf-phone" type="tel" placeholder="+1 555 000 0000" value="<?= $userPhone ?>" autocomplete="tel">
    </div>

    <div class="field">
      <label for="bf-address">Address <span style="color:#ef4444">*</span></label>
      <div class="address-wrapper">
        <input class="inp" id="bf-address" type="text" placeholder="Start typing your address..." autocomplete="off">
        <ul class="address-suggestions" id="address-suggestions"></ul>
      </div>
      <small style="color:var(--muted);font-size:.72rem;margin-top:4px;display:block">Type to search &mdash; selecting an address will auto-fill city, postal code &amp; country.</small>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="field">
        <label for="bf-city">City <span style="color:#ef4444">*</span></label>
        <input class="inp" id="bf-city" type="text" placeholder="New York" autocomplete="address-level2">
      </div>
      <div class="field">
        <label for="bf-postal">Postal / ZIP Code <span style="color:#ef4444">*</span></label>
        <input class="inp" id="bf-postal" type="text" placeholder="10001" autocomplete="postal-code">
      </div>
    </div>

    <div class="field">
      <label for="bf-country">Country <span style="color:#ef4444">*</span></label>
      <div class="country-wrapper" id="country-wrapper">
        <div class="country-display placeholder" id="country-display" tabindex="0">
          <span id="country-display-text">Select a country</span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </div>
        <div class="country-dropdown">
          <input type="text" class="country-search" id="country-search" placeholder="Search countries...">
          <ul class="country-list" id="country-list"></ul>
        </div>
        <input type="hidden" id="bf-country" value="">
      </div>
    </div>

    <div class="form-error" id="billing-error"></div>

    <?php if ($stripeError): ?>
    <div style="background:#fef2f2;border:1.5px solid #fca5a5;border-radius:10px;padding:14px 16px;margin-bottom:16px;color:#991b1b;font-size:.84rem;line-height:1.55">
        <strong>Payment setup failed.</strong> Could not initialise Stripe. Please check that your <code>STRIPE_SECRET_KEY</code> is configured correctly.<br>
        <span style="font-size:.78rem;opacity:.8;margin-top:4px;display:block"><?= htmlspecialchars($stripeError, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
    </div>
    <?php endif; ?>

    <button type="button" class="btn-continue" id="btn-continue" <?= $stripeError ? 'disabled style="opacity:.5;cursor:not-allowed"' : '' ?>>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>
      </svg>
      Continue to Payment &mdash; $<?= $finalDisplay ?>
    </button>

    <div class="secure-row">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
      </svg>
      Secure payment powered by Stripe
    </div>

  </div><!-- /billing-col -->

</div><!-- /checkout-shell -->

<!-- ── Payment Modal ──────────────────────────────────────────── -->
<div class="modal-overlay" id="payment-modal" role="dialog" aria-modal="true" aria-label="Complete payment">
  <div class="modal-box">
    <button class="modal-close" id="modal-close-btn" aria-label="Close payment">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
      </svg>
    </button>

    <div class="modal-header">
      <div>
        <div class="modal-title">Complete Payment</div>
      </div>
    </div>

    <iframe
      id="modal-iframe"
      class="modal-iframe"
      allow="payment"
      loading="lazy"
      title="Payment form"
    ></iframe>
  </div>
</div>

<script>
(function () {
  'use strict';

  var BASE_URL    = <?= $jsBaseUrl ?>;
  var IFRAME_SRC  = <?= json_encode($iframeSrc) ?>;
  var shell       = document.getElementById('checkout-shell');
  var overlay     = document.getElementById('payment-modal');
  var closeBtn    = document.getElementById('modal-close-btn');
  var iframe      = document.getElementById('modal-iframe');
  var continueBtn = document.getElementById('btn-continue');
  var errorEl     = document.getElementById('billing-error');

  var fields = {
    name:    document.getElementById('bf-name'),
    email:   document.getElementById('bf-email'),
    phone:   document.getElementById('bf-phone'),
    address: document.getElementById('bf-address'),
    city:    document.getElementById('bf-city'),
    postal:  document.getElementById('bf-postal'),
    country: document.getElementById('bf-country')
  };

  // ── Country dropdown ──────────────────────────────
  var COUNTRIES = [
    "Afghanistan","Albania","Algeria","Andorra","Angola","Antigua and Barbuda","Argentina","Armenia","Australia","Austria",
    "Azerbaijan","Bahamas","Bahrain","Bangladesh","Barbados","Belarus","Belgium","Belize","Benin","Bhutan",
    "Bolivia","Bosnia and Herzegovina","Botswana","Brazil","Brunei","Bulgaria","Burkina Faso","Burundi","Cabo Verde","Cambodia",
    "Cameroon","Canada","Central African Republic","Chad","Chile","China","Colombia","Comoros","Congo (DRC)","Congo (Republic)",
    "Costa Rica","Croatia","Cuba","Cyprus","Czech Republic","Denmark","Djibouti","Dominica","Dominican Republic","Ecuador",
    "Egypt","El Salvador","Equatorial Guinea","Eritrea","Estonia","Eswatini","Ethiopia","Fiji","Finland","France",
    "Gabon","Gambia","Georgia","Germany","Ghana","Greece","Grenada","Guatemala","Guinea","Guinea-Bissau",
    "Guyana","Haiti","Honduras","Hungary","Iceland","India","Indonesia","Iran","Iraq","Ireland",
    "Israel","Italy","Ivory Coast","Jamaica","Japan","Jordan","Kazakhstan","Kenya","Kiribati","Kosovo",
    "Kuwait","Kyrgyzstan","Laos","Latvia","Lebanon","Lesotho","Liberia","Libya","Liechtenstein","Lithuania",
    "Luxembourg","Madagascar","Malawi","Malaysia","Maldives","Mali","Malta","Marshall Islands","Mauritania","Mauritius",
    "Mexico","Micronesia","Moldova","Monaco","Mongolia","Montenegro","Morocco","Mozambique","Myanmar","Namibia",
    "Nauru","Nepal","Netherlands","New Zealand","Nicaragua","Niger","Nigeria","North Korea","North Macedonia","Norway",
    "Oman","Pakistan","Palau","Palestine","Panama","Papua New Guinea","Paraguay","Peru","Philippines","Poland",
    "Portugal","Qatar","Romania","Russia","Rwanda","Saint Kitts and Nevis","Saint Lucia","Saint Vincent and the Grenadines","Samoa","San Marino",
    "Sao Tome and Principe","Saudi Arabia","Senegal","Serbia","Seychelles","Sierra Leone","Singapore","Slovakia","Slovenia","Solomon Islands",
    "Somalia","South Africa","South Korea","South Sudan","Spain","Sri Lanka","Sudan","Suriname","Sweden","Switzerland",
    "Syria","Taiwan","Tajikistan","Tanzania","Thailand","Timor-Leste","Togo","Tonga","Trinidad and Tobago","Tunisia",
    "Turkey","Turkmenistan","Tuvalu","Uganda","Ukraine","United Arab Emirates","United Kingdom","United States","Uruguay","Uzbekistan",
    "Vanuatu","Vatican City","Venezuela","Vietnam","Yemen","Zambia","Zimbabwe"
  ];

  var countryWrapper  = document.getElementById('country-wrapper');
  var countryDisplay  = document.getElementById('country-display');
  var countryText     = document.getElementById('country-display-text');
  var countrySearch   = document.getElementById('country-search');
  var countryList     = document.getElementById('country-list');
  var countryHidden   = document.getElementById('bf-country');

  // Build list
  COUNTRIES.forEach(function (name) {
    var li = document.createElement('li');
    li.textContent = name;
    li.setAttribute('data-value', name);
    li.addEventListener('click', function () {
      selectCountry(name);
    });
    countryList.appendChild(li);
  });

  function selectCountry(name) {
    countryHidden.value = name;
    countryText.textContent = name;
    countryDisplay.classList.remove('placeholder', 'invalid');
    closeCountryDropdown();
  }

  function openCountryDropdown() {
    countryWrapper.classList.add('open');
    countrySearch.value = '';
    filterCountries('');
    setTimeout(function () { countrySearch.focus(); }, 50);
  }

  function closeCountryDropdown() {
    countryWrapper.classList.remove('open');
  }

  function filterCountries(q) {
    var lower = q.toLowerCase();
    var items = countryList.querySelectorAll('li');
    items.forEach(function (li) {
      if (li.textContent.toLowerCase().indexOf(lower) === -1) {
        li.classList.add('hidden');
      } else {
        li.classList.remove('hidden');
      }
    });
  }

  countryDisplay.addEventListener('click', function () {
    if (countryWrapper.classList.contains('open')) closeCountryDropdown();
    else openCountryDropdown();
  });
  countryDisplay.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openCountryDropdown(); }
  });
  countrySearch.addEventListener('input', function () {
    filterCountries(this.value);
  });
  document.addEventListener('click', function (e) {
    if (!countryWrapper.contains(e.target)) closeCountryDropdown();
  });

  // ── Address Autocomplete (OpenStreetMap Nominatim - free) ──
  var addressInput = document.getElementById('bf-address');
  var suggestionsEl = document.getElementById('address-suggestions');
  var debounceTimer = null;

  function closeSuggestions() {
    suggestionsEl.classList.remove('open');
    suggestionsEl.innerHTML = '';
  }

  addressInput.addEventListener('input', function () {
    var query = this.value.trim();
    clearTimeout(debounceTimer);
    if (query.length < 3) { closeSuggestions(); return; }

    debounceTimer = setTimeout(function () {
      fetch('https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=5&q=' + encodeURIComponent(query), {
        headers: { 'Accept-Language': 'en' }
      })
      .then(function (r) { return r.json(); })
      .then(function (results) {
        suggestionsEl.innerHTML = '';
        if (!results.length) { closeSuggestions(); return; }

        results.forEach(function (item) {
          var li = document.createElement('li');
          var mainText = item.display_name.split(',').slice(0, 2).join(',');
          var subText = item.display_name.split(',').slice(2).join(',').trim();
          li.innerHTML = '<strong>' + escapeHtml(mainText) + '</strong>' + (subText ? '<small>' + escapeHtml(subText) + '</small>' : '');
          li.addEventListener('click', function () {
            fillFromNominatim(item);
            closeSuggestions();
          });
          suggestionsEl.appendChild(li);
        });
        suggestionsEl.classList.add('open');
      })
      .catch(function () { closeSuggestions(); });
    }, 350); // debounce 350ms (respects Nominatim 1req/s policy)
  });

  document.addEventListener('click', function (e) {
    if (!e.target.closest('.address-wrapper')) closeSuggestions();
  });

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function fillFromNominatim(item) {
    var addr = item.address || {};
    var street = (addr.house_number ? addr.house_number + ' ' : '') + (addr.road || addr.pedestrian || addr.street || '');
    var city = addr.city || addr.town || addr.village || addr.municipality || '';
    var postal = addr.postcode || '';
    var country = addr.country || '';

    if (street) {
      addressInput.value = street;
      addressInput.classList.remove('invalid');
    }
    if (city) {
      fields.city.value = city;
      fields.city.classList.remove('invalid');
    }
    if (postal) {
      fields.postal.value = postal;
      fields.postal.classList.remove('invalid');
    }
    if (country) {
      selectCountry(country);
    }
  }

  // ── Validation ──────────────────────────────────
  function validateForm() {
    errorEl.textContent = '';
    var missing = [];
    Object.keys(fields).forEach(function (k) {
      var el = fields[k];
      if (el.value.trim() === '') {
        // Special handling for country (hidden input)
        if (k === 'country') {
          countryDisplay.classList.add('invalid');
        } else {
          el.classList.add('invalid');
        }
        missing.push(k);
      } else {
        if (k === 'country') {
          countryDisplay.classList.remove('invalid');
        } else {
          el.classList.remove('invalid');
        }
      }
    });

    // Basic email check
    if (!missing.includes('email') && fields.email.value.trim()) {
      var emailVal = fields.email.value.trim();
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
        fields.email.classList.add('invalid');
        missing.push('email');
      }
    }

    if (missing.length) {
      errorEl.textContent = 'Please fill in all required fields correctly.';
      if (missing[0] === 'country') openCountryDropdown();
      else fields[missing[0]].focus();
      return false;
    }
    return true;
  }

  Object.keys(fields).forEach(function (k) {
    if (k === 'country') return; // handled by dropdown
    fields[k].addEventListener('input', function () {
      if (this.value.trim() !== '') this.classList.remove('invalid');
    });
  });

  // ── Modal open/close ──────────────────────────────
  function openModal() {
    // Load iframe src only when opening (lazy)
    if (!iframe.src || iframe.src === 'about:blank') {
      iframe.src = IFRAME_SRC;
    }
    overlay.classList.add('open');
    shell.classList.add('blurred');
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    overlay.classList.remove('open');
    shell.classList.remove('blurred');
    document.body.style.overflow = '';
  }

  continueBtn.addEventListener('click', function () {
    if (!validateForm()) return;
    openModal();
  });

  closeBtn.addEventListener('click', closeModal);
  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) closeModal();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && overlay.classList.contains('open')) closeModal();
  });

  // ── Listen for messages from cn.php iframe ─────────
  window.addEventListener('message', function (e) {
    if (!e.data || typeof e.data !== 'object') return;
    if (e.data.type === 'checkout_complete') {
      closeModal();
      window.location.href = e.data.redirect_url || (BASE_URL + '/my-courses');
    }
  });
})();
</script>
</body>
</html>
