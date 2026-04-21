<?php

$page_title = 'Certificate of Completion';
$page_desc  = 'Your course completion certificate from NerdAcademy.';

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/courses-repo.php';
require_once __DIR__ . '/includes/purchases-repo.php';
require_once __DIR__ . '/includes/certificates-repo.php';

if (!defined('BASE')) define('BASE', '');

$user = auth_current_user();
if (!$user) {
    header('Location: ' . BASE . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$courseId = (int)($_GET['course_id'] ?? 0);
if ($courseId <= 0) {
    header('Location: ' . BASE . '/my-courses.php');
    exit;
}

$course   = find_course_by_id($mysqli, $courseId);
$enrolled = has_user_enrolled_course($mysqli, (int)$user['id'], $courseId);

if (!$course || !$enrolled) {
    header('Location: ' . BASE . '/my-courses.php');
    exit;
}

$progressMap = get_user_progress_map($mysqli, (int)$user['id']);
$progress    = (int)($progressMap[$courseId]['progress_percent'] ?? 0);

$certificate = null;
$issueError  = '';
if ($progress >= 100) {
    $certificate = get_or_issue_certificate($mysqli, (int)$user['id'], $courseId);
    if (!$certificate) {
        $issueError = 'Unable to issue certificate right now. Please try again.';
    }
}

$studentName  = htmlspecialchars((string)($user['full_name'] ?: $user['email']), ENT_QUOTES);
$courseTitle  = htmlspecialchars((string)$course['title'], ENT_QUOTES);
$certCode     = $certificate ? htmlspecialchars((string)$certificate['certificate_code'], ENT_QUOTES) : '';
$issuedDate   = $certificate ? date('F d, Y', strtotime((string)$certificate['issued_at'])) : '';
$jsStudentName = json_encode($studentName);
$jsCertCode    = json_encode($certCode);

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ── Page layout ─────────────────────────────────── */
.cert-page { padding: calc(var(--nav-h, 72px) + 2rem) 0 4rem; }
.cert-page .container { max-width: 1000px; }

/* ── Confetti canvas ─────────────────────────────── */
#cert-confetti {
  position: fixed;
  inset: 0;
  pointer-events: none;
  z-index: 9999;
}

/* ── Controls bar ────────────────────────────────── */
.cert-controls {
  display: flex;
  align-items: center;
  gap: .65rem;
  flex-wrap: wrap;
  margin-bottom: 2rem;
}
.cert-controls .section-tag { margin-bottom: 0; }
.cert-controls-right { margin-left: auto; display: flex; gap: .65rem; flex-wrap: wrap; }
.cert-btn {
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  padding: .55rem 1.1rem;
  border-radius: 10px;
  font-size: .875rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: opacity .15s, transform .1s;
  text-decoration: none;
}
.cert-btn:hover { opacity: .88; transform: translateY(-1px); }
.cert-btn:active { transform: translateY(0); }
.cert-btn--primary  { background: #6366f1; color: #fff; }
.cert-btn--green    { background: #16a34a; color: #fff; }
.cert-btn--outline  { background: transparent; color: var(--text-primary); border: 1.5px solid var(--border); }
.cert-btn--sm       { padding: .4rem .8rem; font-size: .82rem; }
.cert-btn svg       { flex-shrink: 0; }
#cert-dl-spinner    { display: none; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: cert-spin .6s linear infinite; }
@keyframes cert-spin { to { transform: rotate(360deg); } }

/* ── Locked / error state ────────────────────────── */
.cert-locked {
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 2.5rem;
  text-align: center;
}
.cert-locked-icon {
  width: 64px; height: 64px;
  border-radius: 50%;
  background: linear-gradient(135deg,#e0e7ff,#c7d2fe);
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 1.2rem;
}
.cert-locked h3 { margin: 0 0 .5rem; font-size: 1.3rem; }
.cert-locked p  { margin: 0 0 1.2rem; color: var(--text-muted); }
.cert-progress-bar { height: 10px; background: var(--border); border-radius: 999px; overflow: hidden; margin: .6rem 0 1.4rem; }
.cert-progress-fill { height: 100%; background: linear-gradient(90deg,#6366f1,#8b5cf6); border-radius: 999px; }

/* ── Certificate card wrapper (always light) ─────── */
#cert-scroll-wrap { overflow-x: auto; }

#cert-wrap {
  display: inline-block;
  min-width: 760px;
  width: 100%;
  background: #fffef7;
  border-radius: 4px;
  padding: 6px;
  box-shadow: 0 25px 80px rgba(0,0,0,.18), 0 4px 12px rgba(0,0,0,.08);
}

/* ── Certificate itself ──────────────────────────── */
#cert {
  font-family: 'Inter', 'Georgia', serif;
  background: #fffef7;
  color: #1a1a2e;
  position: relative;
  padding: 52px 64px 44px;
  border: 3px solid #b8922a;
  outline: 8px solid #f5e6c0;
  outline-offset: -18px;
  overflow: hidden;
  min-height: 540px;
  display: flex;
  flex-direction: column;
}

/* watermark dots */
#cert::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image:
    radial-gradient(circle, #b8922a22 1px, transparent 1px);
  background-size: 28px 28px;
  pointer-events: none;
}

/* corner ornament helper */
.cert-corner {
  position: absolute;
  width: 72px;
  height: 72px;
  pointer-events: none;
}
.cert-corner--tl { top: 22px; left: 22px; }
.cert-corner--tr { top: 22px; right: 22px; transform: scaleX(-1); }
.cert-corner--bl { bottom: 22px; left: 22px; transform: scaleY(-1); }
.cert-corner--br { bottom: 22px; right: 22px; transform: scale(-1,-1); }

/* top band */
.cert-band {
  background: linear-gradient(135deg,#6366f1 0%,#8b5cf6 50%,#6366f1 100%);
  margin: -52px -64px 0;
  padding: 18px 64px 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
  margin-bottom: 34px;
}
.cert-logo-mark svg path:first-child { fill: #fff; }
.cert-logo-mark svg path:last-child  { fill: #6366f1; }
.cert-logo-text {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 1.6rem;
  font-weight: 800;
  color: #fff;
  letter-spacing: .04em;
}
.cert-logo-text span { color: #c4b5fd; }

/* title row */
.cert-title-row {
  text-align: center;
  margin-bottom: 8px;
}
.cert-title-label {
  display: inline-block;
  font-size: .72rem;
  text-transform: uppercase;
  letter-spacing: .2em;
  color: #b8922a;
  border-top: 1px solid #b8922a;
  border-bottom: 1px solid #b8922a;
  padding: 4px 20px;
  margin-bottom: 28px;
  font-family: 'Space Grotesk', sans-serif;
}

/* body */
.cert-body {
  text-align: center;
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0;
}
.cert-presented-to {
  font-size: .85rem;
  color: #78716c;
  text-transform: uppercase;
  letter-spacing: .12em;
  margin-bottom: 10px;
}
.cert-student-name {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 2.6rem;
  font-weight: 700;
  color: #1a1a2e;
  line-height: 1.1;
  margin-bottom: 18px;
  position: relative;
}
.cert-student-name::after {
  content: '';
  display: block;
  width: 80px;
  height: 2px;
  background: linear-gradient(90deg,transparent,#b8922a,transparent);
  margin: 12px auto 0;
}
.cert-for-completing {
  font-size: .85rem;
  color: #78716c;
  text-transform: uppercase;
  letter-spacing: .12em;
  margin-bottom: 8px;
}
.cert-course-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 1.4rem;
  font-weight: 700;
  color: #1a1a2e;
  max-width: 500px;
  line-height: 1.3;
}

/* divider */
.cert-divider {
  width: 100%;
  height: 1px;
  background: linear-gradient(90deg,transparent,#d4b896,transparent);
  margin: 28px 0 20px;
}

/* footer row */
.cert-footer {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 1rem;
}
.cert-meta-item {
  text-align: center;
}
.cert-meta-label {
  font-size: .62rem;
  text-transform: uppercase;
  letter-spacing: .14em;
  color: #a8a29e;
  margin-bottom: 3px;
}
.cert-meta-value {
  font-size: .85rem;
  font-weight: 700;
  color: #1a1a2e;
}

/* seal */
.cert-seal {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}
.cert-seal-ring {
  width: 76px;
  height: 76px;
  border-radius: 50%;
  border: 3px solid #b8922a;
  outline: 2px dashed #d4b896;
  outline-offset: 3px;
  background: linear-gradient(145deg,#fffef7,#fef3c7);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 12px rgba(184,146,42,.25);
}
.cert-seal-star  { font-size: 1.1rem; line-height:1; }
.cert-seal-text  { font-size: .42rem; text-transform: uppercase; letter-spacing: .14em; color: #b8922a; font-weight: 700; text-align: center; line-height: 1.3; }

/* ── Print styles ────────────────────────────────── */
@media print {
  @page {
    size: A4 landscape;
    margin: 8mm;
  }

  /* Hide everything except the certificate */
  html, body { height: auto !important; overflow: visible !important; background: #fff !important; }
  body > *:not(.cert-print-root) { display: none !important; }

  /* The section that holds the cert becomes the only visible element */
  .cert-page {
    display: block !important;
    padding: 0 !important;
    margin: 0 !important;
  }
  .cert-page .container {
    max-width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
  }

  /* Hide controls and locked state */
  .cert-controls,
  .cert-locked,
  #cert-confetti { display: none !important; }

  /* Scroll wrapper fills the page */
  #cert-scroll-wrap {
    overflow: visible !important;
    width: 100% !important;
  }

  /* Remove chrome shadow */
  #cert-wrap {
    box-shadow: none !important;
    padding: 0 !important;
    width: 100% !important;
    min-width: 0 !important;
    display: block !important;
  }

  /* Certificate itself — fit page, preserve colors */
  #cert {
    min-height: 0 !important;
    width: 100% !important;
    page-break-inside: avoid;
    break-inside: avoid;
    outline: 6px solid #f5e6c0 !important;
    outline-offset: -14px !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    color-adjust: exact !important;
  }

  /* Force background colors to print */
  .cert-band {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}

@media (max-width: 640px) {
  #cert { padding: 36px 28px 32px; min-height: unset; }
  .cert-band { margin: -36px -28px 24px; padding: 14px 28px; }
  .cert-student-name { font-size: 1.8rem; }
  .cert-course-title  { font-size: 1.1rem; }
  .cert-seal-ring { width: 60px; height: 60px; }
}
</style>

<section class="cert-page section">
  <div class="container">

    <?php if ($progress < 100): ?>
    <!-- ── Locked ───────────────────────────── -->
    <div class="cert-locked">
      <div class="cert-locked-icon">
        <svg width="28" height="28" fill="none" stroke="#6366f1" stroke-width="2" viewBox="0 0 24 24">
          <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
      </div>
      <h3>Certificate Locked</h3>
      <p>Complete the course to unlock your certificate.</p>
      <div style="max-width:360px;margin:0 auto;text-align:left">
        <div style="display:flex;justify-content:space-between;font-size:.88rem;color:var(--text-muted);margin-bottom:.3rem">
          <span>Progress</span><span><?php echo $progress; ?>%</span>
        </div>
        <div class="cert-progress-bar">
          <div class="cert-progress-fill" style="width:<?php echo $progress; ?>%"></div>
        </div>
      </div>
      <a href="<?php echo BASE; ?>/course-player.php?course=<?php echo $courseId; ?>" class="cert-btn cert-btn--primary">Continue Learning</a>
    </div>

    <?php elseif ($issueError !== ''): ?>
    <!-- ── Error ─────────────────────────────── -->
    <div class="cert-locked">
      <p style="color:#b91c1c"><?php echo htmlspecialchars($issueError); ?></p>
      <a href="<?php echo BASE; ?>/my-courses.php" class="cert-btn cert-btn--outline">Back to My Courses</a>
    </div>

    <?php else: ?>
    <!-- ── Certificate ready ─────────────────── -->

    <!-- Controls -->
    <div class="cert-controls">
      <span class="section-tag">Achievement Unlocked</span>
      <div class="cert-controls-right">
        <button class="cert-btn cert-btn--green" id="btn-dl-png" onclick="downloadCert('png')">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          <span id="btn-dl-png-label">Download PNG</span>
          <span id="cert-dl-spinner"></span>
        </button>
        <button class="cert-btn cert-btn--primary" id="btn-dl-pdf" onclick="downloadCert('pdf')">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 13h6m-6 4h6m-3-8v8"/></svg>
          <span id="btn-dl-pdf-label">Download PDF</span>
        </button>
        <button class="cert-btn cert-btn--outline" onclick="printCert()">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          Print
        </button>
        <a href="<?php echo BASE; ?>/my-courses.php" class="cert-btn cert-btn--outline">
          ← My Courses
        </a>
      </div>
    </div>

    <!-- Scroll wrapper for mobile -->
    <div id="cert-scroll-wrap">
    <div id="cert-wrap">
    <!-- THE CERTIFICATE -->
    <div id="cert">

      <!-- Corner ornaments -->
      <?php
      $cornerSvg = '<svg class="cert-corner %s" viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M2 2 L28 2 L2 28 Z" fill="#b8922a" opacity=".25"/>
        <path d="M2 2 L18 2" stroke="#b8922a" stroke-width="2"/>
        <path d="M2 2 L2 18" stroke="#b8922a" stroke-width="2"/>
        <circle cx="2" cy="2" r="3" fill="#b8922a"/>
        <path d="M8 8 L20 8 L8 20 Z" fill="#b8922a" opacity=".15"/>
      </svg>';
      foreach(['cert-corner--tl','cert-corner--tr','cert-corner--bl','cert-corner--br'] as $cls) {
          echo sprintf($cornerSvg, $cls);
      }
      ?>

      <!-- Top brand band -->
      <div class="cert-band">
        <div class="cert-logo-mark">
          <svg width="36" height="36" viewBox="0 0 32 32" fill="none">
            <path d="M16 2L28.124 9V23L16 30L3.876 23V9L16 2Z" fill="#fff" opacity=".9"/>
            <path d="M11 11h2v4.5l4-4.5h2.5l-4.2 4.6L20 21h-2.5l-3.5-4.2V21H11V11Z" fill="#6366f1"/>
          </svg>
        </div>
        <div class="cert-logo-text">Nerd<span>Academy</span></div>
      </div>

      <!-- Title -->
      <div class="cert-title-row">
        <div class="cert-title-label">Certificate of Completion</div>
      </div>

      <!-- Body -->
      <div class="cert-body">
        <p class="cert-presented-to">This certificate is proudly presented to</p>
        <div class="cert-student-name"><?php echo $studentName; ?></div>
        <p class="cert-for-completing">for successfully completing the course</p>
        <div class="cert-course-title"><?php echo $courseTitle; ?></div>
      </div>

      <!-- Divider -->
      <div class="cert-divider"></div>

      <!-- Footer -->
      <div class="cert-footer">
        <div class="cert-meta-item">
          <div class="cert-meta-label">Issued On</div>
          <div class="cert-meta-value"><?php echo htmlspecialchars($issuedDate); ?></div>
        </div>

        <!-- Seal -->
        <div class="cert-seal">
          <div class="cert-seal-ring">
            <span class="cert-seal-star">★</span>
            <span class="cert-seal-text">NERD<br>ACADEMY<br>VERIFIED</span>
          </div>
        </div>

        <div class="cert-meta-item">
          <div class="cert-meta-label">Certificate ID</div>
          <div class="cert-meta-value" style="font-family:monospace;letter-spacing:.05em"><?php echo $certCode; ?></div>
        </div>
      </div>

    </div><!-- /#cert -->
    </div><!-- /#cert-wrap -->
    </div><!-- /#cert-scroll-wrap -->

    <?php endif; ?>
  </div>
</section>

<?php if ($certificate): ?>
<canvas id="cert-confetti" aria-hidden="true"></canvas>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" crossorigin="anonymous"></script>
<script>
const CERT_CODE = <?php echo $jsCertCode; ?>;

async function downloadCert(type) {
  const certEl   = document.getElementById('cert');
  const pngBtn   = document.getElementById('btn-dl-png');
  const pdfBtn   = document.getElementById('btn-dl-pdf');
  const spinner  = document.getElementById('cert-dl-spinner');
  const pngLabel = document.getElementById('btn-dl-png-label');
  const pdfLabel = document.getElementById('btn-dl-pdf-label');

  // Disable buttons + show spinner
  pngBtn.disabled = pdfBtn.disabled = true;
  spinner.style.display = 'block';
  if (type === 'png') pngLabel.textContent = 'Generating…';
  if (type === 'pdf') pdfLabel.textContent = 'Generating…';

  try {
    const canvas = await html2canvas(certEl, {
      scale: 3,
      useCORS: true,
      allowTaint: false,
      backgroundColor: '#fffef7',
      logging: false,
      imageTimeout: 8000,
    });

    if (type === 'png') {
      const link = document.createElement('a');
      link.download = 'nerdacademy-certificate-' + CERT_CODE + '.png';
      link.href = canvas.toDataURL('image/png', 1.0);
      link.click();

    } else if (type === 'pdf') {
      const { jsPDF } = window.jspdf;
      const pdf  = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
      const pdfW = pdf.internal.pageSize.getWidth();
      const pdfH = pdf.internal.pageSize.getHeight();
      // Fit image inside A4 landscape with 6mm margin
      const margin = 6;
      const imgRatio = canvas.width / canvas.height;
      const maxW = pdfW - margin * 2;
      const maxH = pdfH - margin * 2;
      let drawW = maxW;
      let drawH = drawW / imgRatio;
      if (drawH > maxH) { drawH = maxH; drawW = drawH * imgRatio; }
      const x = (pdfW - drawW) / 2;
      const y = (pdfH - drawH) / 2;
      pdf.addImage(canvas.toDataURL('image/png', 1.0), 'PNG', x, y, drawW, drawH);
      pdf.save('nerdacademy-certificate-' + CERT_CODE + '.pdf');
    }

  } catch (err) {
    alert('Download failed: ' + err.message);
  } finally {
    pngBtn.disabled = pdfBtn.disabled = false;
    spinner.style.display = 'none';
    pngLabel.textContent = 'Download PNG';
    pdfLabel.textContent = 'Download PDF';
  }
}

function printCert() {
  const certEl = document.getElementById('cert-wrap');
  if (!certEl) { window.print(); return; }

  // Collect all <style> tags from the current page that relate to the cert
  let styles = '';
  document.querySelectorAll('style').forEach(function(s){ styles += s.outerHTML; });

  // Google Fonts link if present
  let links = '';
  document.querySelectorAll('link[rel="stylesheet"]').forEach(function(l){
    links += l.outerHTML;
  });

  const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Certificate — NerdAcademy</title>
  ${links}
  ${styles}
  <style>
    @page { size: A4 landscape; margin: 8mm; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; background: #fff; }
    #cert-wrap {
      width: 100%;
      background: #fffef7;
      padding: 0;
      box-shadow: none;
    }
    #cert {
      min-height: 0 !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
      color-adjust: exact !important;
    }
    .cert-band {
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
  </style>
</head>
<body>
  ${certEl.outerHTML}
  <script>
    window.onload = function(){ window.print(); window.onafterprint = function(){ window.close(); }; };
  <\/script>
</body>
</html>`;

  const pw = window.open('', '_blank', 'width=1100,height=780');
  if (!pw) { window.print(); return; }
  pw.document.open();
  pw.document.write(html);
  pw.document.close();
}
</script>
<?php endif; ?>

<?php if ($certificate): ?>
<script>
(function(){
  var canvas = document.getElementById('cert-confetti');
  if (!canvas) return;
  var ctx = canvas.getContext('2d');

  var COLORS = [
    '#6366f1','#8b5cf6','#a855f7',
    '#f59e0b','#fbbf24','#fcd34d',
    '#10b981','#34d399',
    '#f43f5e','#fb7185',
    '#38bdf8','#60a5fa',
    '#fff','#e0e7ff'
  ];

  var pieces = [];
  var W, H;

  function resize(){
    W = canvas.width  = window.innerWidth;
    H = canvas.height = window.innerHeight;
  }
  resize();
  window.addEventListener('resize', resize);

  function rand(a, b){ return a + Math.random() * (b - a); }

  function Piece(){
    this.x  = rand(0, W);
    this.y  = rand(-H * 0.3, -10);
    this.w  = rand(7, 15);
    this.h  = rand(4, 9);
    this.color = COLORS[Math.floor(Math.random() * COLORS.length)];
    this.vx = rand(-2, 2);
    this.vy = rand(2.5, 6.5);
    this.angle  = rand(0, Math.PI * 2);
    this.spin   = rand(-0.12, 0.12);
    this.opacity = 1;
    // shape: 0=rect, 1=circle, 2=strip
    this.shape  = Math.floor(Math.random() * 3);
  }

  Piece.prototype.update = function(dt){
    this.x += this.vx;
    this.y += this.vy;
    this.angle += this.spin;
    this.vx += rand(-0.04, 0.04); // gentle drift
    // fade when near bottom
    if (this.y > H * 0.75) this.opacity = Math.max(0, this.opacity - 0.018);
  };

  Piece.prototype.draw = function(){
    ctx.save();
    ctx.globalAlpha = this.opacity;
    ctx.translate(this.x, this.y);
    ctx.rotate(this.angle);
    ctx.fillStyle = this.color;
    if (this.shape === 1) {
      ctx.beginPath();
      ctx.arc(0, 0, this.w / 2, 0, Math.PI * 2);
      ctx.fill();
    } else if (this.shape === 2) {
      ctx.fillRect(-this.w / 2, -this.h / 4, this.w, this.h / 2);
    } else {
      ctx.fillRect(-this.w / 2, -this.h / 2, this.w, this.h);
    }
    ctx.restore();
  };

  // Burst: spawn a wave of pieces
  var TOTAL = 180;
  var spawnCount = 0;
  var spawnInterval = setInterval(function(){
    var batch = Math.min(18, TOTAL - spawnCount);
    for (var i = 0; i < batch; i++) pieces.push(new Piece());
    spawnCount += batch;
    if (spawnCount >= TOTAL) clearInterval(spawnInterval);
  }, 80);

  var active = true;
  var last = performance.now();

  function loop(now){
    if (!active) return;
    var dt = now - last; last = now;
    ctx.clearRect(0, 0, W, H);

    for (var i = pieces.length - 1; i >= 0; i--){
      pieces[i].update(dt);
      pieces[i].draw();
      if (pieces[i].y > H + 20 || pieces[i].opacity <= 0) pieces.splice(i, 1);
    }

    if (pieces.length > 0) {
      requestAnimationFrame(loop);
    } else {
      active = false;
      canvas.remove();
    }
  }

  requestAnimationFrame(loop);
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
