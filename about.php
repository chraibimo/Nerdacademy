<?php
$page_title = 'About NerdAcademy — Who We Are';
$page_desc  = 'We\'re a small team of researchers and engineers who got tired of shallow AI content. Here\'s who we are and why we built NerdAcademy.';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/header.php';

$team = [
    ['name'=>'Dr. Evelyn Hart',  'role'=>'CEO & Co-Founder',         'color'=>'#7c3aed', 'initials'=>'EH', 'bio'=>'Former AI Research Director at DeepMind. PhD in Machine Learning from Cambridge.'],
    ['name'=>'Marcus Chen',      'role'=>'CTO & Co-Founder',         'color'=>'#0ea5e9', 'initials'=>'MC', 'bio'=>'Ex-Google Brain engineer. Built ML infrastructure at scale serving billions of users.'],
    ['name'=>'Dr. Aisha Kamara', 'role'=>'Head of Curriculum',       'color'=>'#10b981', 'initials'=>'AK', 'bio'=>'Published 40+ AI papers. Former professor at Stanford AI Lab. Passionate about education.'],
    ['name'=>'Jordan Walsh',     'role'=>'Head of Product',          'color'=>'#f59e0b', 'initials'=>'JW', 'bio'=>'10 years in EdTech product design. Built award-winning learning platforms used by 5M+ students.'],
    ['name'=>'Priya Nair',       'role'=>'Lead Instructor',          'color'=>'#ec4899', 'initials'=>'PN', 'bio'=>'NLP researcher, Hugging Face contributor. Makes complex AI concepts elegantly simple.'],
    ['name'=>'Dr. Leo Strauss',  'role'=>'AI Research Lead',         'color'=>'#ef4444', 'initials'=>'LS', 'bio'=>'Former OpenAI researcher. Specializes in LLMs, alignment, and generative models.'],
];

$values = [
    ['icon'=>'star',    'color'=>'#7c3aed', 'title'=>'No fluff, ever',             'text'=>'If a lesson doesn\'t make you better at building AI, it doesn\'t ship. We hold every piece of content to research-paper standards.'],
    ['icon'=>'users',   'color'=>'#0ea5e9', 'title'=>'We care about your outcome',  'text'=>'We don\'t measure success by completion rates. We measure it by what you actually do after you finish — the job you land, the product you build.'],
    ['icon'=>'globe',   'color'=>'#10b981', 'title'=>'Good education shouldn\'t be a privilege', 'text'=>'You shouldn\'t need a Stanford acceptance letter to learn this. We offer scholarships and support learners in 47 countries.'],
    ['icon'=>'refresh', 'color'=>'#f59e0b', 'title'=>'We keep up so you don\'t have to', 'text'=>'The AI field changes every few weeks. Our team updates courses continuously — so you\'re never learning yesterday\'s techniques.'],
];
?>

<!-- ─── About Hero ─────────────────────────────────────────────────────────── -->
<section class="about-hero">
    <div class="about-hero-bg"></div>
    <div class="container">
        <div class="about-hero-layout">
            <div class="about-hero-copy">
                <div class="section-tag">Who We Are</div>
                <h1 class="about-hero-title">We got tired of<br>shallow AI courses. So we<br>built <span class="gradient-text">something better</span>.</h1>
                <p class="about-hero-subtitle">NerdAcademy started in 2022 because most AI courses looked great on the surface and taught almost nothing useful. We built something different — deep, practical, and taught by people who actually build AI for a living.</p>
                <div class="about-hero-chips">
                    <span>120K+ learners</span>
                    <span>47 countries</span>
                    <span>92% reach their goal</span>
                </div>
            </div>
            <aside class="about-hero-panel">
                <h3>Honestly, here's what's different</h3>
                <ul>
                    <li>
                        <strong>Systems, not just tools</strong>
                        <span>We teach you how things work, not just how to click buttons.</span>
                    </li>
                    <li>
                        <strong>You build real things</strong>
                        <span>Every path includes projects you'd actually put in a portfolio.</span>
                    </li>
                    <li>
                        <strong>We update constantly</strong>
                        <span>The AI world changes fast. So does our curriculum.</span>
                    </li>
                </ul>
            </aside>
        </div>
    </div>
</section>

<!-- ─── Story + Milestones ────────────────────────────────────────────────── -->
<section class="section">
    <div class="container">
        <div class="about-story-layout">
            <div class="about-story-copy">
                <div class="section-tag">Our Mission</div>
                <h2 class="section-title about-left">Great AI education shouldn't require a Stanford acceptance letter.</h2>
                <p>AI is infrastructure now. Every industry runs on it. But the best training is still locked behind expensive programs and elite institutions — and that's broken.</p>
                <p>We built NerdAcademy to close that gap. Real academic depth, real hands-on projects, available to anyone with the drive to learn. You come in understanding concepts; you leave shipping models.</p>
            </div>

            <div class="about-timeline">
                <article class="about-milestone">
                    <span class="about-year">2022</span>
                    <h3>We got started</h3>
                    <p>A small group of researchers and engineers, fed up with shallow AI content, decided to build something worth their own time.</p>
                </article>
                <article class="about-milestone">
                    <span class="about-year">2024</span>
                    <h3>We went global</h3>
                    <p>Our learners were in 47 countries by this point. We launched scholarship programs so geography and economics wouldn't be a barrier.</p>
                </article>
                <article class="about-milestone">
                    <span class="about-year">Today</span>
                    <h3>120,000 people and growing</h3>
                    <p>Building AI careers, shipping products, starting companies. That's what this is all about.</p>
                </article>
            </div>
        </div>
    </div>
</section>

<!-- ─── Values ─────────────────────────────────────────────────────────────── -->
<section class="section section-dark">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">Values</div>
            <h2 class="section-title">How we actually operate</h2>
            <p class="section-subtitle">These aren't posters on a wall. They're the filters we run every lesson, feature, and decision through.</p>
        </div>
        <div class="about-values-grid">
            <?php foreach ($values as $v): ?>
            <article class="about-value-card">
                <div class="about-value-icon" style="--c:<?php echo $v['color']; ?>">
                    <?php if ($v['icon']==='star'): ?>
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <?php elseif ($v['icon']==='users'): ?>
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                    <?php elseif ($v['icon']==='globe'): ?>
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>
                    <?php else: ?>
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                    <?php endif; ?>
                </div>
                <h3><?php echo $v['title']; ?></h3>
                <p><?php echo $v['text']; ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ─── Team ───────────────────────────────────────────────────────────────── -->
<section class="section about-team-section">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">Experts</div>
            <h2 class="section-title">Experts Building World-Class AI Courses</h2>
            <p class="section-subtitle">Our team brings together researchers, engineers, and educators with deep real-world experience — all focused on creating practical AI learning that actually moves careers forward.</p>
        </div>

        <div class="about-team-grid">
            <?php foreach ($team as $member): ?>
            <article class="about-team-card">
                <div class="about-team-avatar" style="--member:<?php echo $member['color']; ?>">
                    <?php echo $member['initials']; ?>
                </div>
                <h3><?php echo $member['name']; ?></h3>
                <div class="about-team-role" style="--member:<?php echo $member['color']; ?>"><?php echo $member['role']; ?></div>
                <p><?php echo $member['bio']; ?></p>
            </article>
            <?php endforeach; ?>
            <article class="about-team-card about-team-hiring">
                <h3>Join our expert network</h3>
                <p>If you're an expert in building exceptional courses and want to help shape practical AI education, we'd love to connect with you. Fully remote.</p>
                <a href="<?php echo BASE; ?>/contact.php" class="btn-primary">Get in touch</a>
            </article>
        </div>
    </div>
</section>

<!-- ─── CTA ─────────────────────────────────────────────────────────────────── -->
<section class="cta-section">
    <div class="cta-bg"></div>
    <div class="container">
        <div class="cta-inner">
            <h2 class="cta-title">Everyone here started where you are.</h2>
            <p class="cta-subtitle">120,000 people took the first step. You can too. Come join us.</p>
            <div class="cta-actions">
                <a href="<?php echo BASE; ?>/courses.php" class="btn-primary btn-lg btn-white">Browse Courses</a>
                <a href="<?php echo BASE; ?>/contact.php" class="btn-ghost-white btn-lg">Talk to Us</a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
