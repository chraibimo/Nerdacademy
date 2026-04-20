<?php
$page_title = 'Cookie Policy';
$page_desc  = 'How NerdAcademy uses cookies and similar technologies — and how you can control them.';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ─── Hero ──────────────────────────────────────────────────────────────────── -->
<section class="about-hero" style="padding:4rem 0 3rem">
    <div class="about-hero-bg"></div>
    <div class="container">
        <div class="section-tag">Legal</div>
        <h1 class="about-hero-title" style="max-width:680px">Cookie <span class="gradient-text">Policy</span></h1>
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
                    <li><a href="#what-are-cookies">What Are Cookies?</a></li>
                    <li><a href="#types">Cookies We Use</a></li>
                    <li><a href="#third-party">Third-Party Cookies</a></li>
                    <li><a href="#manage">Managing Your Preferences</a></li>
                    <li><a href="#changes">Changes to This Policy</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ol>
            </aside>

            <!-- Body -->
            <article class="policy-body">

                <p class="policy-intro">This Cookie Policy explains how NerdAcademy uses cookies and similar technologies when you visit our website. It should be read alongside our <a href="<?php echo BASE; ?>/privacy-policy.php">Privacy Policy</a>.</p>

                <h2 id="what-are-cookies">1. What Are Cookies?</h2>
                <p>Cookies are small text files placed on your device by a website. They allow the site to remember your actions and preferences over time, so you don't have to re-enter them each time you visit. Cookies can be:</p>
                <ul>
                    <li><strong>Session cookies</strong> — temporary; deleted when you close your browser.</li>
                    <li><strong>Persistent cookies</strong> — remain on your device until they expire or you delete them.</li>
                    <li><strong>First-party cookies</strong> — set by NerdAcademy.</li>
                    <li><strong>Third-party cookies</strong> — set by external services we use.</li>
                </ul>

                <h2 id="types">2. Cookies We Use</h2>

                <div class="policy-table-wrap">
                    <table class="policy-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Name</th>
                                <th>Purpose</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="cookie-badge cookie-essential">Essential</span></td>
                                <td><code>na_session</code></td>
                                <td>Keeps you logged in to your account</td>
                                <td>Session</td>
                            </tr>
                            <tr>
                                <td><span class="cookie-badge cookie-essential">Essential</span></td>
                                <td><code>na_csrf</code></td>
                                <td>Protects against cross-site request forgery attacks</td>
                                <td>Session</td>
                            </tr>
                            <tr>
                                <td><span class="cookie-badge cookie-functional">Functional</span></td>
                                <td><code>na_theme</code></td>
                                <td>Remembers your light/dark mode preference</td>
                                <td>1 year</td>
                            </tr>
                            <tr>
                                <td><span class="cookie-badge cookie-functional">Functional</span></td>
                                <td><code>na_lang</code></td>
                                <td>Remembers your language preference</td>
                                <td>1 year</td>
                            </tr>
                            <tr>
                                <td><span class="cookie-badge cookie-analytics">Analytics</span></td>
                                <td><code>_na_analytics</code></td>
                                <td>Measures page views, user journeys, and feature usage (anonymised)</td>
                                <td>13 months</td>
                            </tr>
                            <tr>
                                <td><span class="cookie-badge cookie-analytics">Analytics</span></td>
                                <td><code>_na_vid</code></td>
                                <td>Identifies unique visitors (no personal data stored)</td>
                                <td>13 months</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p><strong>Essential cookies</strong> are required for the site to function and cannot be disabled. All other categories are optional.</p>

                <h2 id="third-party">3. Third-Party Cookies</h2>
                <p>Some of our service providers may also set cookies on your device:</p>
                <ul>
                    <li><strong>Stripe</strong> — payment fraud detection. See <a href="https://stripe.com/privacy" target="_blank" rel="noopener">Stripe's Privacy Policy</a>.</li>
                    <li><strong>YouTube</strong> — if a course embeds a YouTube video, YouTube may set cookies when you play it. See <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google's Privacy Policy</a>.</li>
                    <li><strong>Intercom</strong> — our in-app chat widget. See <a href="https://www.intercom.com/legal/privacy" target="_blank" rel="noopener">Intercom's Privacy Policy</a>.</li>
                </ul>
                <p>We do not use advertising or retargeting cookies.</p>

                <h2 id="manage">4. Managing Your Preferences</h2>
                <p>You have several options for controlling cookies:</p>
                <ul>
                    <li><strong>Cookie banner:</strong> When you first visit NerdAcademy, you can accept or decline non-essential cookies via our consent banner.</li>
                    <li><strong>Browser settings:</strong> Most browsers let you block or delete cookies. Note that blocking essential cookies will prevent you from staying logged in.</li>
                    <li><strong>Opt-out links:</strong>
                        <ul>
                            <li>Analytics: email <strong>privacy@nerdacademy.ai</strong> with "Opt out of analytics" in the subject line.</li>
                        </ul>
                    </li>
                </ul>

                <div class="policy-callout">
                    <strong>Browser cookie guides:</strong>
                    <a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener">Chrome</a> ·
                    <a href="https://support.mozilla.org/en-US/kb/enhanced-tracking-protection-firefox-desktop" target="_blank" rel="noopener">Firefox</a> ·
                    <a href="https://support.apple.com/en-us/HT201265" target="_blank" rel="noopener">Safari</a> ·
                    <a href="https://support.microsoft.com/en-us/windows/manage-cookies-in-microsoft-edge-168dab11-0753-043d-7c16-ede5947fc64d" target="_blank" rel="noopener">Edge</a>
                </div>

                <h2 id="changes">5. Changes to This Policy</h2>
                <p>We may update this Cookie Policy as our use of cookies changes or regulations require. We will post a notice on this page and, for material changes, email registered users. The "Last updated" date at the top reflects the most recent revision.</p>

                <h2 id="contact">6. Contact</h2>
                <p>Questions about cookies? Contact us at <strong>privacy@nerdacademy.ai</strong> or via our <a href="<?php echo BASE; ?>/contact.php">contact form</a>.</p>

            </article>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
