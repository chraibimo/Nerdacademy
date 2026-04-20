<?php
// Temporary debug page — DELETE after diagnosis
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h3>1. PHP is working</h3>";
echo "PHP version: " . phpversion() . "<br>";

echo "<h3>2. Environment variables</h3>";
echo "DB_HOST (getenv): " . var_export(getenv('DB_HOST'), true) . "<br>";
echo "DB_HOST (_SERVER): " . var_export($_SERVER['DB_HOST'] ?? 'NOT SET', true) . "<br>";
echo "DB_NAME (getenv): " . var_export(getenv('DB_NAME'), true) . "<br>";
echo "DB_NAME (_SERVER): " . var_export($_SERVER['DB_NAME'] ?? 'NOT SET', true) . "<br>";
echo "DB_USER (getenv): " . var_export(getenv('DB_USER'), true) . "<br>";
echo "DB_USER (_SERVER): " . var_export($_SERVER['DB_USER'] ?? 'NOT SET', true) . "<br>";

echo "<h3>3. DB Connection Test</h3>";
try {
    $host = getenv('DB_HOST') ?: ($_SERVER['DB_HOST'] ?? '127.0.0.1');
    $name = getenv('DB_NAME') ?: ($_SERVER['DB_NAME'] ?? 'ai_courses');
    $user = getenv('DB_USER') ?: ($_SERVER['DB_USER'] ?? 'root');
    $pass = getenv('DB_PASS') ?: ($_SERVER['DB_PASS'] ?? '');
    echo "Connecting as $user@$host to $name ...<br>";
    $m = new mysqli($host, $user, $pass, $name);
    if ($m->connect_errno) {
        echo "FAILED: " . $m->connect_error . "<br>";
    } else {
        echo "SUCCESS<br>";
        $m->close();
    }
} catch (Throwable $e) {
    echo "Exception: " . $e->getMessage() . "<br>";
}

echo "<h3>4. Include chain test</h3>";
try {
    require_once __DIR__ . '/includes/data.php';
    echo "data.php loaded OK<br>";
} catch (Throwable $e) {
    echo "data.php FAILED: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "<br>";
}

try {
    require_once __DIR__ . '/includes/header.php';
    echo "header.php loaded OK<br>";
} catch (Throwable $e) {
    echo "header.php FAILED: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "<br>";
}
