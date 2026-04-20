<?php


require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

function ensure_email_verification_table(mysqli $mysqli): bool
{
    $sql = "
    CREATE TABLE IF NOT EXISTS email_verification_tokens (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        client_id BIGINT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        consumed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_evt_client_id (client_id),
        KEY idx_evt_token_hash (token_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    return $mysqli->query($sql) === true;
}

function create_email_verification_token(mysqli $mysqli, int $clientId, int $validHours = 24): ?string
{
    if (!ensure_email_verification_table($mysqli)) {
        return null;
    }

    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);

    $stmt = $mysqli->prepare('INSERT INTO email_verification_tokens (client_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('isi', $clientId, $hash, $validHours);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok ? $token : null;
}

function send_verification_email(mysqli $mysqli, int $clientId, string $fullName, string $email, ?string &$error = null): bool
{
    $token = create_email_verification_token($mysqli, $clientId, 24);
    if (!$token) {
        $error = 'Unable to create verification token.';
        return false;
    }

    $baseUrl = (isset($_SERVER['HTTP_HOST']) ? ('http://' . $_SERVER['HTTP_HOST']) : 'http://localhost') . '/ai-courses';
    $verifyUrl = $baseUrl . '/verify-email.php?token=' . urlencode($token);

    $subject = 'Verify your NerdAcademy account';
    $safeName = htmlspecialchars($fullName ?: 'there', ENT_QUOTES, 'UTF-8');

    $html = <<<HTML
    <div style="margin:0;padding:32px 12px;background:linear-gradient(160deg,#eef2ff 0%,#f8fafc 48%,#e0f2fe 100%);font-family:Inter,Segoe UI,Arial,sans-serif;color:#111827">
        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;max-width:680px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:22px;overflow:hidden;box-shadow:0 18px 46px rgba(79,70,229,.16)">
            <tr>
                <td style="background:linear-gradient(135deg,#4338ca 0%,#6366f1 48%,#06b6d4 100%);padding:30px 30px 24px;color:#ffffff">
                    <div style="font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;opacity:.92">Welcome to NerdAcademy</div>
                    <h1 style="margin:12px 0 8px;font-size:30px;line-height:1.2;font-weight:800">Confirm Your Email. Launch Your AI Journey.</h1>
                    <p style="margin:0;font-size:15px;line-height:1.75;color:rgba(255,255,255,.92)">One quick confirmation unlocks your dashboard, courses, progress tracking, and certificates.</p>
                </td>
            </tr>
            <tr>
                <td style="padding:30px">
                    <p style="margin:0 0 12px;font-size:17px;line-height:1.7;color:#111827">Hello {$safeName},</p>
                    <p style="margin:0 0 18px;font-size:15px;line-height:1.8;color:#475569">Thanks for joining NerdAcademy. We are excited to help you turn curiosity into practical AI skill — from your first lesson to your next big build.</p>

                    <div style="margin:18px 0;padding:18px;border-radius:16px;border:1px solid #dbeafe;background:linear-gradient(135deg,#f8fbff 0%,#eef2ff 100%)">
                        <div style="font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#4f46e5;margin-bottom:10px">Once verified, you can</div>
                        <ul style="margin:0;padding-left:18px;color:#334155;font-size:14px;line-height:1.9">
                            <li>Access your learning dashboard instantly</li>
                            <li>Track lessons, streaks, and certificates</li>
                            <li>Continue seamlessly across all your devices</li>
                        </ul>
                    </div>

                    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 22px">
                        <tr>
                            <td style="border-radius:12px;background:#4f46e5">
                                <a href="{$verifyUrl}" style="display:inline-block;padding:14px 24px;font-size:15px;font-weight:800;color:#ffffff;text-decoration:none">Verify Email Address</a>
                            </td>
                        </tr>
                    </table>

                    <div style="padding:14px 16px;border:1px solid #e5e7eb;border-radius:14px;background:#f8fafc">
                        <div style="font-size:12px;color:#64748b;margin-bottom:6px;font-weight:700">Verification link</div>
                        <a href="{$verifyUrl}" style="font-size:13px;color:#1d4ed8;word-break:break-all;text-decoration:none">{$verifyUrl}</a>
                    </div>

                    <p style="margin:18px 0 0;font-size:13px;line-height:1.8;color:#64748b">This secure link expires in 24 hours. If you did not create this account, you can safely ignore this email.</p>
                </td>
            </tr>
            <tr>
                <td style="padding:18px 30px 24px;border-top:1px solid #e5e7eb;background:#fcfcff;color:#64748b;font-size:12px;line-height:1.8">
                    NerdAcademy Support Team<br>
                    Helping ambitious learners build real AI skills.
                </td>
            </tr>
        </table>
    </div>
HTML;

    $text = "NerdAcademy account verification\n\n"
        . "Hello " . ($fullName ?: 'there') . ",\n\n"
        . "Welcome to NerdAcademy. Please verify your email to activate your account and unlock your learning dashboard:\n"
        . $verifyUrl . "\n\n"
        . "This secure link expires in 24 hours. If you did not create this account, you can safely ignore this email.";

    return send_smtp_mail($email, $fullName, $subject, $html, $text, $error);
}

function verify_email_token(mysqli $mysqli, string $rawToken): array
{
    if ($rawToken === '') {
        return ['ok' => false, 'error' => 'missing-token'];
    }

    $hash = hash('sha256', $rawToken);

    $stmt = $mysqli->prepare('SELECT id, client_id, expires_at, consumed_at FROM email_verification_tokens WHERE token_hash = ? ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'query-failed'];
    }

    $stmt->bind_param('s', $hash);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['ok' => false, 'error' => 'query-failed'];
    }

    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return ['ok' => false, 'error' => 'invalid-token'];
    }

    if (!empty($row['consumed_at'])) {
        return ['ok' => false, 'error' => 'already-used'];
    }

    if (strtotime((string)$row['expires_at']) < time()) {
        return ['ok' => false, 'error' => 'expired'];
    }

    $mysqli->begin_transaction();
    try {
        $clientId = (int)$row['client_id'];

        $upClient = $mysqli->prepare("UPDATE clients SET email_verified = 1, account_status = 'active', updated_at = NOW() WHERE id = ?");
        if (!$upClient) throw new RuntimeException('update-client-failed');
        $upClient->bind_param('i', $clientId);
        if (!$upClient->execute()) {
            $upClient->close();
            throw new RuntimeException('update-client-failed');
        }
        $upClient->close();

        $upToken = $mysqli->prepare('UPDATE email_verification_tokens SET consumed_at = NOW() WHERE id = ?');
        if (!$upToken) throw new RuntimeException('update-token-failed');
        $tokenId = (int)$row['id'];
        $upToken->bind_param('i', $tokenId);
        if (!$upToken->execute()) {
            $upToken->close();
            throw new RuntimeException('update-token-failed');
        }
        $upToken->close();

        $mysqli->commit();
        return ['ok' => true];
    } catch (Throwable $e) {
        $mysqli->rollback();
        return ['ok' => false, 'error' => 'verify-failed'];
    }
}

function ensure_password_change_requests_table(mysqli $mysqli): bool
{
    $sql = "
    CREATE TABLE IF NOT EXISTS password_change_requests (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        client_id BIGINT UNSIGNED NOT NULL,
        new_password_hash VARCHAR(255) NOT NULL,
        code_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        consumed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_pcr_client_id (client_id),
        KEY idx_pcr_code_hash (code_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    return $mysqli->query($sql) === true;
}

function get_pending_password_change_request(mysqli $mysqli, int $clientId): ?array
{
    if ($clientId <= 0 || !ensure_password_change_requests_table($mysqli)) {
        return null;
    }

    $stmt = $mysqli->prepare('SELECT id, expires_at, created_at FROM password_change_requests WHERE client_id = ? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $clientId);
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

    if (strtotime((string)$row['expires_at']) < time()) {
        $expire = $mysqli->prepare('UPDATE password_change_requests SET consumed_at = NOW() WHERE id = ?');
        if ($expire) {
            $requestId = (int)$row['id'];
            $expire->bind_param('i', $requestId);
            $expire->execute();
            $expire->close();
        }
        return null;
    }

    return $row;
}

function create_password_change_request(mysqli $mysqli, int $clientId, string $newPassword, int $validMinutes = 15): ?array
{
    if ($clientId <= 0 || $newPassword === '' || !ensure_password_change_requests_table($mysqli)) {
        return null;
    }

    $clear = $mysqli->prepare('UPDATE password_change_requests SET consumed_at = NOW() WHERE client_id = ? AND consumed_at IS NULL');
    if ($clear) {
        $clear->bind_param('i', $clientId);
        $clear->execute();
        $clear->close();
    }

    $code = (string)random_int(100000, 999999);
    $codeHash = hash('sha256', $code);
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $mysqli->prepare('INSERT INTO password_change_requests (client_id, new_password_hash, code_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('issi', $clientId, $newPasswordHash, $codeHash, $validMinutes);
    $ok = $stmt->execute();
    $requestId = (int)$stmt->insert_id;
    $stmt->close();

    if (!$ok) {
        return null;
    }

    return [
        'request_id' => $requestId,
        'code' => $code,
        'valid_minutes' => $validMinutes,
    ];
}

function send_password_change_code_email(mysqli $mysqli, int $clientId, string $fullName, string $email, string $newPassword, ?string &$error = null): bool
{
    if ($email === '') {
        $error = 'This account does not have a valid email address for password verification.';
        return false;
    }

    $payload = create_password_change_request($mysqli, $clientId, $newPassword, 15);
    if (!$payload) {
        $error = 'Unable to create a password verification request.';
        return false;
    }

    $safeName = htmlspecialchars($fullName !== '' ? $fullName : 'there', ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars((string)$payload['code'], ENT_QUOTES, 'UTF-8');
    $requestTime = date('M j, Y \a\t g:i A');
    $ipAddress = htmlspecialchars((string)($_SERVER['REMOTE_ADDR'] ?? 'Unknown device'), ENT_QUOTES, 'UTF-8');
    $validMinutes = (int)($payload['valid_minutes'] ?? 15);

    $subject = 'Your NerdAcademy password change verification code';

    $html = <<<HTML
    <div style="margin:0;padding:32px 12px;background:linear-gradient(160deg,#eef2ff 0%,#f8fafc 48%,#ecfeff 100%);font-family:Inter,Segoe UI,Arial,sans-serif;color:#0f172a">
        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;max-width:680px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:20px;overflow:hidden;box-shadow:0 18px 45px rgba(79,70,229,.16)">
            <tr>
                <td style="background:linear-gradient(135deg,#312e81 0%,#4f46e5 45%,#0ea5e9 100%);padding:30px 30px 24px;color:#ffffff">
                    <div style="font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;opacity:.92">NerdAcademy Security Studio</div>
                    <h1 style="margin:12px 0 8px;font-size:30px;line-height:1.2;font-weight:800">Approve This Password Update</h1>
                    <p style="margin:0;font-size:15px;line-height:1.75;color:rgba(255,255,255,.9)">We protect every security change with a one-time code — quick for you, hard for anyone else.</p>
                </td>
            </tr>
            <tr>
                <td style="padding:30px">
                    <p style="margin:0 0 12px;font-size:17px;line-height:1.7;color:#111827">Hello {$safeName},</p>
                    <p style="margin:0 0 18px;font-size:15px;line-height:1.8;color:#475569">A request was made to update the password on your NerdAcademy account. Enter the secure code below on the password settings page to confirm the change:</p>

                    <div style="margin:22px 0;padding:20px 18px;border-radius:18px;border:1px solid #c7d2fe;background:linear-gradient(135deg,#eef2ff 0%,#f8fbff 100%);text-align:center">
                        <div style="font-size:11px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:#4f46e5;margin-bottom:10px">Security Verification Code</div>
                        <div style="font-size:34px;line-height:1;font-weight:900;letter-spacing:.32em;color:#1e1b4b">{$safeCode}</div>
                        <div style="margin-top:10px;font-size:13px;color:#64748b">Valid for {$validMinutes} minutes only.</div>
                    </div>

                    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 18px;border-collapse:separate;border-spacing:0 10px">
                        <tr>
                            <td style="padding:14px 16px;border:1px solid #e5e7eb;border-radius:14px;background:#f8fafc;font-size:13px;color:#334155">
                                <strong style="display:block;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#6366f1;margin-bottom:4px">Request time</strong>
                                {$requestTime}
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:14px 16px;border:1px solid #e5e7eb;border-radius:14px;background:#f8fafc;font-size:13px;color:#334155">
                                <strong style="display:block;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#6366f1;margin-bottom:4px">Request source</strong>
                                {$ipAddress}
                            </td>
                        </tr>
                    </table>

                    <div style="padding:16px 18px;border-radius:14px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:13px;line-height:1.7">
                        <strong style="display:block;color:#7c2d12;margin-bottom:4px">Wasn't you?</strong>
                        Ignore this email and your current password will stay exactly as it is. No changes are applied unless the code is entered on NerdAcademy.
                    </div>

                    <p style="margin:18px 0 0;font-size:13px;line-height:1.8;color:#64748b">Never share this code with anyone — including support staff. We will never ask for it by email or chat.</p>
                </td>
            </tr>
            <tr>
                <td style="padding:18px 30px 24px;border-top:1px solid #e5e7eb;background:#fcfcff;color:#64748b;font-size:12px;line-height:1.8">
                    NerdAcademy Security Team<br>
                    This is an automated account protection email.
                </td>
            </tr>
        </table>
    </div>
HTML;

    $text = "NerdAcademy password change verification\n\n"
        . "Hello " . ($fullName !== '' ? $fullName : 'there') . ",\n\n"
        . "A request was made to update your password. Use this one-time code to approve the change:\n\n"
        . ($payload['code'] ?? '') . "\n\n"
        . "This code expires in " . $validMinutes . " minutes.\n"
        . "Request time: " . $requestTime . "\n"
        . "Request source: " . (string)($_SERVER['REMOTE_ADDR'] ?? 'Unknown device') . "\n\n"
        . "If you did not request this, ignore this email and your current password will remain unchanged.";

    $sent = send_smtp_mail($email, $fullName, $subject, $html, $text, $error);
    if (!$sent) {
        $cleanup = $mysqli->prepare('UPDATE password_change_requests SET consumed_at = NOW() WHERE id = ?');
        if ($cleanup) {
            $requestId = (int)($payload['request_id'] ?? 0);
            $cleanup->bind_param('i', $requestId);
            $cleanup->execute();
            $cleanup->close();
        }
    }

    return $sent;
}

function send_password_reset_code_email(mysqli $mysqli, int $clientId, string $fullName, string $email, ?string &$error = null): bool
{
    if ($email === '') {
        $error = 'This account does not have a valid email address for password reset.';
        return false;
    }

    $placeholderPassword = 'pending-reset-' . bin2hex(random_bytes(12));
    $payload = create_password_change_request($mysqli, $clientId, $placeholderPassword, 15);
    if (!$payload) {
        $error = 'Unable to create a password reset request.';
        return false;
    }

    $safeName = htmlspecialchars($fullName !== '' ? $fullName : 'there', ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars((string)$payload['code'], ENT_QUOTES, 'UTF-8');
    $requestTime = date('M j, Y \a\t g:i A');
    $ipAddress = htmlspecialchars((string)($_SERVER['REMOTE_ADDR'] ?? 'Unknown device'), ENT_QUOTES, 'UTF-8');
    $validMinutes = (int)($payload['valid_minutes'] ?? 15);

    $subject = 'Your NerdAcademy password reset code';

    $html = '
    <div style="margin:0;padding:32px 12px;background:linear-gradient(160deg,#eef2ff 0%,#f8fafc 48%,#ecfeff 100%);font-family:Inter,Segoe UI,Arial,sans-serif;color:#0f172a">
        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;max-width:680px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:20px;overflow:hidden;box-shadow:0 18px 45px rgba(79,70,229,.16)">
            <tr>
                <td style="background:linear-gradient(135deg,#4338ca 0%,#6366f1 45%,#0ea5e9 100%);padding:30px 30px 24px;color:#ffffff">
                    <div style="font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;opacity:.92">NerdAcademy Security</div>
                    <h1 style="margin:12px 0 8px;font-size:30px;line-height:1.2;font-weight:800">Reset Your Password</h1>
                    <p style="margin:0;font-size:15px;line-height:1.7;color:rgba(255,255,255,.9)">Use the one-time code below to verify your identity before creating a new password.</p>
                </td>
            </tr>
            <tr>
                <td style="padding:30px">
                    <p style="margin:0 0 12px;font-size:17px;line-height:1.7;color:#111827">Hello ' . $safeName . ',</p>
                    <p style="margin:0 0 18px;font-size:15px;line-height:1.8;color:#475569">We received a request to reset the password for your NerdAcademy account. Enter this code on the verification page to continue:</p>

                    <div style="margin:22px 0;padding:20px 18px;border-radius:18px;border:1px solid #c7d2fe;background:linear-gradient(135deg,#eef2ff 0%,#f8fbff 100%);text-align:center">
                        <div style="font-size:11px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:#4f46e5;margin-bottom:10px">Password Reset Code</div>
                        <div style="font-size:34px;line-height:1;font-weight:900;letter-spacing:.32em;color:#1e1b4b">' . $safeCode . '</div>
                        <div style="margin-top:10px;font-size:13px;color:#64748b">This code expires in ' . $validMinutes . ' minutes.</div>
                    </div>

                    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 18px;border-collapse:separate;border-spacing:0 10px">
                        <tr>
                            <td style="padding:14px 16px;border:1px solid #e5e7eb;border-radius:14px;background:#f8fafc;font-size:13px;color:#334155">
                                <strong style="display:block;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#6366f1;margin-bottom:4px">Request time</strong>
                                ' . htmlspecialchars($requestTime, ENT_QUOTES, 'UTF-8') . '
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:14px 16px;border:1px solid #e5e7eb;border-radius:14px;background:#f8fafc;font-size:13px;color:#334155">
                                <strong style="display:block;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#6366f1;margin-bottom:4px">Request source</strong>
                                ' . $ipAddress . '
                            </td>
                        </tr>
                    </table>

                    <div style="padding:16px 18px;border-radius:14px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:13px;line-height:1.7">
                        <strong style="display:block;color:#7c2d12;margin-bottom:4px">Did not request this?</strong>
                        You can safely ignore this email. Your password will remain unchanged unless this code is entered on NerdAcademy.
                    </div>

                    <p style="margin:18px 0 0;font-size:13px;line-height:1.8;color:#64748b">For your safety, never share this code with anyone — including support staff.</p>
                </td>
            </tr>
            <tr>
                <td style="padding:18px 30px 24px;border-top:1px solid #e5e7eb;background:#fcfcff;color:#64748b;font-size:12px;line-height:1.8">
                    NerdAcademy Security Team<br>
                    This is an automated security email regarding your account.
                </td>
            </tr>
        </table>
    </div>';

    $text = "NerdAcademy password reset verification\n\n"
        . "Hello " . ($fullName !== '' ? $fullName : 'there') . ",\n\n"
        . "We received a request to reset your password. Use this one-time verification code to continue:\n\n"
        . ($payload['code'] ?? '') . "\n\n"
        . "This code expires in " . $validMinutes . " minutes.\n"
        . "Request time: " . $requestTime . "\n"
        . "Request source: " . (string)($_SERVER['REMOTE_ADDR'] ?? 'Unknown device') . "\n\n"
        . "If you did not request this change, ignore this email and your current password will stay the same.";

    $sent = send_smtp_mail($email, $fullName, $subject, $html, $text, $error);
    if (!$sent) {
        $cleanup = $mysqli->prepare('UPDATE password_change_requests SET consumed_at = NOW() WHERE id = ?');
        if ($cleanup) {
            $requestId = (int)($payload['request_id'] ?? 0);
            $cleanup->bind_param('i', $requestId);
            $cleanup->execute();
            $cleanup->close();
        }
    }

    return $sent;
}

function verify_password_reset_code_only(mysqli $mysqli, int $clientId, string $code): array
{
    if ($clientId <= 0 || $code === '' || !ensure_password_change_requests_table($mysqli)) {
        return ['ok' => false, 'error' => 'no-request'];
    }

    $stmt = $mysqli->prepare('SELECT id, code_hash, expires_at FROM password_change_requests WHERE client_id = ? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'query-failed'];
    }

    $stmt->bind_param('i', $clientId);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['ok' => false, 'error' => 'query-failed'];
    }

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return ['ok' => false, 'error' => 'no-request'];
    }

    if (strtotime((string)$row['expires_at']) < time()) {
        $expire = $mysqli->prepare('UPDATE password_change_requests SET consumed_at = NOW() WHERE id = ?');
        if ($expire) {
            $requestId = (int)$row['id'];
            $expire->bind_param('i', $requestId);
            $expire->execute();
            $expire->close();
        }
        return ['ok' => false, 'error' => 'expired'];
    }

    $providedHash = hash('sha256', $code);
    if (!hash_equals((string)$row['code_hash'], $providedHash)) {
        return ['ok' => false, 'error' => 'invalid-code'];
    }

    return ['ok' => true, 'request_id' => (int)$row['id']];
}

function finalize_password_reset_after_verification(mysqli $mysqli, int $clientId, string $newPassword): array
{
    if ($clientId <= 0 || $newPassword === '' || !ensure_password_change_requests_table($mysqli)) {
        return ['ok' => false, 'error' => 'no-request'];
    }

    $stmt = $mysqli->prepare('SELECT id, expires_at FROM password_change_requests WHERE client_id = ? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'query-failed'];
    }

    $stmt->bind_param('i', $clientId);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['ok' => false, 'error' => 'query-failed'];
    }

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return ['ok' => false, 'error' => 'no-request'];
    }

    if (strtotime((string)$row['expires_at']) < time()) {
        $expire = $mysqli->prepare('UPDATE password_change_requests SET consumed_at = NOW() WHERE id = ?');
        if ($expire) {
            $requestId = (int)$row['id'];
            $expire->bind_param('i', $requestId);
            $expire->execute();
            $expire->close();
        }
        return ['ok' => false, 'error' => 'expired'];
    }

    $mysqli->begin_transaction();
    try {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $requestId = (int)$row['id'];

        $updateClient = $mysqli->prepare('UPDATE clients SET password_hash = ?, updated_at = NOW() WHERE id = ?');
        if (!$updateClient) {
            throw new RuntimeException('update-client-failed');
        }
        $updateClient->bind_param('si', $passwordHash, $clientId);
        if (!$updateClient->execute()) {
            $updateClient->close();
            throw new RuntimeException('update-client-failed');
        }
        $updateClient->close();

        $consumeRequest = $mysqli->prepare('UPDATE password_change_requests SET new_password_hash = ?, consumed_at = NOW() WHERE id = ?');
        if (!$consumeRequest) {
            throw new RuntimeException('update-request-failed');
        }
        $consumeRequest->bind_param('si', $passwordHash, $requestId);
        if (!$consumeRequest->execute()) {
            $consumeRequest->close();
            throw new RuntimeException('update-request-failed');
        }
        $consumeRequest->close();

        $mysqli->commit();
        return ['ok' => true];
    } catch (Throwable $e) {
        $mysqli->rollback();
        return ['ok' => false, 'error' => 'update-failed'];
    }
}

function confirm_password_change_code(mysqli $mysqli, int $clientId, string $code): array
{
    if ($clientId <= 0 || $code === '' || !ensure_password_change_requests_table($mysqli)) {
        return ['ok' => false, 'error' => 'no-request'];
    }

    $stmt = $mysqli->prepare('SELECT id, new_password_hash, code_hash, expires_at FROM password_change_requests WHERE client_id = ? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'query-failed'];
    }

    $stmt->bind_param('i', $clientId);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['ok' => false, 'error' => 'query-failed'];
    }

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return ['ok' => false, 'error' => 'no-request'];
    }

    if (strtotime((string)$row['expires_at']) < time()) {
        $expire = $mysqli->prepare('UPDATE password_change_requests SET consumed_at = NOW() WHERE id = ?');
        if ($expire) {
            $requestId = (int)$row['id'];
            $expire->bind_param('i', $requestId);
            $expire->execute();
            $expire->close();
        }
        return ['ok' => false, 'error' => 'expired'];
    }

    $providedHash = hash('sha256', $code);
    if (!hash_equals((string)$row['code_hash'], $providedHash)) {
        return ['ok' => false, 'error' => 'invalid-code'];
    }

    $mysqli->begin_transaction();
    try {
        $passwordHash = (string)$row['new_password_hash'];
        $requestId = (int)$row['id'];

        $updateClient = $mysqli->prepare('UPDATE clients SET password_hash = ?, updated_at = NOW() WHERE id = ?');
        if (!$updateClient) {
            throw new RuntimeException('update-client-failed');
        }
        $updateClient->bind_param('si', $passwordHash, $clientId);
        if (!$updateClient->execute()) {
            $updateClient->close();
            throw new RuntimeException('update-client-failed');
        }
        $updateClient->close();

        $consumeRequest = $mysqli->prepare('UPDATE password_change_requests SET consumed_at = NOW() WHERE id = ?');
        if (!$consumeRequest) {
            throw new RuntimeException('update-request-failed');
        }
        $consumeRequest->bind_param('i', $requestId);
        if (!$consumeRequest->execute()) {
            $consumeRequest->close();
            throw new RuntimeException('update-request-failed');
        }
        $consumeRequest->close();

        $mysqli->commit();
        return ['ok' => true];
    } catch (Throwable $e) {
        $mysqli->rollback();
        return ['ok' => false, 'error' => 'update-failed'];
    }
}
