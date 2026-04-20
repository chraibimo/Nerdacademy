<?php
$page_title = 'Learn AI the way it\'s actually built';
$page_desc  = 'Learn Machine Learning, Deep Learning, and Generative AI from people who ship AI for a living. Join 120,000+ learners who are building real things, not just finishing courses.';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ─── Hero Section ────────────────────────────────────────────────────────── -->
<section class="hero">
    <canvas id="neuralCanvas"></canvas>
    <div class="hero-gradient"></div>

    <div class="container hero-content">
        <div class="hero-badge animate-fade-up">
            <span class="badge-dot"></span>
            <span>Fresh: GPT-4o & Claude 3.5 courses just dropped</span>
        </div>

        <h1 class="hero-title animate-fade-up delay-1">
            Learn AI the way<br>
            <span class="gradient-text">it's actually built</span>
        </h1>

        <p class="hero-subtitle animate-fade-up delay-2">
            120,000+ people are already on this journey.<br>
            Come learn alongside them — no fluff, just AI that works.
        </p>

        <div class="hero-actions animate-fade-up delay-3">
            <a href="<?php echo BASE; ?>/courses.php" class="btn-primary btn-lg">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                Find My Course
            </a>
            <a href="#how-it-works" class="btn-ghost btn-lg">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M10 8l6 4-6 4V8z"/></svg>
                See how it works
            </a>
        </div>

        <div class="hero-trust animate-fade-up delay-4">
            <div class="hero-avatars">
                <?php
                $colors = ['#7c3aed','#0ea5e9','#10b981','#f59e0b'];
                $initials = ['JL','PS','CM','AT'];
                foreach ($initials as $i => $init): ?>
                    <div class="hero-avatar" style="background:<?php echo $colors[$i]; ?>"><?php echo $init; ?></div>
                <?php endforeach; ?>
            </div>
            <div>
                <div class="hero-stars">
                    <?php for ($i=0;$i<5;$i++): ?><svg width="14" height="14" fill="#f59e0b" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg><?php endfor; ?>
                </div>
                <span style="color:var(--text-muted);font-size:.85rem">4.9/5 — rated by 15,000+ real students</span>
            </div>
        </div>
    </div>

    <!-- Floating orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <!-- Scroll indicator -->
    <div class="scroll-indicator">
        <div class="scroll-dot"></div>
    </div>
</section>

<!-- ─── Stats Bar ───────────────────────────────────────────────────────────── -->
<section class="stats-bar">
    <div class="container">
        <div class="stats-grid">
            <?php foreach ($stats as $stat): ?>
            <div class="stat-item">
                <div class="stat-icon">
                    <?php if ($stat['icon'] === 'users'): ?>
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                    <?php elseif ($stat['icon'] === 'star'): ?>
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <?php elseif ($stat['icon'] === 'award'): ?>
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
                    <?php else: ?>
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="stat-value counter" data-target="<?php echo $stat['value']; ?>"><?php echo $stat['value']; ?></div>
                    <div class="stat-label"><?php echo $stat['label']; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ─── Featured Courses ────────────────────────────────────────────────────── -->
<section class="section" id="courses">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">Curriculum</div>
            <h2 class="section-title">Courses Built by People Who Ship AI</h2>
            <p class="section-subtitle">Each course is built by someone who's done this work for real — not just read about it. You learn what actually matters on the job.</p>
        </div>

        <div class="courses-grid">
            <?php foreach (array_slice($courses, 0, 6) as $course): ?>
            <a href="<?php echo BASE; ?>/course.php?id=<?php echo $course['id']; ?>" class="course-card" data-color="<?php echo $course['color']; ?>">
                <div class="course-card-top" style="--c:<?php echo $course['color']; ?>">
                    <div class="course-icon">
                        <?php if ($course['icon'] === 'brain'): ?>
                            <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 2C7 2 3 6 3 11c0 2.4 1 4.5 2.5 6L6 21h12l.5-4C20 15.5 21 13.4 21 11c0-5-4-9-9-9z"/><path d="M12 2v19M8 7c0 0 1 2 4 2s4-2 4-2M6 13c0 0 2 2 6 2s6-2 6-2"/></svg>
                        <?php elseif ($course['icon'] === 'network'): ?>
                            <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="5" cy="19" r="2"/><circle cx="19" cy="19" r="2"/><line x1="12" y1="7" x2="5" y2="17"/><line x1="12" y1="7" x2="19" y2="17"/><line x1="5" y1="19" x2="19" y2="19"/></svg>
                        <?php elseif ($course['icon'] === 'sparkles'): ?>
                            <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3z"/><path d="M5 3l.75 2.25L8 6l-2.25.75L5 9l-.75-2.25L2 6l2.25-.75L5 3z"/><path d="M19 14l.75 2.25L22 17l-2.25.75L19 20l-.75-2.25L16 17l2.25-.75L19 14z"/></svg>
                        <?php elseif ($course['icon'] === 'eye'): ?>
                            <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <?php elseif ($course['icon'] === 'chat'): ?>
                            <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                        <?php else: ?>
                            <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        <?php endif; ?>
                    </div>
                    <?php if ($course['badge']): ?>
                        <span class="course-badge"><?php echo $course['badge']; ?></span>
                    <?php endif; ?>
                </div>

                <div class="course-card-body">
                    <div class="course-meta-top">
                        <span class="course-cat"><?php echo $course['category']; ?></span>
                        <span class="course-level level-<?php echo strtolower($course['level']); ?>"><?php echo $course['level']; ?></span>
                    </div>
                    <h3 class="course-title"><?php echo $course['title']; ?></h3>
                    <p class="course-desc"><?php echo $course['subtitle']; ?></p>

                    <div class="course-tags">
                        <?php foreach (array_slice($course['tags'], 0, 3) as $tag): ?>
                            <span class="course-tag"><?php echo $tag; ?></span>
                        <?php endforeach; ?>
                    </div>

                    <div class="course-instructor">
                        <div class="instructor-avatar" style="background:<?php echo $course['color']; ?>"><?php echo substr($course['instructor'], 3, 2); ?></div>
                        <span><?php echo $course['instructor']; ?></span>
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

        <div style="text-align:center;margin-top:3rem">
            <a href="<?php echo BASE; ?>/courses.php" class="btn-primary btn-lg">Browse All Courses</a>
        </div>
    </div>
</section>

<!-- ─── How It Works ────────────────────────────────────────────────────────── -->
<section class="section section-dark" id="how-it-works">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">Process</div>
            <h2 class="section-title">Four Steps. Real Results.</h2>
            <p class="section-subtitle">No guesswork — just a clear path from where you are right now to where you want to be.</p>
        </div>

        <div class="steps-grid">
            <div class="step-card">
                <div class="step-number">01</div>
                <div class="step-icon" style="--c:#7c3aed">
                    <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </div>
                <h3>Pick what excites you</h3>
                <p>Six specializations. Whether you're brand new or already know some code — there's a path built exactly for you.</p>
            </div>
            <div class="step-connector"></div>
            <div class="step-card">
                <div class="step-number">02</div>
                <div class="step-icon" style="--c:#0ea5e9">
                    <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
                </div>
                <h3>Build, don't just watch</h3>
                <p>Every concept connects to a real project. You'll finish with working AI systems — not just a completion certificate.</p>
            </div>
            <div class="step-connector"></div>
            <div class="step-card">
                <div class="step-number">03</div>
                <div class="step-icon" style="--c:#10b981">
                    <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <h3>See yourself grow</h3>
                <p>Quizzes and milestones show you exactly how far you've come. Earn certificates that hiring managers actually recognize.</p>
            </div>
            <div class="step-connector"></div>
            <div class="step-card">
                <div class="step-number">04</div>
                <div class="step-icon" style="--c:#f59e0b">
                    <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <h3>Get the job (seriously)</h3>
                <p>We match you with real AI openings, run mock interviews, and make introductions to our hiring partners. This part is real.</p>
            </div>
        </div>
    </div>
</section>

<!-- ─── Features / Why Us ───────────────────────────────────────────────────── -->
<section class="section features-section">
    <div class="container">
        <div class="features-layout">
            <div class="features-left">
                <div class="section-tag">Why NerdAcademy</div>
                <h2 class="section-title" style="text-align:left">We do things differently.<br>Here's why it works.</h2>
                <p class="section-subtitle" style="text-align:left">Most courses give you theory and hope for the best. We give you the stuff that actually works on Monday morning — built by people who use it every day.</p>

                <div class="feature-list">
                    <div class="feature-item">
                        <div class="feature-check">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <div>
                            <strong>Made by people who actually do this</strong>
                            <p>Our instructors are active researchers from OpenAI, Google Brain, and DeepMind — not just lecturers. They ship the stuff they teach.</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-check">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <div>
                            <strong>Train real models, right in your browser</strong>
                            <p>No setup headaches. Our GPU sandbox lets you run actual training jobs without spending a cent on cloud bills.</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-check">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <div>
                            <strong>Ask a real person every week</strong>
                            <p>Live Q&A with instructors and TAs — every week, no ticket system, no waiting days for a reply.</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-check">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <div>
                            <strong>Buy once, learn forever</strong>
                            <p>AI moves fast. Your content gets updated as the field evolves — you'll never pay again to stay current.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="features-right">
                <div class="feature-visual">
                    <div class="visual-card visual-card-1">
                        <div class="vc-icon" style="--c:#7c3aed">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:.9rem">Training Accuracy</div>
                            <div style="color:var(--text-muted);font-size:.8rem">Model v3.2 — Epoch 47/100</div>
                        </div>
                        <div class="vc-value" style="color:#7c3aed">94.7%</div>
                    </div>
                    <div class="visual-card visual-card-2">
                        <div class="vc-icon" style="--c:#10b981">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:.9rem">Project Submitted</div>
                            <div style="color:var(--text-muted);font-size:.8rem">Image Classifier · Graded A+</div>
                        </div>
                    </div>
                    <div class="visual-card visual-card-3">
                        <div style="font-size:.85rem;font-weight:600;margin-bottom:.75rem;color:var(--text-muted)">Weekly Progress</div>
                        <div class="progress-bars">
                            <?php
                            $bars = [
                                ['label'=>'Python',      'val'=>92, 'color'=>'#7c3aed'],
                                ['label'=>'ML Models',   'val'=>78, 'color'=>'#0ea5e9'],
                                ['label'=>'PyTorch',     'val'=>65, 'color'=>'#10b981'],
                                ['label'=>'Deployment',  'val'=>45, 'color'=>'#f59e0b'],
                            ];
                            foreach ($bars as $b): ?>
                            <div class="pb-row">
                                <div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:.3rem">
                                    <span><?php echo $b['label']; ?></span>
                                    <span style="color:<?php echo $b['color']; ?>"><?php echo $b['val']; ?>%</span>
                                </div>
                                <div class="pb-track">
                                    <div class="pb-fill" style="width:<?php echo $b['val']; ?>%;background:<?php echo $b['color']; ?>"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="visual-bg-glow" style="background:#7c3aed"></div>
                    <div class="visual-bg-glow-2" style="background:#0ea5e9"></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ─── Testimonials ────────────────────────────────────────────────────────── -->
<section class="section section-dark testimonials-section">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">Testimonials</div>
            <h2 class="section-title">Real people. Real outcomes.</h2>
            <p class="section-subtitle">We didn't hand-pick the good ones. These are just honest stories from people who showed up and did the work.</p>
        </div>

        <div class="testimonials-grid">
            <?php foreach ($testimonials as $t): ?>
            <div class="testimonial-card">
                <div class="testimonial-quote">
                    <svg width="32" height="32" fill="currentColor" viewBox="0 0 32 32" style="color:<?php echo $t['color']; ?>;opacity:.4"><path d="M10 8H4v8h6v8H2V8a8 8 0 018-8v8zm18 0h-6v8h6v8h-8V8a8 8 0 018-8v8z"/></svg>
                </div>
                <p class="testimonial-text"><?php echo $t['text']; ?></p>
                <div class="testimonial-stars">
                    <?php for ($i=0;$i<$t['rating'];$i++): ?>
                        <svg width="14" height="14" fill="#f59e0b" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    <?php endfor; ?>
                </div>
                <div class="testimonial-author">
                    <div class="testimonial-avatar" style="background:<?php echo $t['color']; ?>"><?php echo $t['avatar']; ?></div>
                    <div>
                        <div class="testimonial-name"><?php echo $t['name']; ?></div>
                        <div class="testimonial-role"><?php echo $t['role']; ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ─── CTA Banner ──────────────────────────────────────────────────────────── -->
<section class="cta-section">
    <div class="cta-bg"></div>
    <div class="container">
        <div class="cta-inner">
            <h2 class="cta-title">Your first lesson is on us.</h2>
            <p class="cta-subtitle">No card needed. No commitment. Just start learning and see if it clicks.</p>
            <div class="cta-actions">
                <a href="<?php echo BASE; ?>/courses.php" class="btn-primary btn-lg btn-white">Start for Free</a>
                <a href="<?php echo BASE; ?>/about.php" class="btn-ghost-white btn-lg">About NerdAcademy</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
