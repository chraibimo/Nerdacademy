<?php
require_once __DIR__ . '/db.php';

// ============================================================
//  Schema bootstrap
// ============================================================

function ensure_quiz_tables(mysqli $mysqli): void
{
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS quizzes (
            id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
            lesson_id           INT UNSIGNED NOT NULL,
            title               VARCHAR(255) NOT NULL DEFAULT '',
            description         TEXT NOT NULL,
            pass_score          TINYINT UNSIGNED NOT NULL DEFAULT 70,
            time_limit_seconds  INT NOT NULL DEFAULT 0,
            created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_q_lesson (lesson_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $mysqli->query("
        CREATE TABLE IF NOT EXISTS quiz_questions (
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id       INT UNSIGNED NOT NULL,
            question_text TEXT NOT NULL,
            sort_order    INT NOT NULL DEFAULT 0,
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_qq_quiz_sort (quiz_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $mysqli->query("
        CREATE TABLE IF NOT EXISTS quiz_options (
            id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id  INT UNSIGNED NOT NULL,
            option_text  TEXT NOT NULL,
            is_correct   TINYINT(1) NOT NULL DEFAULT 0,
            sort_order   INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_qo_question_sort (question_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $mysqli->query("
        CREATE TABLE IF NOT EXISTS quiz_attempts (
            id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id      INT UNSIGNED NOT NULL,
            client_id    BIGINT UNSIGNED NOT NULL,
            score        TINYINT UNSIGNED NOT NULL DEFAULT 0,
            passed       TINYINT(1) NOT NULL DEFAULT 0,
            answers_json TEXT NOT NULL,
            started_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_qa_quiz_client (quiz_id, client_id),
            KEY idx_qa_client (client_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// ============================================================
//  Internal helpers
// ============================================================

/**
 * Fetches all questions and their options for a quiz, returns nested array.
 */
function _load_quiz_questions(mysqli $mysqli, int $quizId): array
{
    $stmt = $mysqli->prepare(
        'SELECT id, quiz_id, question_text, sort_order, created_at
           FROM quiz_questions
          WHERE quiz_id = ?
          ORDER BY sort_order ASC, id ASC'
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $quizId);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    $questions = [];
    while ($row = $res->fetch_assoc()) {
        $row['id']         = (int)$row['id'];
        $row['quiz_id']    = (int)$row['quiz_id'];
        $row['sort_order'] = (int)$row['sort_order'];
        $row['options']    = [];
        $questions[$row['id']] = $row;
    }

    if (empty($questions)) {
        return [];
    }

    // Load all options for these questions in one query
    $qids  = implode(',', array_keys($questions));
    $res2  = $mysqli->query(
        "SELECT id, question_id, option_text, is_correct, sort_order
           FROM quiz_options
          WHERE question_id IN ($qids)
          ORDER BY sort_order ASC, id ASC"
    );
    if ($res2) {
        while ($opt = $res2->fetch_assoc()) {
            $opt['id']          = (int)$opt['id'];
            $opt['question_id'] = (int)$opt['question_id'];
            $opt['is_correct']  = (bool)(int)$opt['is_correct'];
            $opt['sort_order']  = (int)$opt['sort_order'];
            $qid = $opt['question_id'];
            if (isset($questions[$qid])) {
                $questions[$qid]['options'][] = $opt;
            }
        }
    }

    return array_values($questions);
}

// ============================================================
//  Quiz CRUD
// ============================================================

/**
 * Returns a quiz (with nested questions+options) for the given lesson, or null.
 */
function get_quiz_by_lesson(mysqli $mysqli, int $lessonId): ?array
{
    $stmt = $mysqli->prepare(
        'SELECT id, lesson_id, title, description, pass_score, time_limit_seconds, created_at
           FROM quizzes
          WHERE lesson_id = ?
          LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $lessonId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    $row['id']                 = (int)$row['id'];
    $row['lesson_id']          = (int)$row['lesson_id'];
    $row['pass_score']         = (int)$row['pass_score'];
    $row['time_limit_seconds'] = (int)$row['time_limit_seconds'];
    $row['questions']          = _load_quiz_questions($mysqli, $row['id']);

    return $row;
}

/**
 * Returns a quiz (with nested questions+options) by quiz id, or null.
 */
function get_quiz_by_id(mysqli $mysqli, int $quizId): ?array
{
    $stmt = $mysqli->prepare(
        'SELECT id, lesson_id, title, description, pass_score, time_limit_seconds, created_at
           FROM quizzes
          WHERE id = ?
          LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $quizId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    $row['id']                 = (int)$row['id'];
    $row['lesson_id']          = (int)$row['lesson_id'];
    $row['pass_score']         = (int)$row['pass_score'];
    $row['time_limit_seconds'] = (int)$row['time_limit_seconds'];
    $row['questions']          = _load_quiz_questions($mysqli, $row['id']);

    return $row;
}

/**
 * Insert or update a quiz. Returns the quiz id.
 */
function save_quiz(
    mysqli $mysqli,
    int $lessonId,
    string $title,
    string $desc,
    int $passScore,
    int $timeLimitSeconds,
    ?int $quizId = null
): int {
    $passScore = max(0, min(100, $passScore));

    if ($quizId !== null) {
        $stmt = $mysqli->prepare(
            'UPDATE quizzes
                SET lesson_id = ?, title = ?, description = ?,
                    pass_score = ?, time_limit_seconds = ?
              WHERE id = ?'
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('issiii', $lessonId, $title, $desc, $passScore, $timeLimitSeconds, $quizId);
        $stmt->execute();
        $stmt->close();
        return $quizId;
    }

    $stmt = $mysqli->prepare(
        'INSERT INTO quizzes (lesson_id, title, description, pass_score, time_limit_seconds)
         VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('issii', $lessonId, $title, $desc, $passScore, $timeLimitSeconds);
    $stmt->execute();
    $newId = (int)$mysqli->insert_id;
    $stmt->close();
    return $newId;
}

/**
 * Cascade-delete a quiz: questions, options, attempts, then the quiz itself.
 */
function delete_quiz(mysqli $mysqli, int $quizId): bool
{
    // Get question IDs
    $stmt = $mysqli->prepare('SELECT id FROM quiz_questions WHERE quiz_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $quizId);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                delete_question($mysqli, (int)$row['id']);
            }
        }
    }

    // Delete attempts
    $s = $mysqli->prepare('DELETE FROM quiz_attempts WHERE quiz_id = ?');
    if ($s) {
        $s->bind_param('i', $quizId);
        $s->execute();
        $s->close();
    }

    // Delete quiz
    $s2 = $mysqli->prepare('DELETE FROM quizzes WHERE id = ?');
    if (!$s2) {
        return false;
    }
    $s2->bind_param('i', $quizId);
    $ok = $s2->execute();
    $s2->close();
    return $ok;
}

// ============================================================
//  Question CRUD
// ============================================================

/**
 * Insert or update a question. Returns the question id.
 */
function save_question(
    mysqli $mysqli,
    int $quizId,
    string $text,
    int $sortOrder,
    ?int $questionId = null
): int {
    if ($questionId !== null) {
        $stmt = $mysqli->prepare(
            'UPDATE quiz_questions SET quiz_id = ?, question_text = ?, sort_order = ? WHERE id = ?'
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('isii', $quizId, $text, $sortOrder, $questionId);
        $stmt->execute();
        $stmt->close();
        return $questionId;
    }

    $stmt = $mysqli->prepare(
        'INSERT INTO quiz_questions (quiz_id, question_text, sort_order) VALUES (?, ?, ?)'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('isi', $quizId, $text, $sortOrder);
    $stmt->execute();
    $newId = (int)$mysqli->insert_id;
    $stmt->close();
    return $newId;
}

/**
 * Delete a question and all its options.
 */
function delete_question(mysqli $mysqli, int $questionId): bool
{
    $s = $mysqli->prepare('DELETE FROM quiz_options WHERE question_id = ?');
    if ($s) {
        $s->bind_param('i', $questionId);
        $s->execute();
        $s->close();
    }

    $s2 = $mysqli->prepare('DELETE FROM quiz_questions WHERE id = ?');
    if (!$s2) {
        return false;
    }
    $s2->bind_param('i', $questionId);
    $ok = $s2->execute();
    $s2->close();
    return $ok;
}

// ============================================================
//  Option CRUD
// ============================================================

/**
 * Insert or update an answer option. Returns the option id.
 */
function save_option(
    mysqli $mysqli,
    int $questionId,
    string $text,
    bool $isCorrect,
    int $sortOrder,
    ?int $optionId = null
): int {
    $isCorrectInt = $isCorrect ? 1 : 0;

    if ($optionId !== null) {
        $stmt = $mysqli->prepare(
            'UPDATE quiz_options
                SET question_id = ?, option_text = ?, is_correct = ?, sort_order = ?
              WHERE id = ?'
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('isiii', $questionId, $text, $isCorrectInt, $sortOrder, $optionId);
        $stmt->execute();
        $stmt->close();
        return $optionId;
    }

    $stmt = $mysqli->prepare(
        'INSERT INTO quiz_options (question_id, option_text, is_correct, sort_order) VALUES (?, ?, ?, ?)'
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('isii', $questionId, $text, $isCorrectInt, $sortOrder);
    $stmt->execute();
    $newId = (int)$mysqli->insert_id;
    $stmt->close();
    return $newId;
}

/**
 * Delete an answer option.
 */
function delete_option(mysqli $mysqli, int $optionId): bool
{
    $stmt = $mysqli->prepare('DELETE FROM quiz_options WHERE id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $optionId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// ============================================================
//  Attempts
// ============================================================

/**
 * Submit a quiz attempt. Scores it against correct answers.
 *
 * @param array $answers  [questionId => optionId, ...]
 * @return array  ['score'=>int, 'passed'=>bool, 'correct'=>int, 'total'=>int]
 */
function submit_quiz_attempt(
    mysqli $mysqli,
    int $quizId,
    int $clientId,
    array $answers
): array {
    // Load quiz with questions+options to score
    $quiz = get_quiz_by_id($mysqli, $quizId);
    if (!$quiz) {
        return ['score' => 0, 'passed' => false, 'correct' => 0, 'total' => 0];
    }

    $total   = count($quiz['questions']);
    $correct = 0;

    foreach ($quiz['questions'] as $question) {
        $qid            = $question['id'];
        $selectedOption = isset($answers[$qid]) ? (int)$answers[$qid] : 0;

        foreach ($question['options'] as $opt) {
            if ($opt['id'] === $selectedOption && $opt['is_correct']) {
                $correct++;
                break;
            }
        }
    }

    $score     = $total > 0 ? (int)round(($correct / $total) * 100) : 0;
    $passed    = $score >= $quiz['pass_score'];
    $passedInt = $passed ? 1 : 0;

    $answersJson = json_encode($answers, JSON_UNESCAPED_UNICODE);
    $now         = date('Y-m-d H:i:s');

    $stmt = $mysqli->prepare(
        'INSERT INTO quiz_attempts (quiz_id, client_id, score, passed, answers_json, started_at, completed_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if ($stmt) {
        $stmt->bind_param('iiiisss', $quizId, $clientId, $score, $passedInt, $answersJson, $now, $now);
        $stmt->execute();
        $stmt->close();
    }

    return [
        'score'   => $score,
        'passed'  => $passed,
        'correct' => $correct,
        'total'   => $total,
    ];
}

/**
 * Returns the best (highest score) attempt for a user on a quiz, or null.
 */
function get_best_attempt(mysqli $mysqli, int $quizId, int $clientId): ?array
{
    $stmt = $mysqli->prepare(
        'SELECT id, quiz_id, client_id, score, passed, answers_json, started_at, completed_at
           FROM quiz_attempts
          WHERE quiz_id = ? AND client_id = ?
          ORDER BY score DESC, id DESC
          LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ii', $quizId, $clientId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    $row['id']        = (int)$row['id'];
    $row['quiz_id']   = (int)$row['quiz_id'];
    $row['client_id'] = (int)$row['client_id'];
    $row['score']     = (int)$row['score'];
    $row['passed']    = (bool)(int)$row['passed'];

    return $row;
}

/**
 * Returns all attempts for a quiz (admin view), most recent first.
 */
function get_all_attempts(mysqli $mysqli, int $quizId): array
{
    $stmt = $mysqli->prepare(
        'SELECT qa.id, qa.quiz_id, qa.client_id, qa.score, qa.passed,
                qa.answers_json, qa.started_at, qa.completed_at,
                c.full_name, c.email
           FROM quiz_attempts qa
      LEFT JOIN clients c ON c.id = qa.client_id
          WHERE qa.quiz_id = ?
          ORDER BY qa.completed_at DESC'
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $quizId);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['id']        = (int)$row['id'];
            $row['quiz_id']   = (int)$row['quiz_id'];
            $row['client_id'] = (int)$row['client_id'];
            $row['score']     = (int)$row['score'];
            $row['passed']    = (bool)(int)$row['passed'];
            $rows[]           = $row;
        }
    }
    return $rows;
}
