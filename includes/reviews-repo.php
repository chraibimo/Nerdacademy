<?php


require_once __DIR__ . '/db.php';

function ensure_course_reviews_table(mysqli $mysqli): void
{
    $mysqli->query("CREATE TABLE IF NOT EXISTS course_reviews (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        course_id INT NOT NULL,
        reviewer_name VARCHAR(150) NOT NULL,
        reviewer_email VARCHAR(190) NULL,
        rating TINYINT NOT NULL DEFAULT 5,
        review_text TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_course_reviews_course (course_id),
        KEY idx_course_reviews_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function seed_demo_reviews(mysqli $mysqli): void
{
    ensure_course_reviews_table($mysqli);

    $res = $mysqli->query('SELECT COUNT(*) c FROM course_reviews');
    $count = 0;
    if ($res) {
        $row = $res->fetch_assoc();
        $count = (int)($row['c'] ?? 0);
    }
    if ($count > 0) {
        return;
    }

    $samples = [
        [1, 'Amine R.', 'amine@example.com', 5, 'Excellent fundamentals and very practical examples.', 'approved'],
        [2, 'Lina B.', 'lina@example.com', 4, 'Great content, would love more exercises.', 'approved'],
        [3, 'Youssef K.', 'youssef@example.com', 5, 'Best course I have taken this year.', 'pending'],
    ];

    $stmt = $mysqli->prepare('INSERT INTO course_reviews (course_id, reviewer_name, reviewer_email, rating, review_text, status) VALUES (?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        return;
    }

    foreach ($samples as $sample) {
        $courseId = (int)$sample[0];
        $name = (string)$sample[1];
        $email = (string)$sample[2];
        $rating = (int)$sample[3];
        $text = (string)$sample[4];
        $status = (string)$sample[5];
        $stmt->bind_param('ississ', $courseId, $name, $email, $rating, $text, $status);
        $stmt->execute();
    }

    $stmt->close();
}
