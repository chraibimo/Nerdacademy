<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/purchases-repo.php';

/* ─────────────────────────────────────────────────────────────────────────────
   Table setup
   ───────────────────────────────────────────────────────────────────────────── */

function ensure_bundle_tables(mysqli $mysqli): void
{
    $mysqli->query("CREATE TABLE IF NOT EXISTS bundles (
        id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
        title         VARCHAR(200)     NOT NULL,
        description   TEXT             NOT NULL DEFAULT '',
        price         DECIMAL(10,2)    NOT NULL DEFAULT 0,
        original_price DECIMAL(10,2)   NOT NULL DEFAULT 0,
        image_url     VARCHAR(500)     NULL,
        is_active     TINYINT          NOT NULL DEFAULT 1,
        created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $mysqli->query("CREATE TABLE IF NOT EXISTS bundle_courses (
        bundle_id  INT UNSIGNED NOT NULL,
        course_id  INT          NOT NULL,
        sort_order INT          NOT NULL DEFAULT 0,
        PRIMARY KEY (bundle_id, course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $mysqli->query("CREATE TABLE IF NOT EXISTS bundle_purchases (
        id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
        client_id    BIGINT UNSIGNED  NOT NULL,
        bundle_id    INT UNSIGNED     NOT NULL,
        amount       DECIMAL(10,2)    NOT NULL DEFAULT 0,
        currency     VARCHAR(10)      NOT NULL DEFAULT 'USD',
        purchased_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_bp_client (client_id),
        KEY idx_bp_bundle (bundle_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/* ─────────────────────────────────────────────────────────────────────────────
   Internal helper – load courses for a list of bundle IDs
   ───────────────────────────────────────────────────────────────────────────── */

function _attach_bundle_courses(mysqli $mysqli, array &$bundles): void
{
    if (empty($bundles)) {
        return;
    }
    $ids = implode(',', array_map('intval', array_column($bundles, 'id')));

    $res = $mysqli->query(
        "SELECT bc.bundle_id, bc.sort_order, cc.id, cc.title, cc.price, cc.color, cc.icon, cc.category, cc.image_url
         FROM bundle_courses bc
         LEFT JOIN courses_catalog cc ON cc.id = bc.course_id
         WHERE bc.bundle_id IN ({$ids})
         ORDER BY bc.bundle_id, bc.sort_order"
    );

    $map = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $map[(int)$row['bundle_id']][] = $row;
        }
    }

    foreach ($bundles as &$b) {
        $b['courses'] = $map[(int)$b['id']] ?? [];
    }
    unset($b);
}

/* ─────────────────────────────────────────────────────────────────────────────
   Public API
   ───────────────────────────────────────────────────────────────────────────── */

function get_all_bundles(mysqli $mysqli, bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM bundles';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY id DESC';

    $bundles = [];
    $res = $mysqli->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $bundles[] = $row;
        }
    }

    _attach_bundle_courses($mysqli, $bundles);
    return $bundles;
}

function get_bundle_by_id(mysqli $mysqli, int $bundleId): ?array
{
    $stmt = $mysqli->prepare('SELECT * FROM bundles WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $bundleId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    $bundles = [$row];
    _attach_bundle_courses($mysqli, $bundles);
    return $bundles[0];
}

function save_bundle(
    mysqli $mysqli,
    string $title,
    string $desc,
    float  $price,
    float  $originalPrice,
    string $imageUrl,
    bool   $isActive,
    array  $courseIds,
    ?int   $bundleId = null
): int {
    $activeInt = $isActive ? 1 : 0;

    if ($bundleId === null) {
        $stmt = $mysqli->prepare(
            'INSERT INTO bundles (title, description, price, original_price, image_url, is_active)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('ssddsi', $title, $desc, $price, $originalPrice, $imageUrl, $activeInt);
        $stmt->execute();
        $bundleId = (int)$mysqli->insert_id;
        $stmt->close();
    } else {
        $stmtU = $mysqli->prepare(
            'UPDATE bundles SET title=?, description=?, price=?, original_price=?, image_url=?, is_active=? WHERE id=?'
        );
        if (!$stmtU) {
            return 0;
        }
        $stmtU->bind_param('ssddsii', $title, $desc, $price, $originalPrice, $imageUrl, $activeInt, $bundleId);
        $stmtU->execute();
        $stmtU->close();
    }

    if ($bundleId <= 0) {
        return 0;
    }

    // Sync bundle_courses
    $delStmt = $mysqli->prepare('DELETE FROM bundle_courses WHERE bundle_id = ?');
    if ($delStmt) {
        $delStmt->bind_param('i', $bundleId);
        $delStmt->execute();
        $delStmt->close();
    }

    if (!empty($courseIds)) {
        $insStmt = $mysqli->prepare('INSERT INTO bundle_courses (bundle_id, course_id, sort_order) VALUES (?, ?, ?)');
        if ($insStmt) {
            foreach ($courseIds as $sort => $cid) {
                $cid      = (int)$cid;
                $sortInt  = (int)$sort;
                $insStmt->bind_param('iii', $bundleId, $cid, $sortInt);
                $insStmt->execute();
            }
            $insStmt->close();
        }
    }

    return $bundleId;
}

function delete_bundle(mysqli $mysqli, int $bundleId): bool
{
    $stmt = $mysqli->prepare('DELETE FROM bundles WHERE id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $bundleId);
    $ok = $stmt->execute();
    $stmt->close();

    // Also clean up bundle_courses
    $s2 = $mysqli->prepare('DELETE FROM bundle_courses WHERE bundle_id = ?');
    if ($s2) {
        $s2->bind_param('i', $bundleId);
        $s2->execute();
        $s2->close();
    }

    return $ok;
}

function purchase_bundle(mysqli $mysqli, int $clientId, int $bundleId, float $amount): bool
{
    ensure_purchases_table($mysqli);

    // Insert bundle_purchases row
    $stmt = $mysqli->prepare(
        'INSERT INTO bundle_purchases (client_id, bundle_id, amount) VALUES (?, ?, ?)'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iid', $clientId, $bundleId, $amount);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return false;
    }

    // Enroll user in each course in the bundle (if not already enrolled)
    $bundle = get_bundle_by_id($mysqli, $bundleId);
    if (!$bundle) {
        return true; // purchased but no courses to enroll — not a failure
    }

    foreach ($bundle['courses'] as $course) {
        $courseId = (int)($course['id'] ?? 0);
        if ($courseId <= 0) {
            continue;
        }
        if (has_user_enrolled_course($mysqli, $clientId, $courseId)) {
            continue;
        }
        $coursePrice = (float)($course['price'] ?? 0);
        $ins = $mysqli->prepare(
            'INSERT IGNORE INTO purchases (client_id, course_id, original_amount, amount, coupon_code, discount_percent, currency, status)
             VALUES (?, ?, ?, ?, \'\', 0, \'USD\', \'completed\')'
        );
        if ($ins) {
            $ins->bind_param('iidd', $clientId, $courseId, $coursePrice, $coursePrice);
            $ins->execute();
            $ins->close();
        }
    }

    return true;
}

function has_user_purchased_bundle(mysqli $mysqli, int $clientId, int $bundleId): bool
{
    $stmt = $mysqli->prepare(
        'SELECT 1 FROM bundle_purchases WHERE client_id = ? AND bundle_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $clientId, $bundleId);
    $stmt->execute();
    $res = $stmt->get_result();
    $found = (bool)($res && $res->fetch_assoc());
    $stmt->close();
    return $found;
}

function get_bundle_sales_stats(mysqli $mysqli): array
{
    $bundles = get_all_bundles($mysqli);
    if (empty($bundles)) {
        return [];
    }

    $ids = implode(',', array_map(fn($b) => (int)$b['id'], $bundles));

    $res = $mysqli->query(
        "SELECT bundle_id, COUNT(*) AS purchase_count, COALESCE(SUM(amount), 0) AS revenue
         FROM bundle_purchases
         WHERE bundle_id IN ({$ids})
         GROUP BY bundle_id"
    );

    $statsMap = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $statsMap[(int)$row['bundle_id']] = [
                'purchase_count' => (int)$row['purchase_count'],
                'revenue'        => (float)$row['revenue'],
            ];
        }
    }

    foreach ($bundles as &$b) {
        $bid = (int)$b['id'];
        $b['purchase_count'] = $statsMap[$bid]['purchase_count'] ?? 0;
        $b['revenue']        = $statsMap[$bid]['revenue']        ?? 0.0;
    }
    unset($b);

    return $bundles;
}
