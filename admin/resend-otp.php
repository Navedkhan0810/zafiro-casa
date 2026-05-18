<?php
session_start();
include("../backend/config/db.php");
include("../backend/config/mail.php");

if (empty($_SESSION["admin_reset_id"]) || empty($_SESSION["admin_reset_email"])) {
    header("Location: forgot-password.php");
    exit;
}

unset($_SESSION["admin_reset_verified"]);

$adminId = (int) $_SESSION["admin_reset_id"];
$email = $_SESSION["admin_reset_email"];
$otp = (string) random_int(100000, 999999);

$stmt = $conn->prepare("UPDATE admins SET reset_otp = ?, reset_otp_expiry = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE admin_id = ? AND email = ?");
$stmt->bind_param("sis", $otp, $adminId, $email);
if (!$stmt->execute()) {
    error_log("Admin OTP resend update failed: " . $stmt->error);
    $_SESSION["admin_reset_message"] = "Reset update error: " . $stmt->error;
    $_SESSION["admin_reset_type"] = "error";
    header("Location: verify-otp.php");
    exit;
}

$mailError = "";
if (sendPasswordResetOtp($email, $otp, $mailError)) {
    $_SESSION["admin_reset_expires"] = time() + 600;
    $_SESSION["admin_reset_message"] = "New OTP sent to admin email.";
    $_SESSION["admin_reset_type"] = "success";
} else {
    error_log("Admin OTP resend mail error: " . $mailError);
    $_SESSION["admin_reset_message"] = "OTP could not be sent. Please try again later.";
    $_SESSION["admin_reset_type"] = "error";
}

header("Location: verify-otp.php");
exit;
?>
