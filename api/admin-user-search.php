<?php

if (!defined('BASE')) define('BASE', '');
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$user = auth_current_user();

// Must be logged-in admin with manage_users permission
if (!$user || !auth_has_permission($user, 'manage_users')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$email = trim((string)($_GET['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_email']);
    exit;
}

$stmt = $mysqli->prepare('SELECT id, full_name, email, role, account_status FROM clients WHERE email = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
    exit;
}

$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$found  = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$found) {
    echo json_encode(['ok' => false, 'error' => 'not_found']);
    exit;
}

echo json_encode([
    'ok'   => true,
    'user' => [
        'id'        => (int)$found['id'],
        'full_name' => $found['full_name'],
        'email'     => $found['email'],
        'role'      => $found['role'],
        'status'    => $found['account_status'],
    ],
]);
