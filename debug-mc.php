<?php
// Temporary diagnostic — DELETE AFTER USE
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "<h3>Debug My-Courses</h3>";
echo "<p>PHP: " . phpversion() . "</p>";

// Test DB
try {
    require_once __DIR__ . '/includes/db.php';
    echo "<p style='color:green'>DB connected OK</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>DB Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Test auth
try {
    require_once __DIR__ . '/includes/auth.php';
    $user = auth_current_user();
    echo "<p>User: " . ($user ? htmlspecialchars($user['email']) : 'NOT LOGGED IN') . "</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>Auth Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Test courses-repo
try {
    require_once __DIR__ . '/includes/courses-repo.php';
    $courses = load_all_courses($mysqli, true);
    echo "<p>Courses loaded: " . count($courses) . "</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>Courses Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Test purchases-repo
try {
    require_once __DIR__ . '/includes/purchases-repo.php';
    echo "<p style='color:green'>purchases-repo loaded OK</p>";
    if ($user) {
        $purchasedIds = get_user_enrolled_course_ids($mysqli, (int)$user['id']);
        echo "<p>Enrolled courses: " . count($purchasedIds) . "</p>";
        $purchaseMap = get_user_purchases_map($mysqli, (int)$user['id']);
        echo "<p>Purchase map: " . count($purchaseMap) . " entries</p>";
    }
} catch (Throwable $e) {
    echo "<p style='color:red'>Purchases Error: " . htmlspecialchars($e->getMessage()) . " at line " . $e->getLine() . " in " . $e->getFile() . "</p>";
    exit;
}

// Test streak-repo
try {
    require_once __DIR__ . '/includes/streak-repo.php';
    echo "<p style='color:green'>streak-repo loaded OK</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>Streak Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Test certificates-repo
try {
    require_once __DIR__ . '/includes/certificates-repo.php';
    echo "<p style='color:green'>certificates-repo loaded OK</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>Certificates Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Test support-repo
try {
    require_once __DIR__ . '/includes/support-repo.php';
    echo "<p style='color:green'>support-repo loaded OK</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>Support Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

echo "<h4 style='color:green'>All includes passed — my-courses.php should work</h4>";
