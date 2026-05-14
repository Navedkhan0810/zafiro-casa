<?php
session_start();
include("../backend/config/db.php");
include("../backend/config/mail.php");
include_once("../backend/includes/csrf.php");

$message = "";
$type = "";

foreach (["reset_otp" => "VARCHAR(10) NULL", "reset_otp_expiry" => "DATETIME NULL"] as $column => $definition) {
    $exists = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'admins' AND column_name = ?");
    $exists->bind_param("s", $column);
    $exists->execute();
    if ((int) ($exists->get_result()->fetch_assoc()["total"] ?? 0) === 0) {
        $conn->query("ALTER TABLE admins ADD COLUMN `$column` $definition");
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_require();
    $email = trim($_POST["email"] ?? "");

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email.";
        $type = "error";
    } else {
        $stmt = $conn->prepare("SELECT admin_id, email FROM admins WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();

        if (!$admin) {
            $message = "No admin account found with this email.";
            $type = "error";
        } else {
            $otp = (string) random_int(100000, 999999);
            $update = $conn->prepare("UPDATE admins SET reset_otp = ?, reset_otp_expiry = DATE_ADD(NOW(), INTERVAL 1 MINUTE) WHERE admin_id = ?");
            $update->bind_param("si", $otp, $admin["admin_id"]);
            $update->execute();

            $mailError = "";
            if (sendPasswordResetOtp($admin["email"], $otp, $mailError)) {
                $_SESSION["admin_reset_id"] = (int) $admin["admin_id"];
                $_SESSION["admin_reset_email"] = $admin["email"];
                $_SESSION["admin_reset_message"] = "OTP sent to admin email.";
                $_SESSION["admin_reset_type"] = "success";
                header("Location: verify-otp.php");
                exit;
            }

            error_log("Admin OTP mail error: " . $mailError);
            $message = "OTP could not be sent. Please try again later.";
            $type = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Zafiro Casa Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-login-body">
    <main class="admin-login-page">
        <section class="admin-login-card">
            <span>Zafiro Casa</span>
            <h1>Forgot Password</h1>
            <p>Enter your admin email to receive OTP.</p>
            <?php if ($message): ?><div class="admin-alert <?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <form method="POST" class="admin-login-form">
                <?php echo csrf_field(); ?>
                <input type="email" name="email" placeholder="Admin Email" required>
                <button type="submit" class="admin-btn">Send OTP</button>
                <a href="login.php" class="admin-auth-link">Back to Login</a>
            </form>
        </section>
    </main>
</body>
</html>
