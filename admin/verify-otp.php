<?php
session_start();
include("../backend/config/db.php");
include_once("../backend/includes/csrf.php");

if (empty($_SESSION["admin_reset_id"]) || empty($_SESSION["admin_reset_email"])) {
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

$message = $_SESSION["admin_reset_message"] ?? "";
$type = $_SESSION["admin_reset_type"] ?? "";
unset($_SESSION["admin_reset_message"], $_SESSION["admin_reset_type"]);

$adminId = (int) $_SESSION["admin_reset_id"];
$expiryStmt = $conn->prepare("SELECT reset_otp_expiry FROM admins WHERE admin_id = ? LIMIT 1");
$expiryStmt->bind_param("i", $adminId);
$expiryStmt->execute();
$otpExpiry = $expiryStmt->get_result()->fetch_assoc()["reset_otp_expiry"] ?? "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_require();
    $otp = trim($_POST["otp"] ?? "");

    if (!preg_match('/^\d{6}$/', $otp)) {
        $message = "Invalid OTP.";
        $type = "error";
    } else {
        $stmt = $conn->prepare("SELECT admin_id FROM admins WHERE admin_id = ? AND reset_otp = ? AND reset_otp_expiry >= NOW() LIMIT 1");
        $stmt->bind_param("is", $adminId, $otp);
        $stmt->execute();

        if ($stmt->get_result()->num_rows) {
            $_SESSION["admin_reset_verified"] = true;
            $_SESSION["admin_reset_expires"] = time() + 600;
            header("Location: reset-password.php");
            exit;
        }

        error_log("Invalid admin reset OTP for admin_id: " . $adminId);
        $message = "Invalid OTP or OTP expired. Please resend OTP.";
        $type = "error";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP | Zafiro Casa Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-login-body">
    <main class="admin-login-page">
        <section class="admin-login-card">
            <span>Zafiro Casa</span>
            <h1>Verify OTP</h1>
            <p>Enter OTP sent to <?php echo htmlspecialchars($_SESSION["admin_reset_email"]); ?>.</p>
            <?php if ($message): ?><div class="admin-alert <?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <p class="admin-otp-countdown" data-admin-otp-expiry="<?php echo htmlspecialchars($otpExpiry); ?>">OTP expires in <span id="adminOtpTimer">01:00</span></p>
            <form method="POST" class="admin-login-form">
                <?php echo csrf_field(); ?>
                <input type="text" name="otp" maxlength="6" pattern="[0-9]{6}" placeholder="Enter OTP" required>
                <button type="submit" id="adminVerifyOtpBtn" class="admin-btn">Verify OTP</button>
                <a href="resend-otp.php" id="adminResendOtpBtn" class="admin-auth-link disabled">Resend OTP</a>
            </form>
        </section>
    </main>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
