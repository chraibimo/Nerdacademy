<?php
if (!defined('BASE')) define('BASE', '');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/courses-repo.php';
require_once __DIR__ . '/includes/purchases-repo.php';
require_once __DIR__ . '/includes/coupons-repo.php';
require_once __DIR__ . '/includes/reviews-repo.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/wishlist-repo.php';
require_once __DIR__ . '/includes/checkout-orders-repo.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 1;
$course = find_course_by_id($mysqli, $id);

if (!$course) {
    header('Location: /ai-courses/courses.php');
    exit;
}

$page_title = $course['title'];
$page_desc  = $course['description'];

$currentUser = auth_current_user();
$enrollMessage = '';
$enrollError = false;
$reviewMessage = '';
$reviewError = false;

ensure_purchases_table($mysqli);
ensure_course_reviews_table($mysqli);
ensure_coupons_table($mysqli);

$isEnrolled = false;
if ($currentUser) {
    $isEnrolled = has_user_enrolled_course($mysqli, (int)$currentUser['id'], (int)$course['id']);
}

ensure_wishlist_table($mysqli);
$isWishlisted = false;
if ($currentUser) {
    $isWishlisted = is_wishlisted($mysqli, (int)$currentUser['id'], (int)$course['id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_course_id'])) {
    if (!$currentUser) {
        header('Location: ' . BASE . '/login.php');
        exit;
    }

    $postedCourseId = (int)$_POST['enroll_course_id'];
    if ($postedCourseId !== (int)$course['id']) {
        $enrollMessage = 'Invalid enrollment request.';
        $enrollError = true;
    } elseif (has_user_enrolled_course($mysqli, (int)$currentUser['id'], (int)$course['id'])) {
        $enrollMessage = 'You are already enrolled in this course.';
        $enrollError = false;
        $isEnrolled = true;
    } else {
        $couponCodeInput = trim((string)($_POST['coupon_code'] ?? ''));
        $couponCode = '';
        $discountPercent = 0.0;
        if ($couponCodeInput !== '') {
            $couponCheck = validate_coupon_code($mysqli, $couponCodeInput);
            if (!($couponCheck['ok'] ?? false)) {
                $enrollMessage = 'Coupon is invalid, inactive, expired, or fully used.';
                $enrollError = true;
            } else {
                $couponCode = normalize_coupon_code($couponCodeInput);
                $discountPercent = (float)($couponCheck['discount_percent'] ?? 0);
            }
        }

        if ($enrollMessage === '') {
            // Create a checkout order and redirect to payment page
            try {
                $clientId       = (int)$currentUser['id'];
                $courseId       = (int)$course['id'];
                $originalAmount = (float)$course['price'];
                $finalAmount    = round($originalAmount * (1 - ($discountPercent / 100)), 2);

                $orderId = create_checkout_order(
                    $mysqli,
                    $clientId,
                    $courseId,
                    $originalAmount,
                    $finalAmount,
                    $couponCode,
                    $discountPercent
                );

                header('Location: ' . BASE . '/checkout/?order_id=' . $orderId);
                exit;
            } catch (Throwable $e) {
                $enrollMessage = 'Unable to start checkout. Please try again.';
                $enrollError   = true;
            }
        }

    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!$currentUser) {
        $reviewMessage = 'Please sign in to submit a review.';
        $reviewError = true;
    } elseif (!has_user_enrolled_course($mysqli, (int)$currentUser['id'], (int)$course['id'])) {
        $reviewMessage = 'Only enrolled students can submit a review.';
        $reviewError = true;
    } else {
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $reviewText = trim((string)($_POST['review_text'] ?? ''));
        if ($reviewText === '') {
            $reviewMessage = 'Please write your review before submitting.';
            $reviewError = true;
        } else {
            $reviewerName = (string)($currentUser['full_name'] ?: 'Student');
            $reviewerEmail = (string)($currentUser['email'] ?? '');
            $stmt = $mysqli->prepare('INSERT INTO course_reviews (course_id, reviewer_name, reviewer_email, rating, review_text, status) VALUES (?, ?, ?, ?, ?, "pending")');
            if (!$stmt) {
                $reviewMessage = 'Unable to submit review right now.';
                $reviewError = true;
            } else {
                $courseId = (int)$course['id'];
                $stmt->bind_param('issis', $courseId, $reviewerName, $reviewerEmail, $rating, $reviewText);
                if ($stmt->execute()) {
                    $reviewMessage = 'Thanks. Your review was submitted and is pending approval.';
                    $reviewError = false;
                } else {
                    $reviewMessage = 'Unable to submit review. Please try again.';
                    $reviewError = true;
                }
                $stmt->close();
            }
        }
    }
}

$approvedReviews = [];
$stmtReviews = $mysqli->prepare('SELECT reviewer_name, rating, review_text, created_at FROM course_reviews WHERE course_id = ? AND status = "approved" ORDER BY created_at DESC LIMIT 8');
if ($stmtReviews) {
    $courseId = (int)$course['id'];
    $stmtReviews->bind_param('i', $courseId);
    if ($stmtReviews->execute()) {
        $result = $stmtReviews->get_result();
        while ($row = $result?->fetch_assoc()) {
            $approvedReviews[] = $row;
        }
    }
    $stmtReviews->close();
}

// Related courses (same category, different id)
$allCourses = load_all_courses($mysqli, true);
$related = array_filter($allCourses, fn($c) => $c['category'] === $course['category'] && $c['id'] !== $course['id']);
$related = array_slice($related, 0, 2);

require_once __DIR__ . '/includes/header.php';
?>

<!-- ─── Course Hero ─────────────────────────────────────────────────────────── -->
<section class="course-hero" style="--course-color:<?php echo $course['color']; ?>">
    <div class="course-hero-bg"></div>
    <div class="container">
        <div class="course-hero-layout">
            <div class="course-hero-main">
                <nav class="breadcrumb">
                    <a href="<?php echo BASE; ?>/index.php">Home</a>
                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    <a href="<?php echo BASE; ?>/courses.php">Courses</a>
                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    <span><?php echo $course['category']; ?></span>
                </nav>

                <div class="course-meta-top" style="margin-bottom:1rem">
                    <span class="course-cat"><?php echo $course['category']; ?></span>
                    <span class="course-level level-<?php echo strtolower($course['level']); ?>"><?php echo $course['level']; ?></span>
                    <?php if ($course['badge']): ?>
                        <span class="course-badge" style="position:static;font-size:.75rem"><?php echo $course['badge']; ?></span>
                    <?php endif; ?>
                </div>

                <h1 class="course-hero-title"><?php echo $course['title']; ?></h1>
                <p class="course-hero-subtitle"><?php echo $course['subtitle']; ?></p>

                <div class="course-hero-stats">
                    <div class="chs-item">
                        <svg width="16" height="16" fill="#f59e0b" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <strong><?php echo $course['rating']; ?></strong>
                        <span>(<?php echo number_format($course['reviews']); ?> reviews)</span>
                    </div>
                    <div class="chs-item">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                        <span><?php echo number_format($course['students']); ?> students</span>
                    </div>
                    <div class="chs-item">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span><?php echo $course['duration']; ?></span>
                    </div>
                    <div class="chs-item">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                        <span><?php echo $course['lessons']; ?> lessons</span>
                    </div>
                </div>

                <div class="course-instructor-row">
                    <div class="instructor-avatar-lg" style="background:<?php echo $course['color']; ?>"><?php echo substr($course['instructor'], 3, 2); ?></div>
                    <div>
                        <div style="font-size:.8rem;color:var(--text-muted)">Instructor</div>
                        <div style="font-weight:600"><?php echo $course['instructor']; ?></div>
                        <div style="font-size:.8rem;color:var(--text-muted)"><?php echo $course['instructor_title']; ?></div>
                    </div>
                </div>
            </div>

            <!-- Sticky Purchase Card -->
            <div class="course-purchase-card">
                <div class="purchase-preview">
                    <?php if (!empty($course['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars((string)$course['image_url']); ?>" alt="<?php echo htmlspecialchars((string)$course['title']); ?>" style="width:100%;height:180px;object-fit:cover;border-radius:12px">
                    <?php else: ?>
                    <div class="preview-icon" style="--c:<?php echo $course['color']; ?>">
                        <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>
                    </div>
                    <?php endif; ?>
                    <div class="preview-label">Course Preview</div>
                </div>
                <div class="purchase-body">
                    <div class="purchase-pricing">
                        <span class="purchase-price">$<?php echo $course['price']; ?></span>
                        <span class="purchase-old">$<?php echo $course['old_price']; ?></span>
                        <span class="purchase-discount"><?php echo (!empty($course['old_price']) && (float)$course['old_price'] > 0) ? round((1 - $course['price']/$course['old_price'])*100) . '% OFF' : ''; ?></span>
                    </div>
                    <div class="purchase-timer">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span id="offerTimer">2 days 14:32:11</span> left at this price!
                    </div>
                    <?php if ($currentUser): ?>
                    <?php if ((float)$course['price'] > 0 && !$isEnrolled): ?>
                    <form method="post" action="<?php echo BASE; ?>/checkout.php">
                        <input type="text" name="coupon_code" class="form-input" placeholder="Coupon code (optional)" style="margin-bottom:.6rem">
                        <input type="hidden" name="course_id" value="<?php echo (int)$course['id']; ?>">
                        <button class="btn-primary btn-full btn-lg" type="submit">Enroll Now — $<?php echo $course['price']; ?></button>
                    </form>
                    <?php else: ?>
                    <form method="post">
                        <?php if ((float)$course['price'] === 0.0): ?>
                        <input type="text" name="coupon_code" class="form-input" placeholder="Coupon code (optional)" style="margin-bottom:.6rem">
                        <?php endif; ?>
                        <input type="hidden" name="enroll_course_id" value="<?php echo $course['id']; ?>">
                        <button class="btn-primary btn-full btn-lg" type="submit" <?php echo $isEnrolled ? 'disabled style="opacity:.7;cursor:not-allowed"' : ''; ?>><?php echo $isEnrolled ? 'Already Enrolled' : 'Enroll Now — Free'; ?></button>
                    </form>
                    <?php endif; ?>
                    <?php else: ?>
                    <a class="btn-primary btn-full btn-lg" style="display:flex;justify-content:center" href="<?php echo BASE; ?>/login.php">Sign in to Enroll</a>
                    <?php endif; ?>
                    <button class="btn-ghost btn-full" style="margin-top:.75rem" onclick="alert('30-day money back guarantee!')">Try Free for 7 Days</button>
                    <?php if ($currentUser): ?>
                    <button id="wlDetailBtn" class="btn-ghost btn-full" style="margin-top:.5rem;display:flex;align-items:center;justify-content:center;gap:.4rem"
                        data-course-id="<?php echo (int)$course['id']; ?>"
                        data-wishlisted="<?php echo $isWishlisted ? '1' : '0'; ?>">
                        <span id="wlDetailHeart"><?php echo $isWishlisted ? '❤' : '♡'; ?></span>
                        <span id="wlDetailLabel"><?php echo $isWishlisted ? 'Saved to Wishlist' : 'Save to Wishlist'; ?></span>
                    </button>
                    <?php endif; ?>
                    <?php if ($enrollMessage): ?>
                    <p style="margin-top:.75rem;color:<?php echo $enrollError ? '#f87171' : 'var(--accent-green)'; ?>;font-size:.9rem"><?php echo htmlspecialchars($enrollMessage); ?></p>
                    <?php endif; ?>
                    <p class="purchase-guarantee">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        30-day money-back guarantee
                    </p>

                    <div class="purchase-includes">
                        <div class="pi-title">This course includes:</div>
                        <ul class="pi-list">
                            <li><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg><?php echo $course['lessons']; ?> on-demand video lessons</li>
                            <li><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>Downloadable resources & code</li>
                            <li><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>GPU-powered sandbox environment</li>
                            <li><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Lifetime access & updates</li>
                            <li><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>Certificate of completion</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ─── Course Content ──────────────────────────────────────────────────────── -->
<section class="section" style="padding-top:3rem">
    <div class="container">
        <div class="course-content-layout">
            <div class="course-content-main">

                <!-- What you'll learn -->
                <div class="content-block">
                    <h2 class="content-block-title">What You'll Learn</h2>
                    <div class="outcomes-grid">
                        <?php foreach ($course['outcomes'] as $outcome): ?>
                        <div class="outcome-item">
                            <svg width="16" height="16" fill="none" stroke="#10b981" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                            <span><?php echo $outcome; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Description -->
                <div class="content-block">
                    <h2 class="content-block-title">About This Course</h2>
                    <p style="color:var(--text-secondary);line-height:1.8;font-size:1.05rem"><?php echo $course['description']; ?></p>
                    <p style="color:var(--text-secondary);line-height:1.8;margin-top:1rem">This course has been meticulously designed to balance theory with practice. Each module builds on the last, ensuring you have a rock-solid foundation before advancing. You'll complete real projects that you can add to your portfolio, and have access to our community of <?php echo number_format($course['students']); ?>+ fellow learners.</p>
                </div>

                <!-- Curriculum -->
                <div class="content-block">
                    <h2 class="content-block-title">Course Curriculum</h2>
                    <div class="curriculum">
                        <?php foreach ($course['curriculum'] as $i => $module): ?>
                        <div class="curriculum-module" onclick="this.classList.toggle('open')">
                            <div class="cm-header">
                                <div class="cm-left">
                                    <div class="cm-num"><?php echo str_pad($i+1, 2, '0', STR_PAD_LEFT); ?></div>
                                    <span class="cm-title"><?php echo $module['title']; ?></span>
                                </div>
                                <div class="cm-right">
                                    <span class="cm-count"><?php echo $module['lessons']; ?> lessons</span>
                                    <svg class="cm-chevron" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                                </div>
                            </div>
                            <div class="cm-body">
                                <?php for ($j = 1; $j <= min($module['lessons'], 3); $j++): ?>
                                <div class="cm-lesson">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>
                                    <span>Lesson <?php echo $j; ?>: <?php echo $module['title']; ?> — Part <?php echo $j; ?></span>
                                </div>
                                <?php endfor; ?>
                                <?php if ($module['lessons'] > 3): ?>
                                    <div class="cm-lesson" style="color:var(--text-muted);font-style:italic">
                                        <span>+ <?php echo $module['lessons'] - 3; ?> more lessons</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Instructor -->
                <div class="content-block">
                    <h2 class="content-block-title">Your Instructor</h2>
                    <div class="instructor-card">
                        <div class="instructor-avatar-xl" style="background:<?php echo $course['color']; ?>"><?php echo substr($course['instructor'], 3, 2); ?></div>
                        <div class="instructor-info">
                            <h3 style="font-size:1.25rem;margin-bottom:.25rem"><?php echo $course['instructor']; ?></h3>
                            <p style="color:<?php echo $course['color']; ?>;font-size:.9rem;margin-bottom:.75rem"><?php echo $course['instructor_title']; ?></p>
                            <div class="instructor-stats">
                                <span><svg width="14" height="14" fill="#f59e0b" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg> <?php echo $course['rating']; ?> Rating</span>
                                <span><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> <?php echo number_format($course['students']); ?> Students</span>
                                <span><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg> 3 Courses</span>
                            </div>
                            <p style="color:var(--text-secondary);line-height:1.7;margin-top:.75rem">A leading expert in <?php echo $course['category']; ?> with over a decade of research and industry experience. Their work has been published in top-tier AI journals and they bring real-world insights that you simply can't find in textbooks.</p>
                        </div>
                    </div>
                </div>

                <!-- Tags -->
                <div class="content-block">
                    <h2 class="content-block-title">Technologies Covered</h2>
                    <div class="course-tags" style="gap:.6rem">
                        <?php foreach ($course['tags'] as $tag): ?>
                            <span class="course-tag" style="font-size:.9rem;padding:.4rem 1rem"><?php echo $tag; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- AI Course Assistant -->
                <div class="content-block">
                    <h2 class="content-block-title">AI Course Assistant</h2>
                    <div style="padding:1rem;border:1px solid var(--border);border-radius:16px;background:var(--bg-surface)">
                        <p style="margin:0;color:var(--text-secondary);line-height:1.7">Ask about the curriculum, prerequisites, certificate, or what you will build in this course.</p>

                        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin:1rem 0 .75rem">
                            <button type="button" class="btn-ghost" data-course-ai-prompt="Summarize this course">Summarize this course</button>
                            <button type="button" class="btn-ghost" data-course-ai-prompt="What will I learn in this course?">What will I learn?</button>
                            <button type="button" class="btn-ghost" data-course-ai-prompt="How do I earn the certificate?">How do I earn the certificate?</button>
                        </div>

                        <div id="courseAiMessages" style="display:grid;gap:.75rem;margin-bottom:.85rem">
                            <div style="padding:.85rem 1rem;border-radius:12px;background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.18);color:var(--text-secondary)">
                                Hi! I can help explain this course using its description, learning outcomes, and curriculum.
                            </div>
                        </div>

                        <textarea id="courseAiInput" class="form-input" rows="4" placeholder="Ask a question about this course..."></textarea>
                        <div style="display:flex;align-items:center;gap:.65rem;flex-wrap:wrap;margin-top:.75rem">
                            <button type="button" class="btn-primary" id="courseAiSend">Ask Assistant</button>
                            <span id="courseAiStatus" style="font-size:.85rem;color:var(--text-muted)">Tip: press Ctrl + Enter to send.</span>
                        </div>
                    </div>
                </div>

                <!-- Reviews -->
                <div class="content-block">
                    <h2 class="content-block-title">Student Reviews</h2>
                    <?php if ($reviewMessage): ?>
                    <p style="margin-bottom:1rem;color:<?php echo $reviewError ? '#f87171' : 'var(--accent-green)'; ?>"><?php echo htmlspecialchars($reviewMessage); ?></p>
                    <?php endif; ?>

                    <?php if ($currentUser && $isEnrolled): ?>
                    <form method="post" style="margin-bottom:1.2rem;padding:1rem;border:1px solid var(--border);border-radius:12px;background:var(--bg-surface)">
                        <input type="hidden" name="submit_review" value="1">
                        <div class="form-group" style="margin-bottom:.65rem">
                            <label for="review_rating">Your Rating</label>
                            <select id="review_rating" name="rating" class="form-input" style="max-width:180px">
                                <option value="5">5 - Excellent</option>
                                <option value="4">4 - Very good</option>
                                <option value="3">3 - Good</option>
                                <option value="2">2 - Fair</option>
                                <option value="1">1 - Poor</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="review_text">Your Review</label>
                            <textarea id="review_text" name="review_text" class="form-input" rows="4" placeholder="Share your experience with this course..." required></textarea>
                        </div>
                        <button type="submit" class="btn-primary">Submit Review</button>
                    </form>
                    <?php endif; ?>

                    <?php if (empty($approvedReviews)): ?>
                    <p style="color:var(--text-muted)">No approved reviews yet.</p>
                    <?php else: ?>
                    <div style="display:grid;gap:.85rem">
                        <?php foreach ($approvedReviews as $rv): ?>
                        <div style="padding:1rem;border:1px solid var(--border);border-radius:12px;background:var(--bg-surface)">
                            <div style="display:flex;justify-content:space-between;gap:.6rem;align-items:center">
                                <strong><?php echo htmlspecialchars((string)$rv['reviewer_name']); ?></strong>
                                <span style="color:#f59e0b"><?php echo str_repeat('★', (int)$rv['rating']); ?></span>
                            </div>
                            <p style="margin:.45rem 0 0 0;color:var(--text-secondary);line-height:1.7"><?php echo nl2br(htmlspecialchars((string)$rv['review_text'])); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Sidebar (desktop sticky) -->
            <div class="course-sidebar-sticky">
                <!-- Requirements -->
                <div class="sidebar-card">
                    <h3>Requirements</h3>
                    <ul class="sidebar-list">
                        <li>Basic programming knowledge (any language)</li>
                        <li>High school level mathematics</li>
                        <li>Curiosity and willingness to experiment</li>
                    </ul>
                </div>
                <!-- Course tags -->
                <div class="sidebar-card">
                    <h3>Share This Course</h3>
                    <div class="share-buttons">
                        <button class="share-btn" onclick="navigator.clipboard && navigator.clipboard.writeText(window.location.href)">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
                            Copy Link
                        </button>
                        <button class="share-btn">
                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231z"/></svg>
                            Twitter
                        </button>
                        <button class="share-btn">
                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286z"/></svg>
                            LinkedIn
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (count($related) > 0): ?>
<!-- ─── Related Courses ─────────────────────────────────────────────────────── -->
<section class="section section-dark" style="padding-top:3rem">
    <div class="container">
        <h2 class="content-block-title" style="margin-bottom:2rem">Related Courses</h2>
        <div class="courses-grid courses-grid-2">
            <?php foreach ($related as $rc): ?>
            <a href="<?php echo BASE; ?>/course.php?id=<?php echo $rc['id']; ?>" class="course-card" data-color="<?php echo $rc['color']; ?>">
                <div class="course-card-top" style="--c:<?php echo $rc['color']; ?>">
                    <?php if (!empty($rc['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars((string)$rc['image_url']); ?>" alt="<?php echo htmlspecialchars((string)$rc['title']); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:inherit">
                    <?php else: ?>
                    <div class="course-icon">
                        <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="course-card-body">
                    <div class="course-meta-top">
                        <span class="course-cat"><?php echo $rc['category']; ?></span>
                        <span class="course-level level-<?php echo strtolower($rc['level']); ?>"><?php echo $rc['level']; ?></span>
                    </div>
                    <h3 class="course-title"><?php echo $rc['title']; ?></h3>
                    <p class="course-desc"><?php echo $rc['subtitle']; ?></p>
                    <div class="course-footer">
                        <div class="course-rating">
                            <svg width="14" height="14" fill="#f59e0b" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <strong><?php echo $rc['rating']; ?></strong>
                        </div>
                        <span class="price-new">$<?php echo $rc['price']; ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<script>
function courseAiEscapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function courseAiAppend(role, text) {
    var wrap = document.getElementById('courseAiMessages');
    if (!wrap) return;
    var align = role === 'user' ? 'justify-content:flex-end' : 'justify-content:flex-start';
    var bg = role === 'user' ? 'background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.22)' : 'background:var(--bg-main);border:1px solid var(--border)';
    var html = '<div style="display:flex;' + align + '"><div style="max-width:100%;white-space:pre-wrap;line-height:1.7;padding:.85rem 1rem;border-radius:12px;' + bg + '">' + courseAiEscapeHtml(text) + '</div></div>';
    wrap.insertAdjacentHTML('beforeend', html);
}

async function askCourseAssistant(promptText) {
    var input = document.getElementById('courseAiInput');
    var status = document.getElementById('courseAiStatus');
    var question = (promptText || (input ? input.value : '') || '').trim();
    if (!question) return;

    if (input) input.value = '';
    if (status) status.textContent = 'Thinking...';
    courseAiAppend('user', question);

    try {
        var response = await fetch('<?= BASE ?>/api/course-assistant.php', {
            method: 'POST',
            credentials: 'include',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({course_id: <?= (int)$course['id'] ?>, question: question})
        });
        var data = await response.json();
        if (data && data.ok) {
            courseAiAppend('assistant', data.answer || 'I could not find a clear answer yet.');
            if (status) status.textContent = 'Answer ready.';
        } else {
            courseAiAppend('assistant', 'Sorry, I could not answer that just now.');
            if (status) status.textContent = 'Please try another question.';
        }
    } catch (err) {
        courseAiAppend('assistant', 'The assistant is temporarily unavailable. Please try again.');
        if (status) status.textContent = 'Request failed.';
    }
}

(function () {
    var btn = document.getElementById('wlDetailBtn');
    var heart = document.getElementById('wlDetailHeart');
    var label = document.getElementById('wlDetailLabel');
    if (btn && heart && label) {
        if (btn.dataset.wishlisted === '1') btn.style.color = '#ef4444';
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            var courseId = btn.dataset.courseId;
            var wasWl = btn.dataset.wishlisted === '1';
            // Optimistic
            btn.dataset.wishlisted = wasWl ? '0' : '1';
            heart.textContent = wasWl ? '♡' : '❤';
            label.textContent = wasWl ? 'Save to Wishlist' : 'Saved to Wishlist';
            btn.style.color = wasWl ? '' : '#ef4444';
            try {
                var fd = new FormData();
                fd.append('course_id', courseId);
                await fetch('/ai-courses/api/toggle-wishlist.php', {method:'POST', body:fd, credentials:'include'});
            } catch (err) {
                // Rollback
                btn.dataset.wishlisted = wasWl ? '1' : '0';
                heart.textContent = wasWl ? '❤' : '♡';
                label.textContent = wasWl ? 'Saved to Wishlist' : 'Save to Wishlist';
                btn.style.color = wasWl ? '#ef4444' : '';
            }
        });
    }

    var aiSend = document.getElementById('courseAiSend');
    var aiInput = document.getElementById('courseAiInput');
    if (aiSend && aiInput) {
        aiSend.addEventListener('click', function () {
            askCourseAssistant(aiInput.value);
        });
        aiInput.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                askCourseAssistant(aiInput.value);
            }
        });
    }

    document.querySelectorAll('[data-course-ai-prompt]').forEach(function (promptBtn) {
        promptBtn.addEventListener('click', function () {
            var prompt = promptBtn.getAttribute('data-course-ai-prompt') || '';
            askCourseAssistant(prompt);
        });
    });
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
