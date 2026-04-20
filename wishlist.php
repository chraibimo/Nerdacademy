<?php
$page_title = 'My Wishlist';
$page_desc  = 'Courses you\'ve saved for later on NerdAcademy.';
if (!defined('BASE')) define('BASE', '');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/wishlist-repo.php';
require_once __DIR__ . '/includes/purchases-repo.php';

ensure_wishlist_table($mysqli);

$user           = auth_current_user();
$wishlistCourses = [];
$purchasedIds   = [];

if ($user) {
    $wishlistCourses = get_user_wishlist_courses($mysqli, (int)$user['id']);
    $purchasedIds    = get_user_enrolled_course_ids($mysqli, (int)$user['id']);
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ─── Wishlist Hero ────────────────────────────────────────────────────────── -->
<section class="my-courses-hero">
    <div class="container">
        <div class="section-tag">My Library</div>
        <div class="my-courses-header">
            <div>
                <h1>My <span class="gradient-text">Wishlist</span></h1>
                <p>Courses you've saved — ready whenever you are.</p>
            </div>
            <?php if ($user && !empty($wishlistCourses)): ?>
            <div class="enrolled-count" style="display:flex">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
                <span><?php echo count($wishlistCourses); ?></span>
                saved course<?php echo count($wishlistCourses) === 1 ? '' : 's'; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ─── Wishlist Content ─────────────────────────────────────────────────────── -->
<section class="section" style="padding-top:2.5rem">
    <div class="container">

        <?php if (!$user): ?>
        <!-- Not logged in -->
        <div class="auth-required-overlay">
            <div class="auth-required-box">
                <div class="empty-icon">
                    <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                </div>
                <h3>Sign in to view your wishlist</h3>
                <p>Save courses you love and come back to them at any time — sign in to get started.</p>
                <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
                    <a href="<?php echo BASE; ?>/login.php" class="btn-primary btn-lg">Sign In</a>
                    <a href="<?php echo BASE; ?>/register.php" class="btn-ghost btn-lg">Create Account</a>
                </div>
            </div>
        </div>

        <?php elseif (empty($wishlistCourses)): ?>
        <!-- Empty wishlist -->
        <div class="empty-courses">
            <div class="empty-icon">
                <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
            </div>
            <h3>No courses saved yet</h3>
            <p>Browse our catalogue and tap the heart on any course to save it here for later.</p>
            <a href="<?php echo BASE; ?>/courses.php" class="btn-primary btn-lg">Browse Courses</a>
        </div>

        <?php else: ?>
        <!-- Wishlist grid -->
        <div class="courses-grid" id="wishlistGrid">
            <?php foreach ($wishlistCourses as $course): ?>
            <?php
                $courseId   = (int)$course['id'];
                $isPurchased = in_array($courseId, $purchasedIds, true);
            ?>
            <div class="course-card wishlist-card" data-course-id="<?php echo $courseId; ?>">

                <!-- Card top image / icon area -->
                <div class="course-card-top" style="--c:<?php echo htmlspecialchars((string)$course['color']); ?>">
                    <?php if (!empty($course['image_url'])): ?>
                        <img
                            src="<?php echo htmlspecialchars((string)$course['image_url']); ?>"
                            alt="<?php echo htmlspecialchars((string)$course['title']); ?>"
                            style="width:100%;height:100%;object-fit:cover;border-radius:inherit"
                        >
                    <?php else: ?>
                        <div class="course-icon">
                            <?php if ($course['icon'] === 'brain'): ?>
                                <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 2C7 2 3 6 3 11c0 2.4 1 4.5 2.5 6L6 21h12l.5-4C20 15.5 21 13.4 21 11c0-5-4-9-9-9z"/><path d="M12 2v19M8 7c0 0 1 2 4 2s4-2 4-2M6 13c0 0 2 2 6 2s6-2 6-2"/></svg>
                            <?php elseif ($course['icon'] === 'network'): ?>
                                <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="5" cy="19" r="2"/><circle cx="19" cy="19" r="2"/><line x1="12" y1="7" x2="5" y2="17"/><line x1="12" y1="7" x2="19" y2="17"/><line x1="5" y1="19" x2="19" y2="19"/></svg>
                            <?php elseif ($course['icon'] === 'sparkles'): ?>
                                <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3z"/><path d="M5 3l.75 2.25L8 6l-2.25.75L5 9l-.75-2.25L2 6l2.25-.75L5 3z"/></svg>
                            <?php elseif ($course['icon'] === 'eye'): ?>
                                <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <?php elseif ($course['icon'] === 'chat'): ?>
                                <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                            <?php else: ?>
                                <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($course['badge']): ?>
                        <span class="course-badge"><?php echo htmlspecialchars((string)$course['badge']); ?></span>
                    <?php endif; ?>

                    <?php if ($isPurchased): ?>
                        <span class="wishlist-enrolled-badge">
                            <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                            Enrolled
                        </span>
                    <?php endif; ?>

                    <!-- Heart / Remove button -->
                    <button
                        class="wishlist-heart-btn active"
                        data-course-id="<?php echo $courseId; ?>"
                        title="Remove from wishlist"
                        aria-label="Remove from wishlist"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.5">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                        </svg>
                    </button>
                </div>

                <!-- Card body -->
                <div class="course-card-body">
                    <div class="course-meta-top">
                        <span class="course-cat"><?php echo htmlspecialchars((string)$course['category']); ?></span>
                        <span class="course-level level-<?php echo strtolower((string)$course['level']); ?>">
                            <?php echo htmlspecialchars((string)$course['level']); ?>
                        </span>
                    </div>

                    <h3 class="course-title"><?php echo htmlspecialchars((string)$course['title']); ?></h3>
                    <p class="course-desc"><?php echo htmlspecialchars((string)$course['subtitle']); ?></p>

                    <div class="course-stats-row">
                        <span>
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            <?php echo number_format((int)$course['students']); ?>
                        </span>
                        <span>
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <?php echo htmlspecialchars((string)$course['duration']); ?>
                        </span>
                        <span>
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                            <?php echo (int)$course['lessons']; ?> lessons
                        </span>
                    </div>

                    <div class="course-footer">
                        <div class="course-rating">
                            <svg width="14" height="14" fill="#f59e0b" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <strong><?php echo number_format((float)$course['rating'], 1); ?></strong>
                            <span>(<?php echo number_format((int)$course['reviews']); ?>)</span>
                        </div>
                        <div class="course-price">
                            <?php if ((float)$course['old_price'] > 0): ?>
                            <span class="price-old">$<?php echo number_format((float)$course['old_price'], 2); ?></span>
                            <?php endif; ?>
                            <span class="price-new">$<?php echo number_format((float)$course['price'], 2); ?></span>
                        </div>
                    </div>

                    <!-- Action button -->
                    <div style="margin-top:.25rem">
                        <?php if ($isPurchased): ?>
                            <a href="<?php echo BASE; ?>/course-player.php?course=<?php echo $courseId; ?>" class="btn-primary" style="display:flex;align-items:center;justify-content:center;gap:.45rem;padding:.55rem .9rem">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                Go to Course
                            </a>
                        <?php else: ?>
                            <a href="<?php echo BASE; ?>/course.php?id=<?php echo $courseId; ?>" class="btn-primary" style="display:flex;align-items:center;justify-content:center;gap:.45rem;padding:.55rem .9rem">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                                Enroll Now
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div><!-- /#wishlistGrid -->

        <!-- Browse more link -->
        <div style="text-align:center;margin-top:3rem">
            <a href="<?php echo BASE; ?>/courses.php" class="btn-ghost btn-lg" style="display:inline-flex;align-items:center;gap:.6rem">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                Browse More Courses
            </a>
        </div>

        <?php endif; ?>
    </div>
</section>

<!-- ─── Wishlist page styles ─────────────────────────────────────────────────── -->
<style>
/* Heart button on card-top */
.wishlist-heart-btn {
    position: absolute;
    bottom: .85rem;
    right: .85rem;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255,255,255,.92);
    border: 1px solid rgba(220,38,38,.2);
    color: #dc2626;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background var(--transition), transform var(--transition), box-shadow var(--transition);
    box-shadow: 0 2px 8px rgba(0,0,0,.1);
    z-index: 2;
    flex-shrink: 0;
}

.wishlist-heart-btn:hover {
    background: #fff;
    transform: scale(1.12);
    box-shadow: 0 4px 14px rgba(220,38,38,.22);
}

.wishlist-heart-btn:active {
    transform: scale(.94);
}

.wishlist-heart-btn.loading {
    opacity: .55;
    pointer-events: none;
    cursor: default;
}

/* Enrolled badge overlay on card-top */
.wishlist-enrolled-badge {
    position: absolute;
    top: .85rem;
    left: .85rem;
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .28rem .65rem;
    background: #ecfdf5;
    color: #059669;
    border: 1px solid rgba(5,150,105,.25);
    border-radius: 100px;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .03em;
    z-index: 2;
}

/* Fade-out animation when removing from wishlist */
@keyframes wishlistFadeOut {
    0%   { opacity: 1; transform: scale(1); }
    40%  { opacity: .6; transform: scale(.97); }
    100% { opacity: 0; transform: scale(.9); }
}

.wishlist-card.removing {
    animation: wishlistFadeOut .35s ease forwards;
    pointer-events: none;
}

/* Undo toast */
#wishlistToast {
    position: fixed;
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%) translateY(120%);
    background: var(--text-primary);
    color: #fff;
    padding: .75rem 1.5rem;
    border-radius: var(--radius-md);
    font-size: .9rem;
    font-weight: 500;
    box-shadow: 0 8px 28px rgba(0,0,0,.18);
    z-index: 9999;
    transition: transform .3s cubic-bezier(.4,0,.2,1);
    display: flex;
    align-items: center;
    gap: .85rem;
    white-space: nowrap;
}

#wishlistToast.visible {
    transform: translateX(-50%) translateY(0);
}

#wishlistToast .toast-msg {}

/* Dark mode adjustments */
[data-theme="dark"] .wishlist-heart-btn {
    background: rgba(30,31,48,.88);
    border-color: rgba(220,38,38,.3);
}

[data-theme="dark"] .wishlist-enrolled-badge {
    background: rgba(5,150,105,.15);
    border-color: rgba(5,150,105,.3);
}

[data-theme="dark"] #wishlistToast {
    background: #e0e1ff;
    color: #1e1f30;
}
</style>

<!-- ─── Wishlist JavaScript ───────────────────────────────────────────────────── -->
<script>
(function () {
    'use strict';

    var API = SITE_BASE + '/api/toggle-wishlist.php';
    var grid = document.getElementById('wishlistGrid');
    if (!grid) return;

    /* ── Toast helper ──────────────────────────────────────────────── */
    var toast = null;
    var toastTimer = null;

    function showToast(msg) {
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'wishlistToast';
            document.body.appendChild(toast);
        }
        toast.innerHTML = '<span class="toast-msg">' + msg + '</span>';
        toast.classList.add('visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            toast.classList.remove('visible');
        }, 3200);
    }

    /* ── Handle heart button clicks ────────────────────────────────── */
    grid.addEventListener('click', function (e) {
        var btn = e.target.closest('.wishlist-heart-btn');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();

        var card = btn.closest('.wishlist-card');
        var courseId = parseInt(btn.dataset.courseId, 10);
        if (!card || !courseId) return;

        // Optimistic UI: immediately fade the card out
        card.classList.add('removing');

        btn.classList.add('loading');

        // After the animation completes, hide the card
        setTimeout(function () {
            card.style.display = 'none';
            checkGridEmpty();
        }, 360);

        // Background API call
        var fd = new FormData();
        fd.append('course_id', courseId);

        fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.added) {
                    // Was re-added (shouldn't happen from this page, but handle gracefully)
                    card.classList.remove('removing');
                    card.style.display = '';
                    btn.classList.remove('loading');
                } else {
                    showToast('Course removed from your wishlist.');
                }
            })
            .catch(function () {
                // Rollback on network error
                card.classList.remove('removing');
                card.style.display = '';
                btn.classList.remove('loading');
                showToast('Something went wrong — please try again.');
            });
    });

    /* ── Show empty state when all cards removed ───────────────────── */
    function checkGridEmpty() {
        var visible = grid.querySelectorAll('.wishlist-card:not([style*="display: none"])');
        if (visible.length === 0) {
            grid.innerHTML =
                '<div class="empty-courses" style="grid-column:1/-1">' +
                    '<div class="empty-icon">' +
                        '<svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">' +
                            '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>' +
                        '</svg>' +
                    '</div>' +
                    '<h3>Your wishlist is empty</h3>' +
                    '<p>Browse our catalogue and save courses you\'d like to take.</p>' +
                    '<a href="' + SITE_BASE + '/courses.php" class="btn-primary btn-lg">Browse Courses</a>' +
                '</div>';
            // Also hide the count badge in the hero
            var countBadge = document.querySelector('.enrolled-count');
            if (countBadge) countBadge.style.display = 'none';
        }
    }

})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
