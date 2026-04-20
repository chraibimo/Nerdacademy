<?php
$page_title = 'Course Bundles — More Learning, Better Price';
$page_desc  = 'Bundle the courses that matter to you and save big. Curated sets built to take you further, faster.';
if (!defined('BASE')) define('BASE', '');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/bundles-repo.php';
require_once __DIR__ . '/includes/purchases-repo.php';
ensure_bundle_tables($mysqli);
$user    = auth_current_user();
$bundles = get_all_bundles($mysqli, true);
require_once __DIR__ . '/includes/header.php';

// Compute max savings % for hero subtitle
$maxSavings = 0;
foreach ($bundles as $b) {
    if ((float)$b['original_price'] > 0) {
        $pct = round((1 - (float)$b['price'] / (float)$b['original_price']) * 100);
        if ($pct > $maxSavings) $maxSavings = $pct;
    }
}
?>

<!-- ─── Hero ───────────────────────────────────────────────────────────────── -->
<section class="section" style="background:linear-gradient(135deg,#1e1b4b 0%,#312e81 50%,#1e1b4b 100%);padding:5rem 0 4rem;text-align:center;position:relative;overflow:hidden">
  <!-- Decorative orbs -->
  <div style="position:absolute;top:-80px;left:50%;transform:translateX(-50%);width:600px;height:600px;background:radial-gradient(circle,rgba(99,102,241,.25) 0%,transparent 70%);pointer-events:none"></div>
  <div class="container" style="position:relative;z-index:1">
    <div style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(99,102,241,.18);border:1px solid rgba(99,102,241,.35);border-radius:2rem;padding:.35rem 1rem;margin-bottom:1.25rem;font-size:.82rem;color:#a5b4fc;font-weight:500;letter-spacing:.04em">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
      LIMITED BUNDLES
    </div>
    <h1 style="font-size:clamp(2rem,5vw,3.25rem);font-weight:800;color:#fff;margin:0 0 1rem;line-height:1.15">
      More learning. Better price.
    </h1>
    <p style="font-size:1.15rem;color:#c7d2fe;max-width:520px;margin:0 auto 1.75rem">
      <?php if ($maxSavings > 0): ?>
        Bundle the courses that matter to you and save up to <strong style="color:#a5b4fc"><?= $maxSavings ?>%</strong>.
      <?php else: ?>
        Curated course sets at one honest price. No tricks.
      <?php endif; ?>
    </p>
    <?php if (!$user): ?>
    <a href="<?= BASE ?>/login.php" class="btn-primary btn-lg" style="background:#6366f1;color:#fff;padding:.85rem 2rem;border-radius:10px;font-weight:600;text-decoration:none;display:inline-block">
      Sign in to get started
    </a>
    <?php endif; ?>
  </div>
</section>

<!-- ─── Bundles Grid ────────────────────────────────────────────────────────── -->
<section class="section" style="padding:4rem 0 5rem;background:var(--bg-section,#f8fafc)">
  <div class="container">

    <?php if (empty($bundles)): ?>
    <div style="text-align:center;padding:4rem 1rem;color:var(--text-muted)">
      <svg width="56" height="56" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 1rem;display:block;opacity:.4" aria-hidden="true">
        <rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
      </svg>
      <p style="font-size:1.1rem;font-weight:500">No bundles are live right now.</p>
      <p style="font-size:.9rem">We're putting new ones together — check back soon. In the meantime, browse individual courses.</p>
      <a href="<?= BASE ?>/courses.php" class="btn-primary" style="background:#6366f1;color:#fff;padding:.7rem 1.5rem;border-radius:8px;font-weight:600;text-decoration:none;display:inline-block;margin-top:1.25rem">
        Browse All Courses
      </a>
    </div>
    <?php else: ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:2rem">
      <?php foreach ($bundles as $b): ?>
      <?php
        $purchased  = $user ? has_user_purchased_bundle($mysqli, (int)$user['id'], (int)$b['id']) : false;
        $courseCount = count($b['courses']);
        $savings    = (float)$b['original_price'] > 0
                      ? round((1 - (float)$b['price'] / (float)$b['original_price']) * 100)
                      : 0;
        $gradients = [
            'linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%)',
            'linear-gradient(135deg,#0ea5e9 0%,#6366f1 100%)',
            'linear-gradient(135deg,#10b981 0%,#06b6d4 100%)',
            'linear-gradient(135deg,#f59e0b 0%,#ef4444 100%)',
            'linear-gradient(135deg,#8b5cf6 0%,#ec4899 100%)',
        ];
        $grad = $gradients[$b['id'] % count($gradients)];
        $shownCourses = array_slice($b['courses'], 0, 3);
        $extraCount   = max(0, $courseCount - 3);
      ?>
      <article class="course-card" style="display:flex;flex-direction:column;overflow:hidden;border-radius:14px;background:var(--bg-surface,#fff);border:1px solid var(--border,#e4e6f0);box-shadow:0 2px 16px rgba(0,0,0,.07);transition:transform .2s,box-shadow .2s"
               onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 8px 32px rgba(99,102,241,.18)'"
               onmouseout="this.style.transform='';this.style.boxShadow='0 2px 16px rgba(0,0,0,.07)'">

        <!-- Card top: image or gradient banner -->
        <div class="course-card-top" style="position:relative;height:160px;overflow:hidden">
          <?php if (!empty($b['image_url'])): ?>
            <img src="<?= htmlspecialchars($b['image_url']) ?>" alt="<?= htmlspecialchars($b['title']) ?>"
                 style="width:100%;height:100%;object-fit:cover">
          <?php else: ?>
            <div style="width:100%;height:100%;background:<?= $grad ?>"></div>
          <?php endif; ?>

          <!-- Overlay badges -->
          <div style="position:absolute;top:.75rem;left:.75rem;display:flex;gap:.4rem;flex-wrap:wrap">
            <?php if ($savings > 0): ?>
            <span style="background:#10b981;color:#fff;font-size:.72rem;font-weight:700;padding:.25rem .65rem;border-radius:2rem;letter-spacing:.03em">
              Save <?= $savings ?>%
            </span>
            <?php endif; ?>
            <?php if ($purchased): ?>
            <span style="background:#6366f1;color:#fff;font-size:.72rem;font-weight:700;padding:.25rem .65rem;border-radius:2rem">
              Enrolled
            </span>
            <?php endif; ?>
          </div>

          <div style="position:absolute;bottom:.75rem;right:.75rem;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);color:#fff;font-size:.75rem;font-weight:600;padding:.25rem .65rem;border-radius:2rem">
            <?= $courseCount ?> course<?= $courseCount !== 1 ? 's' : '' ?>
          </div>
        </div>

        <!-- Card body -->
        <div class="course-card-body" style="padding:1.25rem 1.4rem;display:flex;flex-direction:column;flex:1">
          <h2 style="font-size:1.1rem;font-weight:700;margin:0 0 .5rem;color:var(--text-primary)"><?= htmlspecialchars($b['title']) ?></h2>

          <?php if (!empty($b['description'])): ?>
          <p style="font-size:.88rem;color:var(--text-muted);margin:0 0 1rem;line-height:1.55">
            <?= htmlspecialchars($b['description']) ?>
          </p>
          <?php endif; ?>

          <!-- Included courses list -->
          <div style="margin-bottom:1rem">
            <div style="font-size:.78rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem">
              Included
            </div>
            <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.3rem">
              <?php foreach ($shownCourses as $c): ?>
              <li style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;color:var(--text-primary)">
                <svg width="13" height="13" fill="none" stroke="#10b981" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
                <?= htmlspecialchars($c['title'] ?? '') ?>
              </li>
              <?php endforeach; ?>
              <?php if ($extraCount > 0): ?>
              <li style="font-size:.82rem;color:var(--text-muted);padding-left:1.4rem">
                + <?= $extraCount ?> more course<?= $extraCount !== 1 ? 's' : '' ?>
              </li>
              <?php endif; ?>
            </ul>
          </div>

          <!-- Spacer to push price/button to bottom -->
          <div style="flex:1"></div>

          <!-- Price row -->
          <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem">
            <span style="font-size:1.65rem;font-weight:800;color:var(--text-primary)">
              $<?= number_format((float)$b['price'], 2) ?>
            </span>
            <?php if ((float)$b['original_price'] > 0): ?>
            <span style="font-size:1rem;color:var(--text-muted);text-decoration:line-through">
              $<?= number_format((float)$b['original_price'], 2) ?>
            </span>
            <?php endif; ?>
          </div>

          <!-- CTA button -->
          <?php if ($purchased): ?>
          <a href="<?= BASE ?>/my-courses.php"
             class="btn-primary"
             style="display:block;text-align:center;background:#10b981;color:#fff;padding:.8rem 1.25rem;border-radius:8px;font-weight:600;text-decoration:none;transition:opacity .15s"
             onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity=''">
            Go to My Courses
          </a>
          <?php elseif ($user): ?>
          <form method="POST" action="<?= BASE ?>/purchase-bundle.php">
            <input type="hidden" name="bundle_id" value="<?= (int)$b['id'] ?>">
            <button type="submit"
                    class="btn-primary btn-lg"
                    style="width:100%;background:var(--grad-primary,linear-gradient(135deg,#6366f1,#8b5cf6));color:#fff;border:none;padding:.8rem 1.25rem;border-radius:8px;font-size:.95rem;font-weight:600;cursor:pointer;transition:opacity .15s"
                    onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity=''">
              Get Bundle — $<?= number_format((float)$b['price'], 2) ?>
            </button>
          </form>
          <?php else: ?>
          <a href="<?= BASE ?>/login.php?redirect=<?= urlencode(BASE . '/bundles.php') ?>"
             class="btn-primary btn-lg"
             style="display:block;text-align:center;background:var(--grad-primary,linear-gradient(135deg,#6366f1,#8b5cf6));color:#fff;padding:.8rem 1.25rem;border-radius:8px;font-weight:600;text-decoration:none;transition:opacity .15s"
             onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity=''">
            Sign in to grab this bundle
          </a>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>

    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
