<?php
$page_title = 'Contact Us';
$page_desc  = 'Get in touch with the NerdAcademy team — courses, support, partnerships and more.';

$success = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name))                                      $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))        $errors[] = 'A valid email address is required.';
    if (empty($subject))                                   $errors[] = 'Please select a subject.';
    if (strlen($message) < 20)                             $errors[] = 'Message must be at least 20 characters.';

    if (empty($errors)) {
        // TODO: connect to mail() or SMTP in production
        $success = true;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ─── Hero ──────────────────────────────────────────────────────────────────── -->
<section class="contact-hero">
    <div class="contact-hero-bg"></div>
    <div class="container">
        <div class="contact-hero-inner">
            <div class="contact-hero-text">
                <div class="section-tag animate-fade-up">Contact</div>
                <h1 class="contact-hero-title animate-fade-up delay-1">
                    Let's <span class="gradient-text">Talk</span>
                </h1>
                <p class="contact-hero-sub animate-fade-up delay-2">
                    Have a question, idea, or issue? We're a real team that actually reads every message.
                </p>
                <div class="contact-trust-row animate-fade-up delay-3">
                    <div class="contact-trust-item">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span>Replies within <strong>24 hours</strong></span>
                    </div>
                    <div class="contact-trust-item">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span><strong>98%</strong> satisfaction rate</span>
                    </div>
                    <div class="contact-trust-item">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                        <span>Real humans, no bots</span>
                    </div>
                </div>
            </div>
            <div class="contact-hero-visual animate-fade-up delay-2">
                <div class="contact-visual-card">
                    <div class="cvc-avatar-row">
                        <?php
                        $team = [['EM','#6366f1'],['AT','#0ea5e9'],['JL','#10b981'],['SR','#f59e0b']];
                        foreach ($team as [$init,$col]): ?>
                        <div class="cvc-avatar" style="background:<?php echo $col; ?>"><?php echo $init; ?></div>
                        <?php endforeach; ?>
                        <div class="cvc-avatar cvc-avatar-more">+8</div>
                    </div>
                    <div class="cvc-label">Our support team is online</div>
                    <div class="cvc-dot-row">
                        <span class="cvc-dot"></span> 12 members currently available
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ─── Main Content ──────────────────────────────────────────────────────────── -->
<section class="contact-main-section">
    <div class="container">
        <div class="contact-layout">

            <!-- ─── Form (left / main) ──────────────────────────────────────── -->
            <div class="contact-form-wrap">
                <?php if ($success): ?>
                <!-- Success State -->
                <div class="form-success">
                    <div class="success-icon">
                        <svg width="40" height="40" fill="none" stroke="#10b981" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <h3>Got it — we'll be in touch!</h3>
                    <p>Thanks for writing, <strong><?php echo htmlspecialchars($_POST['name'] ?? 'friend'); ?></strong>. We read every message ourselves, so expect a reply at <strong><?php echo htmlspecialchars($_POST['email'] ?? ''); ?></strong> within 24 hours.</p>
                    <a href="<?php echo BASE; ?>/contact.php" class="btn-primary" style="margin-top:1.5rem">Send another message</a>
                </div>

                <?php else: ?>
                <!-- Form -->
                <div class="contact-form-card">
                    <div class="contact-form-card-head">
                        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        <h2>Drop us a message</h2>
                    </div>

                    <?php if (!empty($errors)): ?>
                    <div class="form-errors">
                        <div style="display:flex;align-items:center;gap:.5rem;font-weight:700;margin-bottom:.5rem">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            Please fix the following:
                        </div>
                        <?php foreach ($errors as $err): ?>
                        <div class="form-error-item">— <?php echo htmlspecialchars($err); ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo BASE; ?>/contact.php" novalidate>
                        <div class="form-row-two">
                            <div class="form-group">
                                <label for="name">Full Name <span class="required">*</span></label>
                                <div class="input-wrap">
                                    <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    <input type="text" id="name" name="name"
                                        value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                        placeholder="Your full name"
                                        class="form-input has-icon"
                                        autocomplete="name">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <div class="input-wrap">
                                    <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                    <input type="email" id="email" name="email"
                                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                        placeholder="your@email.com"
                                        class="form-input has-icon"
                                        autocomplete="email">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="subject">What's this about? <span class="required">*</span></label>
                            <div class="subject-pills" id="subjectPills">
                                <?php
                                $subjects = ['Course Question','Technical Support','Billing','Partnership / B2B','Scholarship','Other'];
                                $posted   = $_POST['subject'] ?? '';
                                foreach ($subjects as $s): ?>
                                <button type="button"
                                    class="subject-pill <?php echo $posted === $s ? 'active' : ''; ?>"
                                    data-value="<?php echo htmlspecialchars($s); ?>">
                                    <?php echo htmlspecialchars($s); ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="subjectHidden" name="subject" value="<?php echo htmlspecialchars($posted); ?>">
                        </div>

                        <div class="form-group">
                            <label for="message">Your Message <span class="required">*</span></label>
                            <textarea id="message" name="message" rows="6"
                                placeholder="Tell us what's on your mind — the more detail, the better we can help…"
                                class="form-input form-textarea"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                            <div class="char-count"><span id="charCount">0</span> / 1000 characters</div>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="newsletter" value="1" <?php echo isset($_POST['newsletter']) ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                <span>Subscribe to our weekly AI insights newsletter</span>
                            </label>
                        </div>

                        <button type="submit" class="btn-primary btn-full btn-lg contact-submit-btn">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            Send Message
                        </button>
                        <p class="form-privacy">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                            We respect your privacy. Your info stays with us, always.
                        </p>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- ─── Sidebar (right) ─────────────────────────────────────────── -->
            <aside class="contact-sidebar">

                <!-- Contact channels -->
                <div class="contact-channels">
                    <h3 class="contact-sidebar-title">Other ways to reach us</h3>

                    <a href="mailto:hello@nerdacademy.ai" class="contact-channel-card">
                        <div class="cc-icon cc-icon--indigo">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </div>
                        <div class="cc-text">
                            <strong>Email Us</strong>
                            <span>hello@nerdacademy.ai</span>
                        </div>
                        <svg class="cc-arrow" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>

                    <a href="#" class="contact-channel-card">
                        <div class="cc-icon cc-icon--green">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                        </div>
                        <div class="cc-text">
                            <strong>Live Chat</strong>
                            <span>Mon–Fri · 9am–6pm EST</span>
                        </div>
                        <svg class="cc-arrow" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>

                    <a href="#" class="contact-channel-card">
                        <div class="cc-icon cc-icon--violet">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.317 4.37a19.791 19.791 0 00-4.885-1.515.074.074 0 00-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 00-5.487 0 12.64 12.64 0 00-.617-1.25.077.077 0 00-.079-.037A19.736 19.736 0 003.677 4.37a.07.07 0 00-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 00.031.057 19.9 19.9 0 005.993 3.03.078.078 0 00.084-.028 14.09 14.09 0 001.226-1.994.076.076 0 00-.041-.106 13.107 13.107 0 01-1.872-.892.077.077 0 01-.008-.128 10.2 10.2 0 00.372-.292.074.074 0 01.077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 01.078.01c.12.098.246.198.373.292a.077.077 0 01-.006.127 12.299 12.299 0 01-1.873.892.077.077 0 00-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 00.084.028 19.839 19.839 0 006.002-3.03.077.077 0 00.032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 00-.031-.03z"/></svg>
                        </div>
                        <div class="cc-text">
                            <strong>Discord Community</strong>
                            <span>12,000+ active members</span>
                        </div>
                        <svg class="cc-arrow" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>

                    <a href="#" class="contact-channel-card">
                        <div class="cc-icon cc-icon--amber">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <div class="cc-text">
                            <strong>Office Hours</strong>
                            <span>Live Q&A every Thursday 5pm EST</span>
                        </div>
                        <svg class="cc-arrow" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                </div>

                <!-- Response time promise -->
                <div class="response-promise">
                    <div style="display:flex;align-items:center;gap:.65rem;margin-bottom:.65rem">
                        <div style="width:36px;height:36px;background:var(--primary-bg);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;color:var(--primary);flex-shrink:0">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <strong style="font-size:.9rem;color:var(--text-primary)">Our Response Commitment</strong>
                    </div>
                    <p style="font-size:.83rem;color:var(--text-muted);line-height:1.7">We respond to every message within <strong style="color:var(--primary)">24 hours</strong> on weekdays. Complex technical issues may take up to 48 hours.</p>
                </div>

                <!-- Mini FAQ -->
                <div class="contact-faq">
                    <h3 class="contact-sidebar-title">Things people usually ask</h3>
                    <?php
                    $faqs = [
                        ['q' => 'Can I try before I buy?',
                         'a' => 'Absolutely. Every course has free preview lessons, and you get a full 7-day trial on any paid course — no card required upfront.'],
                        ['q' => 'What if I\'m not happy?',
                         'a' => 'We offer a 30-day money-back guarantee, no questions asked. Just reach out and we\'ll sort it out.'],
                        ['q' => 'Do my courses expire?',
                         'a' => 'Never. You buy once and keep access forever — including every update and new lesson we add.'],
                        ['q' => 'Got a team? We can help.',
                         'a' => 'Teams of 5 or more get significant discounts. Reach out via the Partnership option and we\'ll put something together.'],
                    ];
                    foreach ($faqs as $i => $f): ?>
                    <div class="faq-item" onclick="this.classList.toggle('open')">
                        <div class="faq-q">
                            <?php echo htmlspecialchars($f['q']); ?>
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                        <div class="faq-a"><?php echo htmlspecialchars($f['a']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

            </aside>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
// Subject pill selection
(function () {
  const pills  = document.querySelectorAll('.subject-pill');
  const hidden = document.getElementById('subjectHidden');
  if (!pills.length) return;
  pills.forEach(pill => {
    pill.addEventListener('click', function () {
      pills.forEach(p => p.classList.remove('active'));
      this.classList.add('active');
      if (hidden) hidden.value = this.dataset.value;
    });
  });
})();

// Character counter
(function () {
  const ta  = document.getElementById('message');
  const ctr = document.getElementById('charCount');
  if (!ta || !ctr) return;
  function update() {
    const len = ta.value.length;
    ctr.textContent = len;
    ctr.parentElement.style.color = len > 900 ? '#ef4444' : len > 700 ? '#f59e0b' : '';
    if (len > 1000) ta.value = ta.value.slice(0, 1000);
  }
  ta.addEventListener('input', update);
  update();
})();
</script>
