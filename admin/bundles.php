<?php
$admin_page_title  = 'Course Bundles';
$admin_active_page = 'bundles';
if (!defined('BASE')) define('BASE', '');
require_once __DIR__ . '/_head.php';
require_once __DIR__ . '/../includes/bundles-repo.php';
require_once __DIR__ . '/../includes/courses-repo.php';
ensure_bundle_tables($mysqli);
ensure_courses_catalog_table($mysqli);

$message     = '';
$messageType = 'success';

/* ── POST handlers ───────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save_bundle') {
        $bundleId     = isset($_POST['bundle_id']) && (int)$_POST['bundle_id'] > 0
                        ? (int)$_POST['bundle_id'] : null;
        $title        = trim((string)($_POST['title']          ?? ''));
        $desc         = trim((string)($_POST['description']    ?? ''));
        $price        = (float)($_POST['price']         ?? 0);
        $origPrice    = (float)($_POST['original_price'] ?? 0);
        $imageUrl     = trim((string)($_POST['image_url']      ?? ''));
        $isActive     = isset($_POST['is_active']) && $_POST['is_active'] === '1';
        $courseIds    = array_map('intval', (array)($_POST['course_ids'] ?? []));

        if ($title === '') {
            $message     = 'Bundle title is required.';
            $messageType = 'error';
        } else {
            $savedId = save_bundle($mysqli, $title, $desc, $price, $origPrice, $imageUrl, $isActive, $courseIds, $bundleId);
            if ($savedId > 0) {
                $message = $bundleId ? 'Bundle updated successfully.' : 'Bundle created successfully.';
            } else {
                $message     = 'Failed to save bundle.';
                $messageType = 'error';
            }
        }
    }

    if ($action === 'delete_bundle') {
        $bundleId = (int)($_POST['bundle_id'] ?? 0);
        if ($bundleId > 0 && delete_bundle($mysqli, $bundleId)) {
            $message = 'Bundle deleted.';
        } else {
            $message     = 'Failed to delete bundle.';
            $messageType = 'error';
        }
    }

    if ($action === 'toggle_active') {
        $bundleId = (int)($_POST['bundle_id'] ?? 0);
        $newVal   = (int)($_POST['new_val']   ?? 0);
        if ($bundleId > 0) {
            $stmt = $mysqli->prepare('UPDATE bundles SET is_active = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('ii', $newVal, $bundleId);
                $stmt->execute();
                $stmt->close();
                $message = 'Bundle status updated.';
            }
        }
    }
}

/* ── Data ────────────────────────────────────────────────────────────────── */
$stats   = get_bundle_sales_stats($mysqli);
$bundles = $stats; // stats array includes all bundle fields

$totalBundles    = count($bundles);
$activeBundles   = count(array_filter($bundles, fn($b) => (int)$b['is_active'] === 1));
$totalPurchases  = array_sum(array_column($bundles, 'purchase_count'));
$totalRevenue    = array_sum(array_column($bundles, 'revenue'));

$allCourses = load_all_courses($mysqli, false);
?>

<!-- ── Flash message ──────────────────────────────────────────────────────── -->
<?php if ($message !== ''): ?>
<div class="a-flash a-flash--<?= $messageType === 'error' ? 'danger' : 'success' ?>" style="margin-bottom:1.25rem">
  <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- ── Page header ──────────────────────────────────────────────────────── -->
<div class="a-page-header">
  <div class="a-page-header-text">
    <h1>Course Bundles</h1>
    <p>Create and manage discounted course bundles</p>
  </div>
  <div>
    <button class="a-btn a-btn--primary" onclick="openModal('bundleModal')">
      + New Bundle
    </button>
  </div>
</div>

<!-- ── Stats ─────────────────────────────────────────────────────────────── -->
<div class="a-stats-grid">

  <div class="a-stat-card">
    <div class="a-stat-icon a-stat-icon--indigo">
      <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
      </svg>
    </div>
    <div class="a-stat-body">
      <div class="a-stat-label">Total Bundles</div>
      <div class="a-stat-value"><?= number_format($totalBundles) ?></div>
    </div>
  </div>

  <div class="a-stat-card">
    <div class="a-stat-icon a-stat-icon--green">
      <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
      </svg>
    </div>
    <div class="a-stat-body">
      <div class="a-stat-label">Active Bundles</div>
      <div class="a-stat-value"><?= number_format($activeBundles) ?></div>
    </div>
  </div>

  <div class="a-stat-card">
    <div class="a-stat-icon a-stat-icon--amber">
      <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
        <rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 12l2 2 4-4"/>
      </svg>
    </div>
    <div class="a-stat-body">
      <div class="a-stat-label">Total Purchases</div>
      <div class="a-stat-value"><?= number_format($totalPurchases) ?></div>
    </div>
  </div>

  <div class="a-stat-card">
    <div class="a-stat-icon a-stat-icon--blue">
      <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <line x1="12" y1="1" x2="12" y2="23"/>
        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
      </svg>
    </div>
    <div class="a-stat-body">
      <div class="a-stat-label">Total Revenue</div>
      <div class="a-stat-value">$<?= number_format($totalRevenue, 2) ?></div>
    </div>
  </div>

</div>

<!-- ── Bundles Table ──────────────────────────────────────────────────────── -->
<div class="a-table-card">
  <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th>Bundle</th>
          <th>Courses</th>
          <th>Price</th>
          <th>Status</th>
          <th>Purchases</th>
          <th>Revenue</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($bundles)): ?>
        <tr>
          <td colspan="7" style="text-align:center;padding:2.5rem;color:var(--a-text-muted)">
            No bundles yet. Click "New Bundle" to create one.
          </td>
        </tr>
        <?php else: ?>
        <?php foreach ($bundles as $b): ?>
        <?php
          $courseCount = count($b['courses']);
          $savings = $b['original_price'] > 0
              ? round((1 - $b['price'] / $b['original_price']) * 100)
              : 0;
          $colors = ['#6366f1','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444'];
          $colorIdx = $b['id'] % count($colors);
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:.75rem">
              <?php if (!empty($b['image_url'])): ?>
                <img src="<?= htmlspecialchars($b['image_url']) ?>" alt=""
                     style="width:48px;height:36px;object-fit:cover;border-radius:6px;flex-shrink:0">
              <?php else: ?>
                <div style="width:48px;height:36px;border-radius:6px;background:<?= $colors[$colorIdx] ?>;flex-shrink:0;opacity:.85"></div>
              <?php endif; ?>
              <div>
                <div style="font-weight:600;color:var(--a-text)"><?= htmlspecialchars($b['title']) ?></div>
                <?php if (!empty($b['description'])): ?>
                <div style="font-size:.78rem;color:var(--a-text-muted);max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                  <?= htmlspecialchars($b['description']) ?>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td>
            <span class="a-badge a-badge--primary"><?= $courseCount ?> course<?= $courseCount !== 1 ? 's' : '' ?></span>
          </td>
          <td>
            <div style="font-weight:600;color:var(--a-text)">$<?= number_format((float)$b['price'], 2) ?></div>
            <?php if ((float)$b['original_price'] > 0): ?>
            <div style="font-size:.78rem;color:var(--a-text-muted);text-decoration:line-through">
              $<?= number_format((float)$b['original_price'], 2) ?>
            </div>
            <?php if ($savings > 0): ?>
            <span class="a-badge a-badge--success" style="font-size:.7rem">Save <?= $savings ?>%</span>
            <?php endif; ?>
            <?php endif; ?>
          </td>
          <td>
            <?php if ((int)$b['is_active'] === 1): ?>
              <span class="a-badge a-badge--success">Active</span>
            <?php else: ?>
              <span class="a-badge a-badge--muted">Inactive</span>
            <?php endif; ?>
          </td>
          <td style="font-weight:500"><?= number_format((int)$b['purchase_count']) ?></td>
          <td style="font-weight:600">$<?= number_format((float)$b['revenue'], 2) ?></td>
          <td>
            <div style="display:flex;gap:.4rem;flex-wrap:wrap">
              <button class="a-btn a-btn--ghost a-btn--sm"
                      onclick="editBundle(<?= htmlspecialchars(json_encode($b), ENT_QUOTES) ?>)">
                Edit
              </button>
              <!-- Toggle active -->
              <form method="POST" action="<?= BASE ?>/admin/bundles.php" style="display:inline">
                <input type="hidden" name="action"    value="toggle_active">
                <input type="hidden" name="bundle_id" value="<?= (int)$b['id'] ?>">
                <input type="hidden" name="new_val"   value="<?= (int)$b['is_active'] === 1 ? 0 : 1 ?>">
                <button type="submit" class="a-btn a-btn--ghost a-btn--sm">
                  <?= (int)$b['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                </button>
              </form>
              <button class="a-btn a-btn--danger a-btn--sm"
                      onclick="confirmDelete(<?= (int)$b['id'] ?>, <?= htmlspecialchars(json_encode($b['title']), ENT_QUOTES) ?>)">
                Delete
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Add / Edit Bundle Modal ───────────────────────────────────────────── -->
<div class="a-modal-bg" id="bundleModal" style="display:none" onclick="if(event.target===this)closeModal('bundleModal')">
  <div class="a-modal" style="max-width:680px;width:100%" onclick="event.stopPropagation()">
    <div class="a-modal-head">
      <h2 id="bundleModalTitle">New Bundle</h2>
      <button type="button" class="a-modal-close" onclick="closeModal('bundleModal')" aria-label="Close">&times;</button>
    </div>

    <form method="POST" action="<?= BASE ?>/admin/bundles.php">
      <input type="hidden" name="action"    value="save_bundle">
      <input type="hidden" name="bundle_id" id="editBundleId" value="">

      <div class="a-modal-body">
        <div class="a-form-grid">
        <div class="a-form-group full">
          <label class="a-label">Bundle Title <span style="color:#ef4444">*</span></label>
          <input type="text" name="title" id="bundleTitle" class="a-input" required placeholder="e.g. AI Master Bundle">
        </div>

        <div class="a-form-group full">
          <label class="a-label">Description</label>
          <textarea name="description" id="bundleDesc" class="a-input" rows="3"
                    placeholder="Short description shown on the bundle page"></textarea>
        </div>

        <div class="a-form-group">
          <label class="a-label">Bundle Price ($)</label>
          <input type="number" name="price" id="bundlePrice" class="a-input" step="0.01" min="0" value="0">
        </div>
        <div class="a-form-group">
          <label class="a-label">Original Price ($) <span style="color:var(--a-text-muted);font-size:.78rem">(for strikethrough)</span></label>
          <input type="number" name="original_price" id="bundleOrigPrice" class="a-input" step="0.01" min="0" value="0">
        </div>

        <div class="a-form-group full">
          <label class="a-label">Image URL <span style="color:var(--a-text-muted);font-size:.78rem">(optional)</span></label>
          <input type="url" name="image_url" id="bundleImageUrl" class="a-input" placeholder="https://…">
        </div>

        <div class="a-form-group full">
          <label class="a-label" style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
            <input type="hidden"   name="is_active" value="0">
            <input type="checkbox" name="is_active" id="bundleActive" value="1" checked>
            Active (visible on site)
          </label>
        </div>

        <div class="a-form-group full">
          <label class="a-label">Included Courses</label>
          <div style="border:1px solid var(--a-border);border-radius:8px;max-height:240px;overflow-y:auto;padding:.5rem .75rem">
            <?php if (empty($allCourses)): ?>
              <p style="color:var(--a-text-muted);font-size:.85rem;margin:.5rem 0">No courses available.</p>
            <?php else: ?>
              <?php foreach ($allCourses as $c): ?>
              <label style="display:flex;align-items:center;gap:.6rem;padding:.35rem 0;cursor:pointer;font-size:.88rem">
                <input type="checkbox" name="course_ids[]"
                       value="<?= (int)$c['id'] ?>"
                       class="bundle-course-cb">
                <span style="flex:1"><?= htmlspecialchars($c['title']) ?></span>
                <span style="color:var(--a-text-muted);font-size:.78rem">$<?= number_format((float)$c['price'], 2) ?></span>
              </label>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="a-modal-body" style="padding-top:0">
        <div style="display:flex;gap:.75rem;justify-content:flex-end">
          <button type="button" class="a-btn a-btn--ghost" onclick="closeModal('bundleModal')">Cancel</button>
          <button type="submit" class="a-btn a-btn--primary">Save Bundle</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ── Delete Confirmation Modal ─────────────────────────────────────────── -->
<div class="a-modal-bg" id="deleteModal" style="display:none" onclick="if(event.target===this)closeModal('deleteModal')">
  <div class="a-modal" style="max-width:420px;width:100%" onclick="event.stopPropagation()">
    <div class="a-modal-head">
      <h2>Delete Bundle</h2>
      <button type="button" class="a-modal-close" onclick="closeModal('deleteModal')" aria-label="Close">&times;</button>
    </div>
    <form method="POST" action="<?= BASE ?>/admin/bundles.php">
      <input type="hidden" name="action"    value="delete_bundle">
      <input type="hidden" name="bundle_id" id="deleteBundleId" value="">
      <div class="a-modal-body">
        <p style="color:var(--a-text-muted);margin:0">
          Are you sure you want to delete "<strong id="deleteBundleName"></strong>"?
          This cannot be undone.
        </p>
      </div>
      <div class="a-modal-body" style="padding-top:0">
        <div style="display:flex;gap:.75rem;justify-content:flex-end">
          <button type="button" class="a-btn a-btn--ghost" onclick="closeModal('deleteModal')">Cancel</button>
          <button type="submit" class="a-btn a-btn--danger">Delete Bundle</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id) {
  document.getElementById(id).style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id).style.display = 'none';
  document.body.style.overflow = '';
}

function editBundle(bundle) {
  document.getElementById('bundleModalTitle').textContent = 'Edit Bundle';
  document.getElementById('editBundleId').value    = bundle.id;
  document.getElementById('bundleTitle').value     = bundle.title    || '';
  document.getElementById('bundleDesc').value      = bundle.description || '';
  document.getElementById('bundlePrice').value     = bundle.price    || 0;
  document.getElementById('bundleOrigPrice').value = bundle.original_price || 0;
  document.getElementById('bundleImageUrl').value  = bundle.image_url || '';
  document.getElementById('bundleActive').checked  = parseInt(bundle.is_active) === 1;

  // Reset course checkboxes then tick the ones in this bundle
  const cbs = document.querySelectorAll('.bundle-course-cb');
  const enrolledIds = (bundle.courses || []).map(c => parseInt(c.id));
  cbs.forEach(cb => {
    cb.checked = enrolledIds.includes(parseInt(cb.value));
  });

  openModal('bundleModal');
}

function confirmDelete(id, name) {
  document.getElementById('deleteBundleId').value = id;
  document.getElementById('deleteBundleName').textContent = name;
  openModal('deleteModal');
}

// When modal opens for "New Bundle", reset form
document.querySelector('[onclick="openModal(\'bundleModal\')"]').addEventListener('click', function () {
  document.getElementById('bundleModalTitle').textContent = 'New Bundle';
  document.getElementById('editBundleId').value    = '';
  document.getElementById('bundleTitle').value     = '';
  document.getElementById('bundleDesc').value      = '';
  document.getElementById('bundlePrice').value     = '0';
  document.getElementById('bundleOrigPrice').value = '0';
  document.getElementById('bundleImageUrl').value  = '';
  document.getElementById('bundleActive').checked  = true;
  document.querySelectorAll('.bundle-course-cb').forEach(cb => cb.checked = false);
});

// Escape key closes modals
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    ['bundleModal', 'deleteModal'].forEach(id => {
      const el = document.getElementById(id);
      if (el && el.style.display !== 'none') closeModal(id);
    });
  }
});
</script>

<?php require_once __DIR__ . '/_foot.php'; ?>
