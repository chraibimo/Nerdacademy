<?php
$page_title = 'My Courses';
$page_desc  = 'Your purchased AI courses on NerdAcademy.';

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/courses-repo.php';
require_once __DIR__ . '/includes/purchases-repo.php';
require_once __DIR__ . '/includes/streak-repo.php';
require_once __DIR__ . '/includes/certificates-repo.php';

$courses = load_all_courses($mysqli, true);

$user = auth_current_user();
$purchasedIds = [];
$progressMap = [];
$certificateMap = [];
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
