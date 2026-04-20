<?php
$page_title = 'Terms of Service';
$page_desc  = 'Read NerdAcademy\'s terms of service — the rules and conditions that govern your use of our platform.';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ─── Hero ──────────────────────────────────────────────────────────────────── -->
<section class="about-hero" style="padding:4rem 0 3rem">
    <div class="about-hero-bg"></div>
    <div class="container">
        <div class="section-tag">Legal</div>
        <h1 class="about-hero-title" style="max-width:680px">Terms of <span class="gradient-text">Service</span></h1>
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
                    <li><a href="#acceptance">Acceptance of Terms</a></li>
                    <li><a href="#accounts">Accounts</a></li>
                    <li><a href="#purchases">Purchases & Payments</a></li>
                    <li><a href="#license">License to Content</a></li>
                    <li><a href="#prohibited">Prohibited Conduct</a></li>
                    <li><a href="#ip">Intellectual Property</a></li>
                    <li><a href="#disclaimers">Disclaimers</a></li>
                    <li><a href="#liability">Limitation of Liability</a></li>
                    <li><a href="#termination">Termination</a></li>
                    <li><a href="#governing-law">Governing Law</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ol>
            </aside>

            <!-- Body -->
            <article class="policy-body">

                <p class="policy-intro">These Terms of Service ("Terms") govern your access to and use of NerdAcademy's website and online courses. By creating an account or purchasing a course, you agree to these Terms. Please read them carefully.</p>

                <h2 id="acceptance">1. Acceptance of Terms</h2>
                <p>By accessing or using NerdAcademy ("Service"), you confirm that you are at least 13 years old, that you have read and understood these Terms, and that you agree to be bound by them. If you are using the Service on behalf of an organisation, you represent that you are authorised to accept these Terms on its behalf.</p>

                <h2 id="accounts">2. Accounts</h2>
                <ul>
                    <li>You must provide accurate information when creating an account and keep it up to date.</li>
                    <li>You are responsible for maintaining the confidentiality of your password and for all activity that occurs under your account.</li>
                    <li>You must notify us immediately at <strong>support@nerdacademy.ai</strong> if you suspect unauthorised use of your account.</li>
                    <li>One account per person. You may not share your account credentials.</li>
                </ul>

                <h2 id="purchases">3. Purchases &amp; Payments</h2>
                <ul>
                    <li>All prices are listed in USD and include applicable taxes where required by law.</li>
                    <li>Payments are processed securely by Stripe. Your card details are never stored on our servers.</li>
                    <li>Upon successful payment, you receive a personal, non-transferable licence to access the purchased content.</li>
                    <li>For refunds, see our <a href="<?php echo BASE; ?>/refund-policy.php">Refund Policy</a>.</li>
                </ul>

                <h2 id="license">4. Licence to Content</h2>
                <p>When you purchase a course, NerdAcademy grants you a limited, personal, non-exclusive, non-transferable licence to access and view that course content for your own personal, non-commercial educational purposes. This licence does not include the right to:</p>
                <ul>
                    <li>Download, copy, or redistribute course videos, materials, or assessments.</li>
                    <li>Share your account or course access with any other person.</li>
                    <li>Use course content to train AI models or create derivative products.</li>
                    <li>Resell or sublicence any course content.</li>
                </ul>

                <h2 id="prohibited">5. Prohibited Conduct</h2>
                <p>You agree not to:</p>
                <ul>
                    <li>Use the Service for any unlawful purpose or in violation of these Terms.</li>
                    <li>Harass, threaten, or harm other users or our team.</li>
                    <li>Post spam, misleading information, or malicious code.</li>
                    <li>Attempt to gain unauthorised access to any part of our systems.</li>
                    <li>Scrape or systematically extract data from the Service without express written permission.</li>
                    <li>Circumvent, disable, or otherwise interfere with security features.</li>
                </ul>

                <h2 id="ip">6. Intellectual Property</h2>
                <p>All content on NerdAcademy — including course videos, slides, assessments, code examples, and the NerdAcademy name and logo — is owned by or licenced to NerdAcademy and is protected by copyright, trademark, and other laws. Nothing in these Terms transfers ownership of any intellectual property to you.</p>

                <h2 id="disclaimers">7. Disclaimers</h2>
                <p>The Service is provided "as is" and "as available" without warranty of any kind. We do not guarantee that:</p>
                <ul>
                    <li>The Service will be uninterrupted, error-free, or secure.</li>
                    <li>Any course will result in a job offer, certification from a third party, or specific learning outcome.</li>
                    <li>The content will always reflect the latest developments in a fast-moving field (though we do our best to keep it current).</li>
                </ul>

                <h2 id="liability">8. Limitation of Liability</h2>
                <p>To the maximum extent permitted by law, NerdAcademy and its officers, directors, employees, and agents shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including loss of profits, data, or goodwill, arising out of or in connection with your use of the Service. Our total liability to you for any claim shall not exceed the amount you paid us in the 12 months preceding the claim.</p>

                <h2 id="termination">9. Termination</h2>
                <p>We may suspend or terminate your account at any time if we reasonably believe you have violated these Terms. You may close your account at any time by contacting us. Upon termination, your licence to access course content ends, though provisions that by their nature should survive termination (including IP, liability, and governing law) will do so.</p>

                <h2 id="governing-law">10. Governing Law</h2>
                <p>These Terms are governed by the laws of the State of Delaware, USA, without regard to conflict of law principles. Any disputes shall be resolved by binding arbitration in accordance with the AAA Consumer Arbitration Rules, except that either party may seek injunctive relief in a court of competent jurisdiction.</p>

                <h2 id="contact">11. Contact</h2>
                <p>Questions about these Terms? Reach us at <strong>legal@nerdacademy.ai</strong> or via our <a href="<?php echo BASE; ?>/contact.php">contact form</a>.</p>

            </article>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
