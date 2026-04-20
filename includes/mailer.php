<?php


require_once __DIR__ . '/mail-config.php';

function send_smtp_mail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody, ?string &$error = null): bool
{
    $host = getenv('SMTP_HOST') ?: MAIL_SMTP_HOST;
    $port = (int)(getenv('SMTP_PORT') ?: MAIL_SMTP_PORT);
    $username = getenv('SMTP_USERNAME') ?: MAIL_SMTP_USERNAME;
    $password = getenv('SMTP_PASSWORD') ?: MAIL_SMTP_PASSWORD;
    $fromEmail = getenv('SMTP_FROM_EMAIL') ?: MAIL_FROM_EMAIL;
    $fromName = getenv('SMTP_FROM_NAME') ?: MAIL_FROM_NAME;

    if ($username === '' || $password === '') {
        $error = 'SMTP credentials missing.';
        return false;
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        $error = 'PHPMailer is not installed. Run: php composer.phar install';
        return false;
    }

    require_once $autoload;

    try {
        $mailerClass = '\\PHPMailer\\PHPMailer\\PHPMailer';
        if (!class_exists($mailerClass)) {
            $error = 'PHPMailer classes not found after autoload.';
            return false;
        }

        $mail = new $mailerClass(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = 'ssl';
        $mail->Port = $port;

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo($fromEmail, $fromName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;

        $mail->send();
        return true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return false;
    }
}
