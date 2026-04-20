<?php


require_once __DIR__ . '/db.php';

function ensure_courses_catalog_table(mysqli $mysqli): void
{
    $sql = "
    CREATE TABLE IF NOT EXISTS courses_catalog (
        id INT NOT NULL AUTO_INCREMENT,
        slug VARCHAR(190) NOT NULL,
        title VARCHAR(220) NOT NULL,
        subtitle VARCHAR(255) NOT NULL DEFAULT '',
        category VARCHAR(120) NOT NULL DEFAULT 'General',
        level VARCHAR(40) NOT NULL DEFAULT 'Beginner',
        duration VARCHAR(60) NOT NULL DEFAULT '',
        lessons INT NOT NULL DEFAULT 0,
        students INT NOT NULL DEFAULT 0,
        rating DECIMAL(3,2) NOT NULL DEFAULT 0,
        reviews INT NOT NULL DEFAULT 0,
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        old_price DECIMAL(10,2) NOT NULL DEFAULT 0,
        instructor VARCHAR(150) NOT NULL DEFAULT '',
        instructor_title VARCHAR(190) NOT NULL DEFAULT '',
        color VARCHAR(20) NOT NULL DEFAULT '#6366f1',
        icon VARCHAR(60) NOT NULL DEFAULT 'brain',
        badge VARCHAR(60) NOT NULL DEFAULT '',
        image_url VARCHAR(255) NOT NULL DEFAULT '',
        description TEXT NOT NULL,
        tags_json LONGTEXT NOT NULL,
        outcomes_json LONGTEXT NOT NULL,
        curriculum_json LONGTEXT NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_courses_slug (slug),
        KEY idx_courses_category (category),
        KEY idx_courses_active_sort (is_active, sort_order, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $mysqli->query($sql);
    try {
        $mysqli->query("ALTER TABLE courses_catalog ADD COLUMN image_url VARCHAR(255) NOT NULL DEFAULT '' AFTER badge");
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() !== 1060) {
            throw $e;
        }
    }
}

function seed_courses_catalog_from_static(mysqli $mysqli): void
{
    ensure_courses_catalog_table($mysqli);

    $res = $mysqli->query('SELECT COUNT(*) AS c FROM courses_catalog');
    $count = 0;
    if ($res) {
        $row = $res->fetch_assoc();
        $count = (int)($row['c'] ?? 0);
    }

    if ($count > 0) {
        return;
    }

    $courses = [];
    require __DIR__ . '/data.php';

    $stmt = $mysqli->prepare('INSERT INTO courses_catalog (
        id, slug, title, subtitle, category, level, duration, lessons, students,
        rating, reviews, price, old_price, instructor, instructor_title, color,
        icon, badge, image_url, description, tags_json, outcomes_json, curriculum_json, sort_order, is_active
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');

    if (!$stmt) {
        return;
    }

    $sort = 1;
    foreach ($courses as $course) {
        $id = (int)($course['id'] ?? 0);
        $slug = (string)($course['slug'] ?? '');
        $title = (string)($course['title'] ?? 'Untitled Course');
        $subtitle = (string)($course['subtitle'] ?? '');
        $category = (string)($course['category'] ?? 'General');
        $level = (string)($course['level'] ?? 'Beginner');
        $duration = (string)($course['duration'] ?? '');
        $lessons = (int)($course['lessons'] ?? 0);
        $students = (int)($course['students'] ?? 0);
        $rating = (float)($course['rating'] ?? 0);
        $reviews = (int)($course['reviews'] ?? 0);
        $price = (float)($course['price'] ?? 0);
        $oldPrice = (float)($course['old_price'] ?? 0);
        $instructor = (string)($course['instructor'] ?? '');
        $instructorTitle = (string)($course['instructor_title'] ?? '');
        $color = (string)($course['color'] ?? '#6366f1');
        $icon = (string)($course['icon'] ?? 'brain');
        $badge = (string)($course['badge'] ?? '');
        $imageUrl = (string)($course['image_url'] ?? '');
        $description = (string)($course['description'] ?? '');
        $tagsJson = json_encode($course['tags'] ?? [], JSON_UNESCAPED_UNICODE);
        $outcomesJson = json_encode($course['outcomes'] ?? [], JSON_UNESCAPED_UNICODE);
        $curriculumJson = json_encode($course['curriculum'] ?? [], JSON_UNESCAPED_UNICODE);
        $sortOrder = $sort++;

        $stmt->bind_param(
            'issssssiididdssssssssssii',
            $id,
            $slug,
            $title,
            $subtitle,
            $category,
            $level,
            $duration,
            $lessons,
            $students,
            $rating,
            $reviews,
            $price,
            $oldPrice,
            $instructor,
            $instructorTitle,
            $color,
            $icon,
            $badge,
            $imageUrl,
            $description,
            $tagsJson,
            $outcomesJson,
            $curriculumJson,
            $sortOrder
        );

        $stmt->execute();
    }

    $stmt->close();
}

function hydrate_course_row(array $row): array
{
    $tags = json_decode((string)($row['tags_json'] ?? '[]'), true);
    $outcomes = json_decode((string)($row['outcomes_json'] ?? '[]'), true);
    $curriculum = json_decode((string)($row['curriculum_json'] ?? '[]'), true);

    return [
        'id' => (int)$row['id'],
        'slug' => (string)($row['slug'] ?? ''),
        'title' => (string)($row['title'] ?? ''),
        'subtitle' => (string)($row['subtitle'] ?? ''),
        'category' => (string)($row['category'] ?? 'General'),
        'level' => (string)($row['level'] ?? 'Beginner'),
        'duration' => (string)($row['duration'] ?? ''),
        'lessons' => (int)($row['lessons'] ?? 0),
        'students' => (int)($row['students'] ?? 0),
        'rating' => (float)($row['rating'] ?? 0),
        'reviews' => (int)($row['reviews'] ?? 0),
        'price' => (float)($row['price'] ?? 0),
        'old_price' => (float)($row['old_price'] ?? 0),
        'instructor' => (string)($row['instructor'] ?? ''),
        'instructor_title' => (string)($row['instructor_title'] ?? ''),
        'color' => (string)($row['color'] ?? '#6366f1'),
        'icon' => (string)($row['icon'] ?? 'brain'),
        'badge' => (string)($row['badge'] ?? ''),
        'image_url' => (string)($row['image_url'] ?? ''),
        'is_active' => (int)($row['is_active'] ?? 1),
        'description' => (string)($row['description'] ?? ''),
        'tags' => is_array($tags) ? array_values($tags) : [],
        'outcomes' => is_array($outcomes) ? array_values($outcomes) : [],
        'curriculum' => is_array($curriculum) ? array_values($curriculum) : [],
    ];
}

function load_all_courses(mysqli $mysqli, bool $activeOnly = true): array
{
    seed_courses_catalog_from_static($mysqli);

    $sql = 'SELECT * FROM courses_catalog';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';

    $courses = [];
    $res = $mysqli->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $courses[] = hydrate_course_row($row);
        }
    }

    return $courses;
}

function find_course_by_id(mysqli $mysqli, int $courseId): ?array
{
    seed_courses_catalog_from_static($mysqli);

    $stmt = $mysqli->prepare('SELECT * FROM courses_catalog WHERE id = ? AND is_active = 1 LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $courseId);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    return hydrate_course_row($row);
}
