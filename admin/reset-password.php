<?php
session_start();
include("../backend/config/db.php");
include_once("../backend/includes/csrf.php");

if (empty($_SESSION["admin_reset_id"]) || empty($_SESSION["admin_reset_verified"])) {
    header("Location: forgot-password.php");
    exit;
}

if (empty($_SESSION["admin_reset_expires"]) || time() > (int) $_SESSION["admin_reset_expires"]) {
    unset($_SESSION["admin_reset_id"], $_SESSION["admin_reset_email"], $_SESSION["admin_reset_verified"], $_SESSION["admin_reset_expires"]);
    $_SESSION["admin_reset_message"] = "Reset session expired. Please request a new OTP.";
    $_SESSION["admin_reset_type"] = "error";
    header("Location: forgot-password.php");
    exit;
}

$message = "";
$type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_require();
    $password = $_POST["password"] ?? "";
    $confirm = $_POST["confirm_password"] ?? "";

    if (strlen($password) < 6) {
        $message = "Password must be at least 6 characters.";
        $type = "error";
    } elseif ($password !== $confirm) {
        $message = "Password and confirm password must match.";
        $type = "error";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $adminId = (int) $_SESSION["admin_reset_id"];
        $stmt = $conn->prepare("UPDATE admins SET password = ?, reset_otp = NULL, reset_otp_expiry = NULL WHERE admin_id = ? AND reset_otp_expiry >= NOW()");
        $stmt->bind_param("si", $hash, $adminId);
        if (!$stmt->execute() || $stmt->affected_rows < 1) {
            error_log("Admin password reset update failed for admin_id " . $adminId . ": " . $stmt->error);
            $message = "Password reset failed or expired. Please request a new OTP.";
            $type = "error";
        } else {

        unset($_SESSION["admin_reset_id"], $_SESSION["admin_reset_email"], $_SESSION["admin_reset_verified"], $_SESSION["admin_reset_expires"]);
        $_SESSION["admin_login_message"] = "Password reset successfully. Please login.";
        header("Location: login.php");
        exit;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Zafiro Casa Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-login-body">
    <main class="admin-login-page">
        <section class="admin-login-card">
            <span>Zafiro Casa</span>
            <h1>Reset Password</h1>
            <p>Create a new admin password.</p>
            <?php if ($message): ?><div class="admin-alert <?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <form method="POST" class="admin-login-form">
                <?php echo csrf_field(); ?>
                <input type="password" name="password" placeholder="New Password" required>
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                <button type="submit" class="admin-btn">Reset Password</button>
            </form>
        </section>
    </main>
</body>
</html>
