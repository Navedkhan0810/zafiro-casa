<?php
if (!function_exists('zafiroLoadEnv')) {
    function zafiroLoadEnv($path) {
        if (!is_readable($path)) return;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($key === '' || getenv($key) !== false) continue;
            $value = trim($value, "\"'");
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

zafiroLoadEnv(__DIR__ . '/../../.env');

define('ZAFIRO_SMTP_HOST', getenv('ZAFIRO_SMTP_HOST') ?: 'smtp.gmail.com');
define('ZAFIRO_SMTP_PORT', (int) (getenv('ZAFIRO_SMTP_PORT') ?: 587));
define('ZAFIRO_SMTP_ENCRYPTION', getenv('ZAFIRO_SMTP_ENCRYPTION') ?: 'tls');
define('ZAFIRO_SMTP_USERNAME', getenv('ZAFIRO_SMTP_USERNAME') ?: '');
define('ZAFIRO_SMTP_PASSWORD', getenv('ZAFIRO_SMTP_PASSWORD') ?: '');
define('ZAFIRO_SMTP_FROM', getenv('ZAFIRO_SMTP_FROM') ?: ZAFIRO_SMTP_USERNAME);
define('ZAFIRO_SMTP_FROM_NAME', getenv('ZAFIRO_SMTP_FROM_NAME') ?: 'Zafiro Casa Luxury Living');

function sendPasswordResetOtp($toEmail, $otp, &$error = '') {
    $subject = 'Zafiro Casa Password Reset OTP';
    $body = 'Your OTP for password reset is: ' . $otp . '. This OTP is valid for 1 minute.';
    $autoload = __DIR__ . '/../../vendor/autoload.php';

    if (ZAFIRO_SMTP_PASSWORD === '' || ZAFIRO_SMTP_PASSWORD === 'PASTE_GMAIL_APP_PASSWORD_HERE') {
        $error = 'SMTP password missing. Add mail credentials in .env.';
        return false;
    }

    if (file_exists($autoload)) {
        require_once $autoload;

        if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = ZAFIRO_SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = ZAFIRO_SMTP_USERNAME;
                $mail->Password = ZAFIRO_SMTP_PASSWORD;
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = ZAFIRO_SMTP_PORT;
                $mail->setFrom(ZAFIRO_SMTP_FROM, ZAFIRO_SMTP_FROM_NAME);
                $mail->addAddress($toEmail);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->send();
                return true;
            } catch (Exception $e) {
                $error = $mail->ErrorInfo ?: $e->getMessage();
            }
        }
    }

    $error = $error ?: 'PHPMailer is not installed. Run composer require phpmailer/phpmailer.';
    return false;
}
?>
