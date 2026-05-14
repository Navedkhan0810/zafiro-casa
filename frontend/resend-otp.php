<?php
session_start();
include("../backend/config/db.php");
include("../backend/config/mail.php");

if (empty($_SESSION['reset_user_id']) || empty($_SESSION['reset_email'])) {
    header("Location: forgot-password.php");
    exit;
}

$userId = (int) $_SESSION['reset_user_id'];
$email = $_SESSION['reset_email'];
$otp = (string) random_int(100000, 999999);

$stmt = $conn->prepare("UPDATE users SET reset_otp = ?, reset_otp_expiry = DATE_ADD(NOW(), INTERVAL 1 MINUTE) WHERE id = ? AND email = ?");
$stmt->bind_param("sis", $otp, $userId, $email);
$stmt->execute();

$mailError = '';
if (sendPasswordResetOtp($email, $otp, $mailError)) {
    $_SESSION['reset_message'] = 'New OTP sent to your email.';
    $_SESSION['reset_type'] = 'success';
} else {
    error_log('User OTP resend mail error: ' . $mailError);
    $_SESSION['reset_message'] = 'OTP could not be sent. Please try again later.';
    $_SESSION['reset_type'] = 'error';
}

header("Location: verify-otp.php");
exit;
?>
