<?php
$page_title = 'My Courses';
$page_desc  = 'Your purchased AI courses on NerdAcademy.';

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/courses-repo.php';
require_once __DIR__ . '/includes/purchases-repo.php';
require_once __DIR__ . '/includes/streak-repo.php';
require_once __DIR__ . '/includes/certificates-repo.php';
require_once __DIR__ . '/includes/support-repo.php';

$courses = load_all_courses($mysqli, true);

$user = auth_current_user();
$purchasedIds = [];
$progressMap = [];
$certificateMap = [];
$purchaseMap = [];
ensure_streak_table($mysqli);
$streak = $user ? get_user_streak($mysqli, (int)$user['id']) : ['current_streak' => 0, 'longest_streak' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_progress']) && $user) {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $progress = (int)($_POST['progress_percent'] ?? 0);
    if ($courseId > 0 && has_user_enrolled_course($mysqli, (int)$user['id'], $courseId)) {
        set_course_progress($mysqli, (int)$user['id'], $courseId, $progress, null);
    }
}

if ($user) {
    $purchasedIds = get_user_enrolled_course_ids($mysqli, (int)$user['id']);
    $progressMap = get_user_progress_map($mysqli, (int)$user['id']);
    $certificateMap = get_user_certificates_map($mysqli, (int)$user['id']);
    $purchaseMap = get_user_purchases_map($mysqli, (int)$user['id']);
}

// Handle refund request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_refund']) && $user) {
    $refundCourseId = (int)($_POST['course_id'] ?? 0);
    $refundReason = trim((string)($_POST['refund_reason'] ?? ''));
    $purchase = $purchaseMap[$refundCourseId] ?? null;
    if ($purchase && $refundReason !== '') {
        $courseName = '';
        foreach ($courses as $c) {
            if ((int)$c['id'] === $refundCourseId) { $courseName = $c['title']; break; }
        }
        $subject = 'Refund Request — ' . $courseName;
        $message = "Course: $courseName (ID: $refundCourseId)\n"
                 . "Purchase ID: {$purchase['id']}\n"
                 . "Amount: \${$purchase['amount']} {$purchase['currency']}\n"
                 . "Purchased: {$purchase['purchased_at']}\n\n"
                 . "Reason:\n$refundReason";
        create_support_ticket($mysqli, (int)$user['id'], $subject, 'refund', 'normal', $message);
        $refundSuccess = true;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="my-courses-hero">
    <div class="container">
        <div class="section-tag">Dashboard</div>
        <div class="my-courses-header">
            <div>
                <h1>My Courses</h1>
                <p>Continue learning where you left off.</p>
            </div>
            <?php if ($user): ?>
            <div class="enrolled-count" style="display:flex">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                <span><?php echo count($purchasedIds); ?></span> course<?php echo count($purchasedIds) === 1 ? '' : 's'; ?> enrolled
            </div>
            <div class="enrolled-count" style="display:flex;gap:.5rem;align-items:center">
                <span style="font-size:1.2rem">🔥</span>
                <div>
                    <span><?php echo (int)$streak['current_streak']; ?></span> day streak
                    <?php if ($streak['longest_streak'] > 0): ?>
                    <span style="color:var(--text-muted);font-size:.8rem">(best: <?php echo (int)$streak['longest_streak']; ?>)</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="enrolled-count" style="display:flex;gap:.5rem;align-items:center">
                <span style="font-size:1.2rem">🏅</span>
                <div>
                    <span><?php echo count($certificateMap); ?></span> certificate<?php echo count($certificateMap) === 1 ? '' : 's'; ?> earned
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section" style="padding-top:2.5rem">
    <div class="container">
        <?php if (!$user): ?>
            <div class="auth-required-overlay">
                <div class="auth-required-box">
                    <div class="empty-icon">
                        <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <h3>Sign in to see your courses</h3>
                    <p>Access your purchased courses by signing in to your account.</p>
                    <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
                        <a href="<?php echo BASE; ?>/login.php" class="btn-primary btn-lg">Sign In</a>
                        <a href="<?php echo BASE; ?>/register.php" class="btn-ghost btn-lg">Create Account</a>
                    </div>
                </div>
            </div>
        <?php elseif (empty($purchasedIds)): ?>
            <div class="empty-courses">
                <div class="empty-icon">
                    <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                </div>
                <h3>No courses yet</h3>
                <p>You haven't enrolled in any courses yet. Start your AI learning journey today.</p>
                <a href="<?php echo BASE; ?>/courses.php" class="btn-primary btn-lg">Browse Courses</a>
            </div>
        <?php else: ?>
            <div class="courses-grid">
                <?php foreach ($courses as $course): ?>
                    <?php if (in_array((int)$course['id'], $purchasedIds, true)): ?>
                        <div class="course-card" data-color="<?php echo $course['color']; ?>">
                            <div class="course-card-top" style="--c:<?php echo $course['color']; ?>">
                                <?php if (!empty($course['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars((string)$course['image_url']); ?>" alt="<?php echo htmlspecialchars((string)$course['title']); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:inherit">
                                <?php else: ?>
                                <div class="course-icon">
                                    <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="course-card-body">
                                <div class="course-meta-top">
                                    <span class="course-cat"><?php echo $course['category']; ?></span>
                                    <span class="course-level level-<?php echo strtolower($course['level']); ?>"><?php echo $course['level']; ?></span>
                                </div>
                                <h3 class="course-title"><?php echo $course['title']; ?></h3>
                                <p class="course-desc"><?php echo $course['subtitle']; ?></p>
                                <?php
                                    $courseProgress = $progressMap[(int)$course['id']] ?? ['progress_percent' => 0, 'last_lesson' => ''];
                                    $progress = (int)($courseProgress['progress_percent'] ?? 0);
                                    $lastLessonId = (int)($courseProgress['last_lesson'] ?? 0);
                                    $continueUrl = BASE . '/course-player.php?course=' . (int)$course['id'];
                                    if ($lastLessonId > 0) {
                                        $continueUrl .= '&lesson=' . $lastLessonId . '&resume=1';
                                    }
                                    $certificateInfo = $certificateMap[(int)$course['id']] ?? null;
                                ?>
                                <div style="margin-top:.65rem">
                                    <div style="display:flex;justify-content:space-between;font-size:.82rem;color:var(--text-muted)">
                                        <span>Progress</span>
                                        <span><?php echo $progress; ?>%</span>
                                    </div>
                                    <div style="height:8px;background:var(--border);border-radius:999px;overflow:hidden;margin-top:.35rem">
                                        <div style="width:<?php echo $progress; ?>%;height:100%;background:var(--grad-primary)"></div>
                                    </div>

                                    <p style="margin:.55rem 0 0;color:var(--text-muted);font-size:.82rem">
                                        <?php if ($progress >= 100): ?>
                                            Course completed — your certificate is ready.
                                        <?php elseif ($lastLessonId > 0): ?>
                                            Resume from your last saved lesson with one click.
                                        <?php else: ?>
                                            Progress is tracked automatically while you learn.
                                        <?php endif; ?>
                                    </p>

                                    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.6rem">
                                        <a href="<?php echo htmlspecialchars($continueUrl); ?>" class="btn-primary" style="padding:.45rem .75rem;display:inline-flex">
                                            <?php echo $progress > 0 ? 'Continue Learning' : 'Start Course'; ?>
                                        </a>
                                        <?php if ($progress >= 100): ?>
                                        <a href="<?php echo BASE; ?>/certificate.php?course_id=<?php echo (int)$course['id']; ?>" class="btn-ghost" style="padding:.45rem .75rem;display:inline-flex">
                                            <?php echo $certificateInfo ? 'View Certificate' : 'Get Certificate'; ?>
                                        </a>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Action buttons: Details, Refund, Share -->
                                    <?php $purchaseInfo = $purchaseMap[(int)$course['id']] ?? null; ?>
                                    <div class="mc-actions" style="display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.55rem;padding-top:.55rem;border-top:1px solid var(--border)">
                                        <button type="button" class="mc-action-btn" onclick="showDetails(<?php echo (int)$course['id']; ?>)" title="Purchase details">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                            Details
                                        </button>
                                        <button type="button" class="mc-action-btn" onclick="showRefund(<?php echo (int)$course['id']; ?>)" title="Request a refund">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                                            Refund
                                        </button>
                                        <button type="button" class="mc-action-btn" onclick="shareCourse(<?php echo (int)$course['id']; ?>)" title="Share this course">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                                            Share
                                        </button>
                                    </div>

                                    <?php if ($certificateInfo && !empty($certificateInfo['issued_at'])): ?>
                                    <div style="margin-top:.45rem;color:var(--accent-green);font-size:.8rem">
                                        Issued on <?php echo htmlspecialchars(date('M d, Y', strtotime((string)$certificateInfo['issued_at']))); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Purchase details data for JS -->
<?php if ($user && !empty($purchaseMap)): ?>
<script>
var __purchaseData = <?php
    $jsData = [];
    foreach ($courses as $c) {
        $cid = (int)$c['id'];
        if (!in_array($cid, $purchasedIds, true)) continue;
        $p = $purchaseMap[$cid] ?? null;
        $jsData[$cid] = [
            'title'       => $c['title'],
            'category'    => $c['category'],
            'level'       => $c['level'],
            'amount'      => $p ? number_format((float)$p['amount'], 2) : '0.00',
            'original'    => $p ? number_format((float)$p['original_amount'], 2) : '0.00',
            'currency'    => $p['currency'] ?? 'USD',
            'coupon'      => $p['coupon_code'] ?? '',
            'discount'    => $p ? (float)$p['discount_percent'] : 0,
            'purchased_at'=> $p['purchased_at'] ?? '',
            'status'      => $p['status'] ?? 'completed',
            'purchase_id' => $p['id'] ?? '',
            'url'         => rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'nerdacademy.ai'), '/') . BASE . '/course.php?id=' . $cid,
        ];
    }
    echo json_encode($jsData);
?>;
</script>
<?php endif; ?>

<!-- Details Modal -->
<div id="modal-details" class="mc-modal-overlay" style="display:none" onclick="if(event.target===this)closeModals()">
    <div class="mc-modal">
        <div class="mc-modal-header">
            <h3>Purchase Details</h3>
            <button type="button" onclick="closeModals()" class="mc-modal-close">&times;</button>
        </div>
        <div id="modal-details-body" class="mc-modal-body"></div>
        <div class="mc-modal-footer">
            <button type="button" onclick="closeModals()" class="btn-ghost" style="padding:.45rem 1rem">Close</button>
        </div>
    </div>
</div>

<!-- Refund Modal -->
<div id="modal-refund" class="mc-modal-overlay" style="display:none" onclick="if(event.target===this)closeModals()">
    <div class="mc-modal">
        <div class="mc-modal-header">
            <h3>Request Refund</h3>
            <button type="button" onclick="closeModals()" class="mc-modal-close">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="mc-modal-body">
                <input type="hidden" name="request_refund" value="1">
                <input type="hidden" name="course_id" id="refund-course-id" value="">
                <p id="refund-course-name" style="font-weight:600;margin-bottom:.5rem"></p>
                <p style="font-size:.84rem;color:var(--text-muted);margin-bottom:1rem">
                    Please tell us why you'd like a refund. Our team will review your request within 2–3 business days. 
                    See our <a href="<?php echo BASE; ?>/refund-policy.php" target="_blank" style="color:var(--accent-primary)">refund policy</a>.
                </p>
                <label for="refund-reason" style="display:block;font-size:.82rem;font-weight:600;margin-bottom:.35rem">Reason for refund</label>
                <textarea name="refund_reason" id="refund-reason" rows="4" required placeholder="Please describe the reason for your refund request…" style="width:100%;border:1.5px solid var(--border);border-radius:8px;padding:.6rem .75rem;font-size:.88rem;font-family:inherit;resize:vertical;background:var(--bg-primary);color:var(--text-primary)"></textarea>
            </div>
            <div class="mc-modal-footer">
                <button type="button" onclick="closeModals()" class="btn-ghost" style="padding:.45rem 1rem">Cancel</button>
                <button type="submit" class="btn-primary" style="padding:.45rem 1rem">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<!-- Share Modal -->
<div id="modal-share" class="mc-modal-overlay" style="display:none" onclick="if(event.target===this)closeModals()">
    <div class="mc-modal" style="max-width:400px">
        <div class="mc-modal-header">
            <h3>Share Course</h3>
            <button type="button" onclick="closeModals()" class="mc-modal-close">&times;</button>
        </div>
        <div class="mc-modal-body">
            <p id="share-course-name" style="font-weight:600;margin-bottom:.75rem"></p>
            <div style="display:flex;gap:.5rem;margin-bottom:1rem">
                <input type="text" id="share-url" readonly style="flex:1;border:1.5px solid var(--border);border-radius:8px;padding:.5rem .65rem;font-size:.82rem;background:var(--bg-secondary);color:var(--text-primary);font-family:inherit">
                <button type="button" onclick="copyShareUrl()" class="btn-primary" id="btn-copy" style="padding:.5rem .75rem;font-size:.82rem;white-space:nowrap">Copy</button>
            </div>
            <div style="display:flex;gap:.6rem;justify-content:center">
                <a id="share-twitter" href="#" target="_blank" rel="noopener" class="mc-share-icon" title="Share on X" style="background:#000">
                    <svg width="18" height="18" fill="white" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                </a>
                <a id="share-linkedin" href="#" target="_blank" rel="noopener" class="mc-share-icon" title="Share on LinkedIn" style="background:#0A66C2">
                    <svg width="18" height="18" fill="white" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                </a>
                <a id="share-facebook" href="#" target="_blank" rel="noopener" class="mc-share-icon" title="Share on Facebook" style="background:#1877F2">
                    <svg width="18" height="18" fill="white" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
                <a id="share-whatsapp" href="#" target="_blank" rel="noopener" class="mc-share-icon" title="Share on WhatsApp" style="background:#25D366">
                    <svg width="18" height="18" fill="white" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($refundSuccess)): ?>
<script>alert('Your refund request has been submitted. Our team will review it within 2–3 business days.');</script>
<?php endif; ?>

<script>
function showDetails(cid) {
    var d = (__purchaseData || {})[cid];
    if (!d) return;
    var body = document.getElementById('modal-details-body');
    var rows = [
        ['Course', d.title],
        ['Category', d.category],
        ['Level', d.level],
        ['Purchase ID', '#' + d.purchase_id],
        ['Status', '<span style="color:var(--accent-green);font-weight:600;text-transform:capitalize">' + d.status + '</span>'],
        ['Date', new Date(d.purchased_at).toLocaleDateString('en-US', {year:'numeric',month:'long',day:'numeric'})],
        ['Original Price', '$' + d.original],
    ];
    if (d.coupon) {
        rows.push(['Coupon', d.coupon + ' (' + d.discount + '% off)']);
    }
    rows.push(['Amount Paid', '<strong>$' + d.amount + ' ' + d.currency + '</strong>']);
    body.innerHTML = '<table class="mc-details-table">' + rows.map(function(r){
        return '<tr><td style="color:var(--text-muted);font-size:.82rem;padding:.4rem 1rem .4rem 0;white-space:nowrap">' + r[0] + '</td><td style="font-size:.88rem;padding:.4rem 0">' + r[1] + '</td></tr>';
    }).join('') + '</table>';
    document.getElementById('modal-details').style.display = 'flex';
}

function showRefund(cid) {
    var d = (__purchaseData || {})[cid];
    if (!d) return;
    document.getElementById('refund-course-id').value = cid;
    document.getElementById('refund-course-name').textContent = d.title;
    document.getElementById('refund-reason').value = '';
    document.getElementById('modal-refund').style.display = 'flex';
}

function shareCourse(cid) {
    var d = (__purchaseData || {})[cid];
    if (!d) return;
    var url = d.url;
    var text = 'Check out this course: ' + d.title + ' on NerdAcademy!';
    document.getElementById('share-course-name').textContent = d.title;
    document.getElementById('share-url').value = url;
    document.getElementById('btn-copy').textContent = 'Copy';
    document.getElementById('share-twitter').href = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(text) + '&url=' + encodeURIComponent(url);
    document.getElementById('share-linkedin').href = 'https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(url);
    document.getElementById('share-facebook').href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
    document.getElementById('share-whatsapp').href = 'https://wa.me/?text=' + encodeURIComponent(text + ' ' + url);
    document.getElementById('modal-share').style.display = 'flex';
}

function copyShareUrl() {
    var el = document.getElementById('share-url');
    navigator.clipboard.writeText(el.value).then(function(){
        document.getElementById('btn-copy').textContent = 'Copied!';
        setTimeout(function(){ document.getElementById('btn-copy').textContent = 'Copy'; }, 2000);
    });
}

function closeModals() {
    document.getElementById('modal-details').style.display = 'none';
    document.getElementById('modal-refund').style.display = 'none';
    document.getElementById('modal-share').style.display = 'none';
}
document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeModals(); });
</script>

<style>
/* Action buttons */
.mc-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: .3rem .6rem;
    font-size: .76rem;
    font-weight: 600;
    font-family: inherit;
    border: 1.5px solid var(--border);
    border-radius: 7px;
    background: var(--bg-primary);
    color: var(--text-muted);
    cursor: pointer;
    transition: all .15s;
}
.mc-action-btn:hover {
    border-color: var(--accent-primary);
    color: var(--accent-primary);
    background: rgba(79,70,229,.05);
}
/* Modal overlay */
.mc-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    animation: mcFadeIn .15s;
}
@keyframes mcFadeIn { from { opacity: 0; } to { opacity: 1; } }
.mc-modal {
    background: var(--bg-primary);
    border-radius: 14px;
    box-shadow: 0 20px 60px rgba(0,0,0,.2);
    max-width: 480px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    animation: mcSlideUp .2s;
}
@keyframes mcSlideUp { from { transform: translateY(20px); opacity: 0; } }
.mc-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border);
}
.mc-modal-header h3 {
    font-size: 1rem;
    font-weight: 700;
}
.mc-modal-close {
    background: none;
    border: none;
    font-size: 1.4rem;
    cursor: pointer;
    color: var(--text-muted);
    line-height: 1;
    padding: 0 .2rem;
}
.mc-modal-body {
    padding: 1.25rem;
}
.mc-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: .5rem;
    padding: .75rem 1.25rem;
    border-top: 1px solid var(--border);
}
.mc-share-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    transition: opacity .15s;
}
.mc-share-icon:hover { opacity: .85; }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
