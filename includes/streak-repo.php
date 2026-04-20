<?php


require_once __DIR__ . '/db.php';

function ensure_streak_table(mysqli $mysqli): void
{
    $mysqli->query("CREATE TABLE IF NOT EXISTS learning_streaks (
        client_id      BIGINT UNSIGNED NOT NULL,
        current_streak INT NOT NULL DEFAULT 0,
        longest_streak INT NOT NULL DEFAULT 0,
        last_activity_date DATE NULL,
        total_days     INT NOT NULL DEFAULT 0,
        updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (client_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/**
 * Call whenever a lesson is completed or progress is updated.
 *
 * Logic:
 *  - If last_activity_date == today  → already counted, no change
 *  - If last_activity_date == yesterday → increment current_streak, increment total_days
 *  - If gap > 1 day (or no prior record) → reset current_streak to 1, increment total_days
 *
 * Updates longest_streak if the new current_streak surpasses it.
 * Returns the current streak data array.
 */
function record_learning_activity(mysqli $mysqli, int $clientId): array
{
    ensure_streak_table($mysqli);

    $today = (new DateTimeImmutable('today'))->format('Y-m-d');

    // Fetch existing record
    $stmt = $mysqli->prepare(
        'SELECT current_streak, longest_streak, last_activity_date, total_days
         FROM learning_streaks WHERE client_id = ? LIMIT 1'
    );
    $existing = null;
    if ($stmt) {
        $stmt->bind_param('i', $clientId);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }

    if (!$existing) {
        // First ever activity
        $newStreak  = 1;
        $newLongest = 1;
        $newTotal   = 1;

        $ins = $mysqli->prepare(
            'INSERT INTO learning_streaks (client_id, current_streak, longest_streak, last_activity_date, total_days, updated_at)
             VALUES (?, 1, 1, ?, 1, NOW())'
        );
        if ($ins) {
            $ins->bind_param('is', $clientId, $today);
            $ins->execute();
            $ins->close();
        }

        return [
            'current_streak'     => $newStreak,
            'longest_streak'     => $newLongest,
            'last_activity_date' => $today,
            'total_days'         => $newTotal,
        ];
    }

    $lastDate      = (string)($existing['last_activity_date'] ?? '');
    $currentStreak = (int)($existing['current_streak'] ?? 0);
    $longestStreak = (int)($existing['longest_streak'] ?? 0);
    $totalDays     = (int)($existing['total_days'] ?? 0);

    // Already recorded today
    if ($lastDate === $today) {
        return [
            'current_streak'     => $currentStreak,
            'longest_streak'     => $longestStreak,
            'last_activity_date' => $lastDate,
            'total_days'         => $totalDays,
        ];
    }

    $yesterday = (new DateTimeImmutable('yesterday'))->format('Y-m-d');

    if ($lastDate === $yesterday) {
        // Consecutive day — extend the streak
        $currentStreak += 1;
    } else {
        // Gap of more than one day — restart
        $currentStreak = 1;
    }

    $totalDays += 1;

    if ($currentStreak > $longestStreak) {
        $longestStreak = $currentStreak;
    }

    $upd = $mysqli->prepare(
        'UPDATE learning_streaks
         SET current_streak = ?, longest_streak = ?, last_activity_date = ?, total_days = ?, updated_at = NOW()
         WHERE client_id = ?'
    );
    if ($upd) {
        $upd->bind_param('iisii', $currentStreak, $longestStreak, $today, $totalDays, $clientId);
        $upd->execute();
        $upd->close();
    }

    return [
        'current_streak'     => $currentStreak,
        'longest_streak'     => $longestStreak,
        'last_activity_date' => $today,
        'total_days'         => $totalDays,
    ];
}

/**
 * Returns streak data for a user, or safe defaults if no record exists.
 */
function get_user_streak(mysqli $mysqli, int $clientId): array
{
    ensure_streak_table($mysqli);

    $defaults = [
        'current_streak'     => 0,
        'longest_streak'     => 0,
        'last_activity_date' => null,
        'total_days'         => 0,
    ];

    $stmt = $mysqli->prepare(
        'SELECT current_streak, longest_streak, last_activity_date, total_days
         FROM learning_streaks WHERE client_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return $defaults;
    }

    $stmt->bind_param('i', $clientId);
    if (!$stmt->execute()) {
        $stmt->close();
        return $defaults;
    }

    $result = $stmt->get_result();
    $row    = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return $defaults;
    }

    return [
        'current_streak'     => (int)($row['current_streak'] ?? 0),
        'longest_streak'     => (int)($row['longest_streak'] ?? 0),
        'last_activity_date' => $row['last_activity_date'] ?? null,
        'total_days'         => (int)($row['total_days'] ?? 0),
    ];
}

/**
 * Returns top users by current_streak. JOINs with clients table to get full_name and email.
 */
function get_leaderboard(mysqli $mysqli, int $limit = 10): array
{
    ensure_streak_table($mysqli);

    $limit = max(1, min(100, $limit));
    $rows  = [];

    $stmt = $mysqli->prepare(
        'SELECT ls.client_id, ls.current_streak, ls.longest_streak, ls.total_days,
                ls.last_activity_date, cl.full_name, cl.email
         FROM learning_streaks ls
         INNER JOIN clients cl ON cl.id = ls.client_id
         WHERE ls.current_streak > 0
         ORDER BY ls.current_streak DESC, ls.longest_streak DESC
         LIMIT ?'
    );
    if (!$stmt) {
        return $rows;
    }

    $stmt->bind_param('i', $limit);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result?->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();

    return $rows;
}
