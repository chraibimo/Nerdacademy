<?php
$page_title = 'All AI Courses — Find Yours';
$page_desc  = 'Browse all AI, Machine Learning, and Deep Learning courses. Whether you\'re just starting out or going deeper — there\'s a course here built for you.';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/courses-repo.php';
require_once __DIR__ . '/includes/wishlist-repo.php';
ensure_wishlist_table($mysqli);
$wishlistIds = [];
$user = auth_current_user();
if ($user) {
    $wishlistIds = get_user_wishlist_ids($mysqli, (int)$user['id']);
}

$courses = load_all_courses($mysqli, true);
$categorySet = [];
foreach ($courses as $courseItem) {
    $cat = (string)($courseItem['category'] ?? 'General');
    if ($cat !== '') {
        $categorySet[$cat] = true;
    }
}
$categories = array_merge(['All'], array_keys($categorySet));

// Filters
$active_cat      = isset($_GET['cat']) ? (string)$_GET['cat'] : 'All';
$active_level    = isset($_GET['level']) ? (string)$_GET['level'] : 'All';
$sort            = isset($_GET['sort']) ? (string)$_GET['sort'] : 'popular';
$search          = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$priceFilter     = isset($_GET['price']) ? (string)$_GET['price'] : 'all';
$ratingFilter    = isset($_GET['rating']) ? (string)$_GET['rating'] : 'all';
$workloadFilter  = isset($_GET['workload']) ? (string)$_GET['workload'] : 'all';

$buildCourseQuery = static function (array $overrides = []) use ($active_cat, $active_level, $sort, $search, $priceFilter, $ratingFilter, $workloadFilter): string {
    $params = [
        'cat' => $active_cat,
        'level' => $active_level,
        'sort' => $sort,
        'q' => $search,
        'price' => $priceFilter,
        'rating' => $ratingFilter,
        'workload' => $workloadFilter,
    ];
    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }
    return http_build_query($params);
};

// Filter logic
$filtered = array_filter($courses, function($c) use ($active_cat, $active_level, $search, $priceFilter, $ratingFilter, $workloadFilter) {
    if ($active_cat !== 'All' && (string)($c['category'] ?? '') !== $active_cat) return false;
    if ($active_level !== 'All' && (string)($c['level'] ?? '') !== $active_level) return false;

    $haystack = strtolower(implode(' ', [
        (string)($c['title'] ?? ''),
        (string)($c['subtitle'] ?? ''),
        (string)($c['category'] ?? ''),
        (string)($c['instructor'] ?? ''),
        (string)($c['description'] ?? ''),
        implode(' ', array_map('strval', (array)($c['tags'] ?? []))),
    ]));
    if ($search !== '' && !str_contains($haystack, strtolower($search))) return false;

    $price = (float)($c['price'] ?? 0);
    if ($priceFilter === 'free' && $price > 0.0) return false;
    if ($priceFilter === 'paid' && $price <= 0.0) return false;
    if ($priceFilter === 'budget' && ($price <= 0.0 || $price > 50.0)) return false;
    if ($priceFilter === 'premium' && $price < 50.0) return false;

    $rating = (float)($c['rating'] ?? 0);
    if ($ratingFilter === '4' && $rating < 4.0) return false;
    if ($ratingFilter === '4.5' && $rating < 4.5) return false;

    $lessonsCount = (int)($c['lessons'] ?? 0);
    if ($workloadFilter === 'quick' && $lessonsCount > 15) return false;
    if ($workloadFilter === 'standard' && ($lessonsCount < 16 || $lessonsCount > 30)) return false;
    if ($workloadFilter === 'deep' && $lessonsCount < 31) return false;

    return true;
});

// Sort
usort($filtered, function($a, $b) use ($sort) {
    if ($sort === 'price_asc')  return $a['price'] <=> $b['price'];
    if ($sort === 'price_desc') return $b['price'] <=> $a['price'];
    if ($sort === 'rating')     return $b['rating'] <=> $a['rating'];
    if ($sort === 'lessons')    return $b['lessons'] <=> $a['lessons'];
    return $b['students'] <=> $a['students']; // popular
});

require_once __DIR__ . '/includes/header.php';
?>

<!-- ─── Page Hero ───────────────────────────────────────────────────────────── -->
<section class="page-hero">
    <div class="page-hero-bg"></div>
    <div class="container">
        <div class="page-hero-content">
            <div class="section-tag">Curriculum</div>
            <h1 class="page-title">Find <span class="gradient-text">your course</span></h1>
            <p class="page-subtitle">Whether you're just starting out or going deep — there's a path here built for exactly where you are right now.</p>
        </div>
    </div>
</section>

<!-- ─── Filters + Grid ─────────────────────────────────────────────────────── -->
<section class="section" style="padding-top:2rem">
    <div class="container">

        <!-- Search + Sort Bar -->
        <form method="GET" class="filter-bar">
            <input type="hidden" name="cat" value="<?php echo htmlspecialchars($active_cat); ?>">
            <div class="search-box">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search title, tag, instructor..." class="search-input">
            </div>
            <div class="filter-group">
                <label>Level</label>
                <select name="level" class="filter-select" onchange="this.form.submit()">
                    <option value="All" <?php echo $active_level==='All'?'selected':''; ?>>All Levels</option>
                    <option value="Beginner" <?php echo $active_level==='Beginner'?'selected':''; ?>>Beginner</option>
                    <option value="Intermediate" <?php echo $active_level==='Intermediate'?'selected':''; ?>>Intermediate</option>
                    <option value="Advanced" <?php echo $active_level==='Advanced'?'selected':''; ?>>Advanced</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Price</label>
                <select name="price" class="filter-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $priceFilter==='all'?'selected':''; ?>>All Prices</option>
                    <option value="free" <?php echo $priceFilter==='free'?'selected':''; ?>>Free</option>
                    <option value="budget" <?php echo $priceFilter==='budget'?'selected':''; ?>>Budget</option>
                    <option value="premium" <?php echo $priceFilter==='premium'?'selected':''; ?>>Premium</option>
                    <option value="paid" <?php echo $priceFilter==='paid'?'selected':''; ?>>Any Paid</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Rating</label>
                <select name="rating" class="filter-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $ratingFilter==='all'?'selected':''; ?>>Any Rating</option>
                    <option value="4" <?php echo $ratingFilter==='4'?'selected':''; ?>>4.0+</option>
                    <option value="4.5" <?php echo $ratingFilter==='4.5'?'selected':''; ?>>4.5+</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Workload</label>
                <select name="workload" class="filter-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $workloadFilter==='all'?'selected':''; ?>>Any Length</option>
                    <option value="quick" <?php echo $workloadFilter==='quick'?'selected':''; ?>>Quick Start</option>
                    <option value="standard" <?php echo $workloadFilter==='standard'?'selected':''; ?>>Standard</option>
                    <option value="deep" <?php echo $workloadFilter==='deep'?'selected':''; ?>>Deep Dive</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Sort by</label>
                <select name="sort" class="filter-select" onchange="this.form.submit()">
                    <option value="popular" <?php echo $sort==='popular'?'selected':''; ?>>Most Popular</option>
                    <option value="rating" <?php echo $sort==='rating'?'selected':''; ?>>Highest Rated</option>
                    <option value="lessons" <?php echo $sort==='lessons'?'selected':''; ?>>Most Lessons</option>
                    <option value="price_asc" <?php echo $sort==='price_asc'?'selected':''; ?>>Price: Low to High</option>
                    <option value="price_desc" <?php echo $sort==='price_desc'?'selected':''; ?>>Price: High to Low</option>
                </select>
            </div>
            <button type="submit" class="btn-primary">Apply</button>
        </form>

        <!-- Category Pills -->
        <div class="category-pills">
            <?php foreach ($categories as $cat): ?>
            <a href="?<?php echo htmlspecialchars($buildCourseQuery(['cat' => $cat]), ENT_QUOTES); ?>"
               class="category-pill <?php echo $active_cat === $cat ? 'active' : ''; ?>">
                <?php echo $cat; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Result Count -->
        <div class="result-info">
            <span><?php echo count($filtered); ?> course<?php echo count($filtered) !== 1 ? 's' : ''; ?> found</span>
            <?php if ($active_cat !== 'All' || $active_level !== 'All' || $search): ?>
                <a href="<?php echo BASE; ?>/courses.php" class="clear-filters">Clear filters</a>
            <?php endif; ?>
        </div>

        <!-- Courses Grid -->
        <?php if (count($filtered) > 0): ?>
        <div class="courses-grid">
            <?php foreach ($filtered as $course): ?>
            <a href="<?php echo BASE; ?>/course.php?id=<?php echo $course['id']; ?>" class="course-card" data-color="<?php echo $course['color']; ?>">
                <div class="course-card-top" style="--c:<?php echo $course['color']; ?>">
                    <?php if (!empty($course['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars((string)$course['image_url']); ?>" alt="<?php echo htmlspecialchars((string)$course['title']); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:inherit">
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
                        <span class="course-badge"><?php echo $course['badge']; ?></span>
                    <?php endif; ?>
                    <?php $isWl = in_array((int)$course['id'], $wishlistIds, true); ?>
                    <button class="wl-btn" data-course-id="<?php echo (int)$course['id']; ?>" data-wishlisted="<?php echo $isWl ? '1' : '0'; ?>" style="<?php echo $isWl ? 'color:#ef4444' : ''; ?>" title="<?php echo $isWl ? 'Remove from wishlist' : 'Add to wishlist'; ?>"><?php echo $isWl ? '❤' : '♡'; ?></button>
                </div>

                <div class="course-card-body">
                    <div class="course-meta-top">
                        <span class="course-cat"><?php echo $course['category']; ?></span>
                        <span class="course-level level-<?php echo strtolower($course['level']); ?>"><?php echo $course['level']; ?></span>
                    </div>
                    <h3 class="course-title"><?php echo $course['title']; ?></h3>
                    <p class="course-desc"><?php echo $course['subtitle']; ?></p>

                    <div class="course-stats-row">
                        <span><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> <?php echo number_format($course['students']); ?></span>
                        <span><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> <?php echo $course['duration']; ?></span>
                        <span><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg> <?php echo $course['lessons']; ?> lessons</span>
                    </div>

                    <div class="course-footer">
                        <div class="course-rating">
                            <svg width="14" height="14" fill="#f59e0b" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <strong><?php echo $course['rating']; ?></strong>
                            <span>(<?php echo number_format($course['reviews']); ?>)</span>
                        </div>
                        <div class="course-price">
                            <span class="price-old">$<?php echo $course['old_price']; ?></span>
                            <span class="price-new">$<?php echo $course['price']; ?></span>
                        </div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-results">
            <svg width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="color:var(--text-muted);margin-bottom:1rem"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <h3>Nothing matched those filters</h3>
            <p>Try loosening your search, or <a href="<?php echo BASE; ?>/courses.php">see everything we offer</a>.</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ─── CTA ─────────────────────────────────────────────────────────────────── -->
<section class="cta-section">
    <div class="cta-bg"></div>
    <div class="container">
        <div class="cta-inner">
            <h2 class="cta-title">Not sure where to begin? That's okay.</h2>
            <p class="cta-subtitle">Try any course free for 7 days. No commitment, no card needed.</p>
            <a href="#" class="btn-primary btn-lg btn-white">Start for Free</a>
        </div>
    </div>
</section>

<style>
.wl-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(0,0,0,0.45);
    border: none;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    cursor: pointer;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
    transition: transform 0.15s;
    z-index: 2;
    color: white;
}
.wl-btn:hover { transform: scale(1.15); }
.wl-btn[data-wishlisted="1"] { color: #ef4444; }
.course-card-top { position: relative; }
</style>
<script>
document.querySelectorAll('.wl-btn').forEach(btn => {
    btn.addEventListener('click', async e => {
        e.preventDefault(); e.stopPropagation();
        const courseId = btn.dataset.courseId;
        const wasWishlisted = btn.dataset.wishlisted === '1';
        // Optimistic update
        btn.dataset.wishlisted = wasWishlisted ? '0' : '1';
        btn.textContent = wasWishlisted ? '♡' : '❤';
        btn.style.color = wasWishlisted ? '' : '#ef4444';
        try {
            const fd = new FormData(); fd.append('course_id', courseId);
            await fetch('/ai-courses/api/toggle-wishlist.php', {method:'POST',body:fd,credentials:'include'});
        } catch(e) {
            // Rollback
            btn.dataset.wishlisted = wasWishlisted ? '1' : '0';
            btn.textContent = wasWishlisted ? '❤' : '♡';
            btn.style.color = wasWishlisted ? '#ef4444' : '';
        }
    });
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
