<?php

if (!defined('BASE')) define('BASE', '');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/courses-repo.php';

$user = auth_current_user();
if (!$user || !auth_can_access_admin_panel($user)) {
    header('Location: ' . BASE . '/index.php');
    exit;
}

auth_ensure_rbac_tables();
ensure_courses_catalog_table($mysqli);

// Provide sensible defaults so pages that set these before including _head.php
// don't need to worry about PHP notices.
$admin_page_title  = $admin_page_title  ?? 'Admin';
$admin_active_page = $admin_active_page ?? '';

// Helper: return CSS class string for nav item
function nav_class(string $page, string $active): string {
    return 'a-nav-item' . ($active === $page ? ' active' : '');
}

// User display helpers
$_initials = strtoupper(substr($user['full_name'] ?? $user['email'] ?? 'A', 0, 2));
$_name     = htmlspecialchars($user['full_name'] ?? $user['email'] ?? 'Admin', ENT_QUOTES);
$_role     = htmlspecialchars($user['role'] ?? 'admin', ENT_QUOTES);
$adminCssPath = __DIR__ . '/admin.css';
$adminCssHref = BASE . '/admin/admin.css';
if (is_file($adminCssPath)) {
  $adminCssHref .= '?v=' . (string)filemtime($adminCssPath);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($admin_page_title, ENT_QUOTES) ?> â€” NerdAcademy Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars($adminCssHref, ENT_QUOTES) ?>">
</head>
<body>

<!-- =========================================================
     Sidebar
     ========================================================= -->
<aside class="a-sidebar" id="aSidebar">

  <!-- Logo -->
  <div class="a-sidebar-logo">
    <a href="<?= BASE ?>/admin/index.php" class="a-logo-link">
      <!-- Hexagonal NerdAcademy logo mark -->
      <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M16 2L28.124 9V23L16 30L3.876 23V9L16 2Z" fill="#6366f1"/>
        <path d="M11 11h2v4.5l4-4.5h2.5l-4.2 4.6L20 21h-2.5l-3.5-4.2V21H11V11Z" fill="#ffffff"/>
      </svg>
      <span class="a-logo-text">Nerd<span>Academy</span></span>
    </a>
  </div>

  <!-- â”€â”€ MAIN â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
  <div class="a-nav-label">Main</div>

  <!-- Dashboard -->
  <a href="<?= BASE ?>/admin/index.php" class="<?= nav_class('dashboard', $admin_active_page) ?>">
    <!-- Grid / squares icon -->
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <rect x="3" y="3" width="7" height="7" rx="1"/>
      <rect x="14" y="3" width="7" height="7" rx="1"/>
      <rect x="3" y="14" width="7" height="7" rx="1"/>
      <rect x="14" y="14" width="7" height="7" rx="1"/>
    </svg>
    Dashboard
  </a>

  <!-- Users -->
  <a href="<?= BASE ?>/admin/users.php" class="<?= nav_class('users', $admin_active_page) ?>">
    <!-- Users icon -->
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
      <circle cx="9" cy="7" r="4"/>
      <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
      <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
    </svg>
    Users
  </a>

  <!-- Courses -->
  <a href="<?= BASE ?>/admin/courses.php" class="<?= nav_class('courses', $admin_active_page) ?>">
    <!-- Book / open book icon -->
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
      <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
    </svg>
    Courses
  </a>

  <!-- Course Content -->
  <a href="<?= BASE ?>/admin/course-content.php" class="<?= nav_class('course-content', $admin_active_page) ?>">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <polygon points="23 7 16 12 23 17 23 7"/>
      <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
    </svg>
    Course Content
  </a>

  <!-- Quizzes -->
  <a href="<?= BASE ?>/admin/quizzes.php" class="<?= nav_class('quizzes', $admin_active_page) ?>">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="12" cy="12" r="10"/>
      <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
      <line x1="12" y1="17" x2="12.01" y2="17"/>
    </svg>
    Quizzes
  </a>

  <!-- Enrollments -->
  <a href="<?= BASE ?>/admin/enrollments.php" class="<?= nav_class('enrollments', $admin_active_page) ?>">
    <!-- Clipboard / list check icon -->
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
      <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
      <path d="M9 12l2 2 4-4"/>
    </svg>
    Enrollments
  </a>

  <!-- Bundles -->
  <a href="<?= BASE ?>/admin/bundles.php" class="<?= nav_class('bundles', $admin_active_page) ?>">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
    </svg>
    Bundles
  </a>

  <!-- Reviews -->
  <a href="<?= BASE ?>/admin/reviews.php" class="<?= nav_class('reviews', $admin_active_page) ?>">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
    </svg>
    Reviews
  </a>

  <!-- Comments -->
  <a href="<?= BASE ?>/admin/comments.php" class="<?= nav_class('comments', $admin_active_page) ?>">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
      <line x1="9" y1="10" x2="15" y2="10"/>
      <line x1="9" y1="14" x2="13" y2="14"/>
    </svg>
    Comments
  </a>

  <!-- â”€â”€ TOOLS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
  <div class="a-nav-label">Tools</div>

  <!-- Reports -->
  <a href="<?= BASE ?>/admin/reports.php" class="<?= nav_class('reports', $admin_active_page) ?>">
    <!-- Bar chart icon -->
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <line x1="18" y1="20" x2="18" y2="10"/>
      <line x1="12" y1="20" x2="12" y2="4"/>
      <line x1="6"  y1="20" x2="6"  y2="14"/>
      <line x1="2"  y1="20" x2="22" y2="20"/>
    </svg>
    Reports
  </a>

  <!-- Coupons -->
  <a href="<?= BASE ?>/admin/coupons.php" class="<?= nav_class('coupons', $admin_active_page) ?>">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M3 9V5a2 2 0 0 1 2-2h4"/>
      <path d="M21 15v4a2 2 0 0 1-2 2h-4"/>
      <path d="M14 3h5a2 2 0 0 1 2 2v5"/>
      <path d="M10 21H5a2 2 0 0 1-2-2v-5"/>
      <path d="M8 12h8"/>
      <path d="M12 8v8"/>
    </svg>
    Coupons
  </a>

  <!-- Tickets -->
  <a href="<?= BASE ?>/admin/tickets.php" class="<?= nav_class('tickets', $admin_active_page) ?>">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
    </svg>
    Tickets
  </a>

  <!-- â”€â”€ CONFIG â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
  <div class="a-nav-label">Config</div>

  <!-- Settings -->
  <a href="<?= BASE ?>/admin/settings.php" class="<?= nav_class('settings', $admin_active_page) ?>">
    <!-- Gear / cog icon -->
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="12" cy="12" r="3"/>
      <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33
               1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33
               l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4
               h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06
               A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51
               a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9
               a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
    </svg>
    Settings
  </a>

  <!-- â”€â”€ Sidebar bottom: user info â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
  <div class="a-sidebar-bottom">
    <div class="a-user-cell">
      <div class="a-user-avatar"><?= $_initials ?></div>
      <div>
        <div class="a-user-name"><?= $_name ?></div>
        <div class="a-user-email">
          <span class="a-badge a-badge--primary" style="font-size:.7rem;padding:.15rem .5rem;"><?= $_role ?></span>
        </div>
      </div>
    </div>
    <a href="<?= BASE ?>/logout.php" class="a-sidebar-signout">
      <!-- Logout arrow icon -->
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
           style="vertical-align:middle;margin-right:.3rem;" aria-hidden="true">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      Sign out
    </a>
  </div>

</aside>
<!-- /a-sidebar -->

<!-- =========================================================
     Main wrapper
     ========================================================= -->
<div class="a-main">

  <!-- Topbar -->
  <header class="a-topbar">

    <!-- Mobile hamburger -->
    <button class="a-sidebar-toggle" id="aSidebarToggle" aria-label="Toggle sidebar">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <line x1="3" y1="6"  x2="21" y2="6"/>
        <line x1="3" y1="12" x2="21" y2="12"/>
        <line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>

    <!-- Search -->
    <div class="a-search">
      <div class="a-search-wrap">
        <span class="a-search-icon" aria-hidden="true">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/>
            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
        </span>
        <input type="search" class="a-search-input" placeholder="Searchâ€¦" aria-label="Search admin">
      </div>
    </div>

    <!-- Right-side items -->
    <div class="a-topbar-right">
      <a href="<?= BASE ?>/index.php" class="a-btn a-btn--ghost a-btn--sm">&#8592; Back to Site</a>
      <button class="a-user-btn" type="button">
        <div class="a-user-avatar"><?= $_initials ?></div>
        <span><?= $_name ?></span>
      </button>
    </div>

  </header>
  <!-- /a-topbar -->

  <main class="a-content">
