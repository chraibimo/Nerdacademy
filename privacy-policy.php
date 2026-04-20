<?php
$page_title = 'Privacy Policy';
$page_desc  = 'Learn how NerdAcademy collects, uses, and protects your personal data.';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ─── Hero ──────────────────────────────────────────────────────────────────── -->
<section class="about-hero" style="padding:4rem 0 3rem">
    <div class="about-hero-bg"></div>
    <div class="container">
        <div class="section-tag">Legal</div>
        <h1 class="about-hero-title" style="max-width:680px">Privacy <span class="gradient-text">Policy</span></h1>
        <p style="color:var(--text-muted);margin-top:.75rem">Last updated: April 7, 2026</p>
    </div>
</section>

<!-- ─── Content ───────────────────────────────────────────────────────────────── -->
<section class="section">
    <div class="container">
        <div class="policy-layout">

            <!-- Table of Contents -->
            <aside class="policy-toc">
                <h3>Contents</h3>
                <ol>
                    <li><a href="#information-we-collect">Information We Collect</a></li>
                    <li><a href="#how-we-use">How We Use Your Information</a></li>
                    <li><a href="#sharing">Sharing & Disclosure</a></li>
                    <li><a href="#cookies">Cookies & Tracking</a></li>
                    <li><a href="#data-retention">Data Retention</a></li>
                    <li><a href="#your-rights">Your Rights</a></li>
                    <li><a href="#security">Security</a></li>
                    <li><a href="#children">Children's Privacy</a></li>
                    <li><a href="#changes">Changes to This Policy</a></li>
                    <li><a href="#contact">Contact Us</a></li>
                </ol>
            </aside>

            <!-- Body -->
            <article class="policy-body">

                <p class="policy-intro">NerdAcademy ("we", "our", "us") is committed to protecting your privacy. This policy explains what data we collect when you use our website and services, why we collect it, and how you can exercise your rights.</p>

                <h2 id="information-we-collect">1. Information We Collect</h2>
                <h3>Information you provide directly</h3>
                <ul>
                    <li><strong>Account information:</strong> name, email address, and password when you register.</li>
                    <li><strong>Profile data:</strong> optional bio, avatar, and job title you add to your profile.</li>
                    <li><strong>Payment information:</strong> billing name and address. Card details are processed by Stripe and never stored on our servers.</li>
                    <li><strong>Communications:</strong> messages you send via our contact or support form.</li>
                </ul>
                <h3>Information collected automatically</h3>
                <ul>
                    <li>IP address, browser type, device type, and operating system.</li>
                    <li>Pages visited, time spent, and click events (via our analytics system).</li>
                    <li>Course progress, quiz results, and completion status.</li>
                </ul>

                <h2 id="how-we-use">2. How We Use Your Information</h2>
                <ul>
                    <li>To create and manage your account and deliver the courses you purchased.</li>
                    <li>To process payments and send receipts.</li>
                    <li>To issue certificates upon course completion.</li>
                    <li>To send transactional emails (password resets, enrolment confirmations).</li>
                    <li>To send our weekly newsletter, if you opted in — you can unsubscribe at any time.</li>
                    <li>To improve our platform by analysing aggregate, anonymised usage data.</li>
                    <li>To prevent fraud and abuse and comply with our legal obligations.</li>
                </ul>

                <h2 id="sharing">3. Sharing &amp; Disclosure</h2>
                <p>We do not sell your personal data. We share it only in these limited cases:</p>
                <ul>
                    <li><strong>Service providers:</strong> Stripe (payments), AWS (hosting), Postmark (email delivery). Each is bound by a data-processing agreement.</li>
                    <li><strong>Legal requirements:</strong> if required by law, court order, or to protect the rights and safety of NerdAcademy and its users.</li>
                    <li><strong>Business transfers:</strong> in the event of a merger or acquisition, your data may be transferred — you will be notified in advance.</li>
                </ul>

                <h2 id="cookies">4. Cookies &amp; Tracking</h2>
                <p>We use essential cookies to keep you logged in and remember your preferences. We also use analytics cookies to understand how the site is used. You can manage cookie preferences at any time — see our <a href="<?php echo BASE; ?>/cookie-policy.php">Cookie Policy</a> for full details.</p>

                <h2 id="data-retention">5. Data Retention</h2>
                <p>We retain your account data for as long as your account is active. If you delete your account, we erase your personal data within 30 days, except where we are required to keep it for legal or tax purposes (typically 7 years for billing records).</p>

                <h2 id="your-rights">6. Your Rights</h2>
                <p>Depending on your location you may have the right to:</p>
                <ul>
                    <li><strong>Access</strong> the personal data we hold about you.</li>
                    <li><strong>Correct</strong> inaccurate data via your profile settings.</li>
                    <li><strong>Delete</strong> your account and associated data.</li>
                    <li><strong>Export</strong> your data in a portable format.</li>
                    <li><strong>Opt out</strong> of marketing emails via the unsubscribe link in any email.</li>
                    <li><strong>Restrict</strong> or object to certain processing activities.</li>
                </ul>
                <p>To exercise any of these rights, email <strong>privacy@nerdacademy.ai</strong> and we will respond within 30 days.</p>

                <h2 id="security">7. Security</h2>
                <p>We use industry-standard measures including TLS encryption in transit, bcrypt password hashing, and regular security audits. No system is 100% secure; if you discover a vulnerability, please report it to <strong>security@nerdacademy.ai</strong>.</p>

                <h2 id="children">8. Children's Privacy</h2>
                <p>Our services are not directed to children under 13. If we become aware that a child under 13 has provided us with personal data, we will delete it promptly. If you believe this has occurred, please contact us.</p>

                <h2 id="changes">9. Changes to This Policy</h2>
                <p>We may update this policy from time to time. We will notify registered users by email and post a prominent notice on this page at least 14 days before material changes take effect. Continued use of our services after that date constitutes acceptance of the updated policy.</p>

                <h2 id="contact">10. Contact Us</h2>
                <p>For any privacy-related questions or requests:</p>
                <ul>
                    <li>Email: <strong>privacy@nerdacademy.ai</strong></li>
                    <li>Or use our <a href="<?php echo BASE; ?>/contact.php">contact form</a>.</li>
                </ul>

            </article>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
