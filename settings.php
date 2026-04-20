<?php
$page_title = 'Settings';
$page_desc  = 'Manage your NerdAcademy preferences and account options.';

if (!defined('BASE')) define('BASE', '');
require_once __DIR__ . '/includes/auth.php';

$user = auth_current_user();
if (!$user) {
    header('Location: ' . BASE . '/login.php');
    exit;
}

$displayName = trim((string)($user['full_name'] ?? ''));
if ($displayName === '') {
    $displayName = (string)($user['email'] ?? 'Student');
}
$initials = strtoupper(substr($displayName, 0, 2));

require_once __DIR__ . '/includes/header.php';
?>

<div class="settings-wrap">
    <div style="margin-bottom:2rem">
        <div class="section-tag" style="margin-bottom:.75rem">Preferences</div>
        <h1 style="font-family:'Space Grotesk',sans-serif;font-size:2rem;font-weight:800;color:var(--text-primary);letter-spacing:-.3px">Settings</h1>
        <p style="color:var(--text-muted);margin-top:.35rem">Manage your preferences, privacy, and quick account actions.</p>
    </div>

    <div class="settings-section">
        <div class="settings-section-head"><h2>Account</h2></div>
        <div class="settings-section-body">
            <div class="settings-avatar-wrap">
                <div class="settings-avatar"><?php echo htmlspecialchars($initials); ?></div>
                <div style="flex:1">
                    <div class="settings-row-label"><?php echo htmlspecialchars($displayName); ?></div>
                    <div class="settings-row-desc"><?php echo htmlspecialchars((string)($user['email'] ?? '')); ?></div>
                </div>
                <a href="<?php echo BASE; ?>/profile.php" class="btn-primary">Open Profile</a>
            </div>

            <div class="settings-row">
                <div>
                    <div class="settings-row-label">Profile details</div>
                    <div class="settings-row-desc">Change your display name and password.</div>
                </div>
                <a href="<?php echo BASE; ?>/profile.php" class="btn-ghost">Manage</a>
            </div>
            <div class="settings-row">
                <div>
                    <div class="settings-row-label">My courses</div>
                    <div class="settings-row-desc">Continue learning and track your enrolled courses.</div>
                </div>
                <a href="<?php echo BASE; ?>/my-courses.php" class="btn-ghost">Open</a>
            </div>
            <div class="settings-row">
                <div>
                    <div class="settings-row-label">Wishlist</div>
                    <div class="settings-row-desc">Review saved courses and bundles.</div>
                </div>
                <a href="<?php echo BASE; ?>/wishlist.php" class="btn-ghost">Open</a>
            </div>
        </div>
    </div>

    <div class="settings-section">
        <div class="settings-section-head"><h2>Preferences</h2></div>
        <div class="settings-section-body">
            <div class="settings-row">
                <div>
                    <div class="settings-row-label">Dark mode</div>
                    <div class="settings-row-desc">Use the saved theme across the site.</div>
                </div>
                <label class="toggle-switch" aria-label="Toggle dark mode preference">
                    <input type="checkbox" id="prefThemeDark">
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <div class="settings-row">
                <div>
                    <div class="settings-row-label">Autoplay lessons</div>
                    <div class="settings-row-desc">Remember your video playback preference on this device.</div>
                </div>
                <label class="toggle-switch" aria-label="Toggle autoplay preference">
                    <input type="checkbox" id="prefAutoplay">
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <div class="settings-row">
                <div>
                    <div class="settings-row-label">Learning reminders</div>
                    <div class="settings-row-desc">Keep a simple reminder preference saved in your browser.</div>
                </div>
                <label class="toggle-switch" aria-label="Toggle learning reminders preference">
                    <input type="checkbox" id="prefReminders">
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>
    </div>

    <div class="settings-section">
        <div class="settings-section-head"><h2>Support & Security</h2></div>
        <div class="settings-section-body">
            <div class="settings-row">
                <div>
                    <div class="settings-row-label">Password and security</div>
                    <div class="settings-row-desc">Change your password with current-password confirmation and a secure email code.</div>
                </div>
                <a href="<?php echo BASE; ?>/profile.php#password" class="btn-ghost">Update</a>
            </div>
            <div class="settings-row">
                <div>
                    <div class="settings-row-label">Need help?</div>
                    <div class="settings-row-desc">Contact support if you need account assistance.</div>
                </div>
                <a href="<?php echo BASE; ?>/support.php" class="btn-ghost">Support</a>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const darkToggle = document.getElementById('prefThemeDark');
    const autoplayToggle = document.getElementById('prefAutoplay');
    const reminderToggle = document.getElementById('prefReminders');

    if (darkToggle) {
        darkToggle.checked = (localStorage.getItem('na_theme') || document.documentElement.getAttribute('data-theme')) === 'dark';
        darkToggle.addEventListener('change', function () {
            const nextTheme = darkToggle.checked ? 'dark' : 'light';
            localStorage.setItem('na_theme', nextTheme);
            document.documentElement.setAttribute('data-theme', nextTheme);
        });
    }

    if (autoplayToggle) {
        autoplayToggle.checked = localStorage.getItem('na_autoplay') === '1';
        autoplayToggle.addEventListener('change', function () {
            localStorage.setItem('na_autoplay', autoplayToggle.checked ? '1' : '0');
        });
    }

    if (reminderToggle) {
        reminderToggle.checked = localStorage.getItem('na_learning_reminders') !== '0';
        reminderToggle.addEventListener('change', function () {
            localStorage.setItem('na_learning_reminders', reminderToggle.checked ? '1' : '0');
        });
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
