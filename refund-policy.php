<?php
$page_title = 'Refund Policy';
$page_desc  = 'NerdAcademy\'s refund policy — 30-day money-back guarantee and everything you need to know about getting a refund.';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ─── Hero ──────────────────────────────────────────────────────────────────── -->
<section class="about-hero" style="padding:4rem 0 3rem">
    <div class="about-hero-bg"></div>
    <div class="container">
        <div class="section-tag">Legal</div>
        <h1 class="about-hero-title" style="max-width:680px">Refund <span class="gradient-text">Policy</span></h1>
        <p style="color:var(--text-muted);margin-top:.75rem">Last updated: April 7, 2026</p>
    </div>
</section>

<!-- ─── Highlight Banner ───────────────────────────────────────────────────────── -->
<section style="padding:2rem 0 0">
    <div class="container">
        <div class="policy-highlight">
            <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <div>
                <strong>30-Day Money-Back Guarantee</strong>
                <span>Not happy within 30 days of purchase? We'll refund you — no awkward questions.</span>
            </div>
        </div>
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
                    <li><a href="#eligibility">Eligibility</a></li>
                    <li><a href="#how-to-request">How to Request a Refund</a></li>
                    <li><a href="#processing">Processing & Timeline</a></li>
                    <li><a href="#bundles">Bundles & Subscriptions</a></li>
                    <li><a href="#exceptions">Exceptions</a></li>
                    <li><a href="#chargebacks">Chargebacks</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ol>
            </aside>

            <!-- Body -->
            <article class="policy-body">

                <p class="policy-intro">We stand behind our content. If a course isn't right for you, we'll make it right. Here's exactly how our refund process works.</p>

                <h2 id="eligibility">1. Eligibility</h2>
                <p>You are eligible for a full refund if <strong>all</strong> of the following are true:</p>
                <ul>
                    <li>You purchased the course directly on NerdAcademy.ai (not through a third-party platform).</li>
                    <li>Your refund request is submitted within <strong>30 calendar days</strong> of the purchase date.</li>
                    <li>You have completed fewer than <strong>30% of the course content</strong>.</li>
                </ul>
                <p>We reserve the right to deny refund requests that appear to be made in bad faith (e.g. repeated purchases and refund requests of the same course).</p>

                <h2 id="how-to-request">2. How to Request a Refund</h2>
                <ol>
                    <li>Email <strong>refunds@nerdacademy.ai</strong> with the subject line <em>"Refund Request — [your order number]"</em>.</li>
                    <li>Include your registered email address and the name of the course.</li>
                    <li>Optionally, let us know why — this helps us improve, but it is not required.</li>
                </ol>
                <p>You can also submit a request via our <a href="<?php echo BASE; ?>/contact.php">contact form</a> or by opening a support ticket from your account dashboard.</p>

                <h2 id="processing">3. Processing &amp; Timeline</h2>
                <ul>
                    <li>We will acknowledge your request within <strong>1 business day</strong>.</li>
                    <li>Approved refunds are issued within <strong>5 business days</strong> of approval.</li>
                    <li>The refund will be returned to your original payment method. Depending on your bank, it may take an additional 3–10 business days to appear on your statement.</li>
                    <li>Once a refund is issued, your access to the refunded course is revoked.</li>
                </ul>

                <h2 id="bundles">4. Bundles &amp; Subscriptions</h2>
                <ul>
                    <li><strong>Course bundles:</strong> Refunds on bundles are prorated — you will be refunded for any individual courses in the bundle that meet the eligibility criteria above.</li>
                    <li><strong>Promotional pricing:</strong> If you purchased at a discounted price, the refund reflects the amount you actually paid, not the list price.</li>
                </ul>

                <h2 id="exceptions">5. Exceptions — Non-Refundable Items</h2>
                <ul>
                    <li>Courses where more than 30% of the content has been accessed.</li>
                    <li>Certificates that have already been issued and downloaded.</li>
                    <li>Purchases made more than 30 days ago.</li>
                    <li>Courses purchased through third-party platforms (Udemy, Coursera, etc.) — please contact the platform directly.</li>
                    <li>Gift purchases where the recipient has already enrolled and accessed the content.</li>
                </ul>

                <h2 id="chargebacks">6. Chargebacks</h2>
                <p>If you initiate a chargeback with your bank before contacting us, we will be unable to process a refund through our system and may dispute the chargeback. Please reach out to us first — we'll almost always sort it out faster than a bank dispute.</p>

                <h2 id="contact">7. Contact</h2>
                <p>Refund questions? We're happy to help:</p>
                <ul>
                    <li>Email: <strong>refunds@nerdacademy.ai</strong></li>
                    <li><a href="<?php echo BASE; ?>/contact.php">Contact form</a></li>
                    <li><a href="<?php echo BASE; ?>/support.php">Support centre</a></li>
                </ul>

            </article>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
