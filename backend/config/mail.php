<?php
require_once(__DIR__ . '/../../config/app.php');

define('ZAFIRO_SMTP_HOST', zafiro_env('ZAFIRO_SMTP_HOST', 'smtp.gmail.com'));
define('ZAFIRO_SMTP_PORT', (int) zafiro_env('ZAFIRO_SMTP_PORT', 587));
define('ZAFIRO_SMTP_ENCRYPTION', zafiro_env('ZAFIRO_SMTP_ENCRYPTION', 'tls'));
define('ZAFIRO_SMTP_USERNAME', zafiro_env('ZAFIRO_SMTP_USERNAME', ''));
define('ZAFIRO_SMTP_PASSWORD', zafiro_env('ZAFIRO_SMTP_PASSWORD', ''));
define('ZAFIRO_SMTP_FROM', zafiro_env('ZAFIRO_SMTP_FROM', ZAFIRO_SMTP_USERNAME));
define('ZAFIRO_SMTP_FROM_NAME', zafiro_env('ZAFIRO_SMTP_FROM_NAME', 'Zafiro Casa Luxury Living'));

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
