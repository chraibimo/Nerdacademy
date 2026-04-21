<?php
// Temporary diagnostic — DELETE AFTER USE
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "<h3>Debug My-Courses v2</h3>";
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
    echo "<p>Session user: " . ($user ? htmlspecialchars($user['email']) . ' (ID: ' . $user['id'] . ')' : 'NOT LOGGED IN') . "</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>Auth Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Grab first real client from DB to simulate logged-in path
$testRow = $mysqli->query("SELECT id, email FROM clients ORDER BY id ASC LIMIT 1")?->fetch_assoc();
$testId = $testRow ? (int)$testRow['id'] : 0;
echo "<p>Testing with client ID: $testId (" . htmlspecialchars($testRow['email'] ?? 'none') . ")</p>";

if ($testId <= 0) {
    echo "<p style='color:orange'>No clients in DB — cannot test logged-in path</p>";
} else {

    // Test courses-repo
    try {
        require_once __DIR__ . '/includes/courses-repo.php';
        $courses = load_all_courses($mysqli, true);
        echo "<p style='color:green'>Courses loaded: " . count($courses) . "</p>";
    } catch (Throwable $e) {
        echo "<p style='color:red'>Courses Error: " . htmlspecialchars($e->getMessage()) . " (line " . $e->getLine() . " in " . basename($e->getFile()) . ")</p>";
        exit;
    }

    // Test purchases-repo — enrolled IDs
    try {
        require_once __DIR__ . '/includes/purchases-repo.php';
        $purchasedIds = get_user_enrolled_course_ids($mysqli, $testId);
        echo "<p style='color:green'>get_user_enrolled_course_ids OK: " . count($purchasedIds) . " courses</p>";
    } catch (Throwable $e) {
        echo "<p style='color:red'>get_user_enrolled_course_ids Error: " . htmlspecialchars($e->getMessage()) . " (line " . $e->getLine() . " in " . basename($e->getFile()) . ")</p>";
        exit;
    }

    // Test progress map
    try {
        $progressMap = get_user_progress_map($mysqli, $testId);
        echo "<p style='color:green'>get_user_progress_map OK: " . count($progressMap) . " entries</p>";
    } catch (Throwable $e) {
        echo "<p style='color:red'>get_user_progress_map Error: " . htmlspecialchars($e->getMessage()) . " (line " . $e->getLine() . " in " . basename($e->getFile()) . ")</p>";
        exit;
    }

    // Test purchase map (new function)
    try {
        $purchaseMap = get_user_purchases_map($mysqli, $testId);
        echo "<p style='color:green'>get_user_purchases_map OK: " . count($purchaseMap) . " entries</p>";
    } catch (Throwable $e) {
        echo "<p style='color:red'>get_user_purchases_map Error: " . htmlspecialchars($e->getMessage()) . " (line " . $e->getLine() . " in " . basename($e->getFile()) . ")</p>";
        exit;
    }

    // Test streak-repo
    try {
        require_once __DIR__ . '/includes/streak-repo.php';
        ensure_streak_table($mysqli);
        $streak = get_user_streak($mysqli, $testId);
        echo "<p style='color:green'>streak-repo OK: streak=" . $streak['current_streak'] . "</p>";
    } catch (Throwable $e) {
        echo "<p style='color:red'>Streak Error: " . htmlspecialchars($e->getMessage()) . " (line " . $e->getLine() . " in " . basename($e->getFile()) . ")</p>";
        exit;
    }

    // Test certificates-repo
    try {
        require_once __DIR__ . '/includes/certificates-repo.php';
        $certMap = get_user_certificates_map($mysqli, $testId);
        echo "<p style='color:green'>certificates-repo OK: " . count($certMap) . " certs</p>";
    } catch (Throwable $e) {
        echo "<p style='color:red'>Certificates Error: " . htmlspecialchars($e->getMessage()) . " (line " . $e->getLine() . " in " . basename($e->getFile()) . ")</p>";
        exit;
    }

    // Test support-repo
    try {
        require_once __DIR__ . '/includes/support-repo.php';
        ensure_support_tickets_table($mysqli);
        echo "<p style='color:green'>support-repo OK</p>";
    } catch (Throwable $e) {
        echo "<p style='color:red'>Support Error: " . htmlspecialchars($e->getMessage()) . " (line " . $e->getLine() . " in " . basename($e->getFile()) . ")</p>";
        exit;
    }

    // Test header include (this triggers ob_start and session)
    echo "<p>Testing header include...</p>";
    try {
        ob_start();
        $page_title = 'Test';
        require_once __DIR__ . '/includes/header.php';
        ob_end_clean();
        echo "<p style='color:green'>header.php OK</p>";
    } catch (Throwable $e) {
        ob_end_clean();
        echo "<p style='color:red'>header.php Error: " . htmlspecialchars($e->getMessage()) . " (line " . $e->getLine() . " in " . basename($e->getFile()) . ")</p>";
        exit;
    }
}

echo "<h4 style='color:green'>All tests passed</h4>";
