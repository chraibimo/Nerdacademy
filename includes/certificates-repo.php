<?php


require_once __DIR__ . '/db.php';

function ensure_certificates_table(mysqli $mysqli): void
{
    $mysqli->query("CREATE TABLE IF NOT EXISTS certificates (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        certificate_code VARCHAR(50) NOT NULL,
        client_id BIGINT UNSIGNED NOT NULL,
        course_id INT NOT NULL,
        issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_certificate_code (certificate_code),
        UNIQUE KEY uniq_client_course_certificate (client_id, course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function get_user_certificates_map(mysqli $mysqli, int $clientId): array
{
    ensure_certificates_table($mysqli);

    $map = [];
    $stmt = $mysqli->prepare('SELECT course_id, certificate_code, issued_at FROM certificates WHERE client_id = ?');
    if (!$stmt) {
        return $map;
    }

    $stmt->bind_param('i', $clientId);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result ? $result->fetch_assoc() : null) {
            if (!$row) {
                break;
            }
            $map[(int)$row['course_id']] = [
                'certificate_code' => (string)($row['certificate_code'] ?? ''),
                'issued_at' => (string)($row['issued_at'] ?? ''),
            ];
        }
    }
    $stmt->close();

    return $map;
}

function get_or_issue_certificate(mysqli $mysqli, int $clientId, int $courseId): ?array
{
    ensure_certificates_table($mysqli);

    $stmt = $mysqli->prepare('SELECT id, certificate_code, issued_at FROM certificates WHERE client_id = ? AND course_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ii', $clientId, $courseId);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        if ($row) {
            return $row;
        }
    } else {
        $stmt->close();
        return null;
    }

    $code = 'NA-' . strtoupper(bin2hex(random_bytes(5)));
    $ins = $mysqli->prepare('INSERT INTO certificates (certificate_code, client_id, course_id) VALUES (?, ?, ?)');
    if (!$ins) {
        return null;
    }
    $ins->bind_param('sii', $code, $clientId, $courseId);
    $ok = $ins->execute();
    $ins->close();

    if (!$ok) {
        return null;
    }

    return ['certificate_code' => $code, 'issued_at' => date('Y-m-d H:i:s')];
}
