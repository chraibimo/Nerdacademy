<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function auth_ensure_rbac_tables(): void
{
    global $mysqli;

    $mysqli->query("CREATE TABLE IF NOT EXISTS permissions (
        permission_key VARCHAR(80) NOT NULL,
        label VARCHAR(140) NOT NULL,
        PRIMARY KEY (permission_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $mysqli->query("CREATE TABLE IF NOT EXISTS role_permissions (
        role VARCHAR(30) NOT NULL,
        permission_key VARCHAR(80) NOT NULL,
        PRIMARY KEY (role, permission_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $mysqli->query("CREATE TABLE IF NOT EXISTS user_permissions (
        client_id BIGINT UNSIGNED NOT NULL,
        permission_key VARCHAR(80) NOT NULL,
        allowed TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (client_id, permission_key),
        KEY idx_user_permissions_key (permission_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $defaults = [
        'panel_access' => 'Access Admin Panel',
        'manage_users' => 'Manage Users and Roles',
        'manage_permissions' => 'Manage Permissions',
        'manage_courses' => 'Create and Edit Courses',
        'view_analytics' => 'View Dashboard Analytics',
    ];

    $stmtPerm = $mysqli->prepare('INSERT IGNORE INTO permissions (permission_key, label) VALUES (?, ?)');
    if ($stmtPerm) {
        foreach ($defaults as $key => $label) {
            $stmtPerm->bind_param('ss', $key, $label);
            $stmtPerm->execute();
        }
        $stmtPerm->close();
    }

    $adminPerms = array_keys($defaults);
    $agentPerms = ['panel_access', 'manage_courses', 'view_analytics'];

    $stmtRole = $mysqli->prepare('INSERT IGNORE INTO role_permissions (role, permission_key) VALUES (?, ?)');
    if ($stmtRole) {
        foreach ($adminPerms as $perm) {
            $role = 'admin';
            $stmtRole->bind_param('ss', $role, $perm);
            $stmtRole->execute();
        }
        foreach ($agentPerms as $perm) {
            $role = 'agent';
            $stmtRole->bind_param('ss', $role, $perm);
            $stmtRole->execute();
        }
        $stmtRole->close();
    }
}

function auth_current_user(): ?array
{
    global $mysqli;

    $clientId = isset($_SESSION['client_id']) ? (int)$_SESSION['client_id'] : 0;
    if ($clientId <= 0) {
        return null;
    }

    $stmt = $mysqli->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $clientId);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }

    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$user) {
        unset($_SESSION['client_id']);
        return null;
    }

    return $user;
}

function auth_is_admin(?array $user): bool
{
    if (!$user) return false;
    return (int)($user['is_admin'] ?? 0) === 1 || ($user['role'] ?? '') === 'admin';
}

function auth_has_permission(?array $user, string $permissionKey): bool
{
    global $mysqli;

    if (!$user || $permissionKey === '') {
        return false;
    }

    if (auth_is_admin($user)) {
        return true;
    }

    auth_ensure_rbac_tables();

    $clientId = (int)($user['id'] ?? 0);
    $role = (string)($user['role'] ?? 'user');

    if ($clientId <= 0) {
        return false;
    }

    $stmtUser = $mysqli->prepare('SELECT allowed FROM user_permissions WHERE client_id = ? AND permission_key = ? LIMIT 1');
    if ($stmtUser) {
        $stmtUser->bind_param('is', $clientId, $permissionKey);
        if ($stmtUser->execute()) {
            $result = $stmtUser->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmtUser->close();
            if ($row !== null) {
                return (int)($row['allowed'] ?? 0) === 1;
            }
        } else {
            $stmtUser->close();
        }
    }

    $stmtRole = $mysqli->prepare('SELECT 1 FROM role_permissions WHERE role = ? AND permission_key = ? LIMIT 1');
    if (!$stmtRole) {
        return false;
    }

    $stmtRole->bind_param('ss', $role, $permissionKey);
    $ok = false;
    if ($stmtRole->execute()) {
        $result = $stmtRole->get_result();
        $ok = (bool)($result && $result->fetch_assoc());
    }
    $stmtRole->close();

    return $ok;
}

function auth_can_access_admin_panel(?array $user): bool
{
    if (!$user) {
        return false;
    }
    return auth_is_admin($user) || auth_has_permission($user, 'panel_access');
}

function auth_require_login(string $redirectPath = '/ai-courses/login.php'): void
{
    if (!auth_current_user()) {
        header('Location: ' . $redirectPath);
        exit;
    }
}
