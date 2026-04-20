<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
if (!defined('BASE')) define('BASE', '');
require_once __DIR__ . '/auth.php';
$currentUser = auth_current_user();
$isAdminUser = auth_is_admin($currentUser);
$canAccessAdminPanel = auth_can_access_admin_panel($currentUser);
$stylePath = __DIR__ . '/../assets/css/style.css';
$themePath = __DIR__ . '/../assets/js/theme.js';
$styleVersion = file_exists($stylePath) ? (string)filemtime($stylePath) : '1';
$themeVersion = file_exists($themePath) ? (string)filemtime($themePath) : '1';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' — NerdAcademy' : 'NerdAcademy — Learn AI from people who actually build it'; ?></title>
    <meta name="description" content="<?php echo isset($page_desc) ? $page_desc : 'Learn AI, Machine Learning, and Deep Learning from researchers and engineers who build it for a living. No fluff. Just the real thing.'; ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE; ?>/assets/css/style.css?v=<?php echo urlencode($styleVersion); ?>">
    <!-- Apply saved theme instantly to avoid flash -->
    <script>
      (function(){
        var t = localStorage.getItem('na_theme') || 'light';
        document.documentElement.setAttribute('data-theme', t);
      })();
    </script>
    <script>const SITE_BASE = '<?php echo BASE; ?>';</script>
</head>
<body>

<!-- ─── Navigation ─────────────────────────────────────────────────────────── -->
<nav class="navbar" id="navbar">
    <div class="nav-container">

        <!-- Logo -->
        <a href="<?php echo BASE; ?>/index.php" class="nav-logo">
            <div class="logo-icon">
                <!-- NerdAcademy hexagonal neural-N logo -->
                <svg width="26" height="26" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 2L36 11.5V28.5L20 38L4 28.5V11.5Z" fill="url(#naGrad)"/>
                    <!-- N formed by neural nodes + connections -->
                    <circle cx="12.5" cy="12" r="2.4" fill="white"/>
                    <circle cx="12.5" cy="28" r="2.4" fill="white"/>
                    <circle cx="27.5" cy="12" r="2.4" fill="white"/>
                    <circle cx="27.5" cy="28" r="2.4" fill="white"/>
                    <circle cx="20"   cy="19.5" r="1.7" fill="rgba(255,255,255,0.65)"/>
                    <line x1="12.5" y1="12" x2="12.5" y2="28" stroke="white" stroke-width="2"   stroke-opacity="0.9"/>
                    <line x1="12.5" y1="12" x2="27.5" y2="28" stroke="white" stroke-width="1.8" stroke-opacity="0.65"/>
                    <line x1="27.5" y1="12" x2="27.5" y2="28" stroke="white" stroke-width="2"   stroke-opacity="0.9"/>
                    <defs>
                        <linearGradient id="naGrad" x1="4" y1="2" x2="36" y2="38" gradientUnits="userSpaceOnUse">
                            <stop offset="0%"   stop-color="#6366f1"/>
                            <stop offset="100%" stop-color="#0ea5e9"/>
                        </linearGradient>
                    </defs>
                </svg>
            </div>
            <span class="logo-text">Nerd<span class="logo-accent">Academy</span></span>
        </a>

        <ul class="nav-links">
            <li><a href="<?php echo BASE; ?>/index.php"   class="nav-link <?php echo $current_page === 'index'   ? 'active' : ''; ?>">Home</a></li>
            <li><a href="<?php echo BASE; ?>/courses.php" class="nav-link <?php echo $current_page === 'courses'  ? 'active' : ''; ?>">Courses</a></li>
            <li><a href="<?php echo BASE; ?>/bundles.php" class="nav-link <?php echo $current_page === 'bundles'  ? 'active' : ''; ?>">Bundles</a></li>
            <li><a href="<?php echo BASE; ?>/about.php"   class="nav-link <?php echo $current_page === 'about'    ? 'active' : ''; ?>">About</a></li>
            <li><a href="<?php echo BASE; ?>/contact.php" class="nav-link <?php echo $current_page === 'contact'  ? 'active' : ''; ?>">Contact</a></li>
        </ul>

        <!-- Theme toggle -->
        <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode" title="Toggle dark/light mode">
            <!-- Sun (light mode icon) -->
            <svg class="icon-sun" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="5"/>
                <line x1="12" y1="1"  x2="12" y2="3"/>
                <line x1="12" y1="21" x2="12" y2="23"/>
                <line x1="4.22" y1="4.22"   x2="5.64" y2="5.64"/>
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                <line x1="1" y1="12" x2="3"  y2="12"/>
                <line x1="21" y1="12" x2="23" y2="12"/>
                <line x1="4.22" y1="19.78"  x2="5.64" y2="18.36"/>
                <line x1="18.36" y1="5.64"  x2="19.78" y2="4.22"/>
            </svg>
            <!-- Moon (dark mode icon) -->
            <svg class="icon-moon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
            </svg>
        </button>

        <?php if (!$currentUser): ?>
        <div class="nav-actions" id="navAuthBtns">
            <a href="<?php echo BASE; ?>/login.php"    class="btn-nav-outline">Sign In</a>
            <a href="<?php echo BASE; ?>/register.php" class="btn-nav-primary">Start Learning</a>
        </div>
        <?php else: ?>
        <div class="nav-user-area" id="navUserArea" style="display:flex">
            <div id="navUserToggle" style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
                <div class="nav-user-avatar" id="navUserAvatar"><?php echo strtoupper(substr((string)($currentUser['full_name'] ?: $currentUser['email']), 0, 2)); ?></div>
                <span class="nav-user-name" id="navUserName"><?php echo htmlspecialchars((string)($currentUser['full_name'] ?: strstr((string)$currentUser['email'], '@', true))); ?></span>
                <svg class="nav-user-caret" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="nav-user-dropdown" id="navUserDropdown">
                <a href="<?php echo BASE; ?>/my-courses.php">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                    My Courses
                </a>
                <a href="<?php echo BASE; ?>/wishlist.php">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                    Wishlist
                </a>
                <a href="<?php echo BASE; ?>/support.php">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Support
                </a>
                <a href="<?php echo BASE; ?>/profile.php">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Profile
                </a>
                <a href="<?php echo BASE; ?>/settings.php">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                    Settings
                </a>
                <?php if ($canAccessAdminPanel): ?>
                <a href="<?php echo BASE; ?>/admin/index.php" id="navAdminLink">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Admin Panel
                </a>
                <?php endif; ?>
                <div class="dropdown-divider"></div>
                <a href="<?php echo BASE; ?>/logout.php" class="logout-btn" id="logoutBtn" style="text-decoration:none">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Sign Out
                </a>
            </div>
        </div>
        <?php endif; ?>

        <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
    </div>

    <!-- Mobile menu -->
    <div class="nav-mobile" id="navMobile">
        <a href="<?php echo BASE; ?>/index.php"   class="nav-mobile-link <?php echo $current_page === 'index'   ? 'active' : ''; ?>">Home</a>
        <a href="<?php echo BASE; ?>/courses.php" class="nav-mobile-link <?php echo $current_page === 'courses'  ? 'active' : ''; ?>">Courses</a>
        <a href="<?php echo BASE; ?>/bundles.php" class="nav-mobile-link <?php echo $current_page === 'bundles'  ? 'active' : ''; ?>">Bundles</a>
        <a href="<?php echo BASE; ?>/about.php"   class="nav-mobile-link <?php echo $current_page === 'about'    ? 'active' : ''; ?>">About</a>
        <a href="<?php echo BASE; ?>/contact.php" class="nav-mobile-link <?php echo $current_page === 'contact'  ? 'active' : ''; ?>">Contact</a>
        <?php if ($currentUser): ?>
        <a href="<?php echo BASE; ?>/my-courses.php" class="nav-mobile-link">My Courses</a>
        <a href="<?php echo BASE; ?>/wishlist.php" class="nav-mobile-link">Wishlist</a>
        <a href="<?php echo BASE; ?>/profile.php" class="nav-mobile-link">Profile</a>
        <a href="<?php echo BASE; ?>/settings.php" class="nav-mobile-link">Settings</a>
        <a href="<?php echo BASE; ?>/support.php" class="nav-mobile-link">Support</a>
        <a href="<?php echo BASE; ?>/logout.php" class="nav-mobile-link">Sign Out</a>
        <?php else: ?>
        <a href="<?php echo BASE; ?>/login.php" class="nav-mobile-link">Sign In</a>
        <a href="<?php echo BASE; ?>/register.php" class="btn-nav-primary" style="margin-top:.5rem;display:inline-flex;justify-content:center">Start Learning Free</a>
        <?php endif; ?>
    </div>
</nav>

<!-- Load theme script early so toggle works -->
<script src="<?php echo BASE; ?>/assets/js/theme.js?v=<?php echo urlencode($themeVersion); ?>"></script>
