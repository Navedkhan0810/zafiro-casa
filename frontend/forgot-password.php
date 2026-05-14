<?php
session_start();
include("../backend/config/db.php");
include("../backend/config/mail.php");
include_once("../backend/includes/csrf.php");

$message = '';
$type = '';

function forgotPasswordColumnExists($conn, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = ?");
    $stmt->bind_param("s", $column);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0) > 0;
}

foreach ([
    'reset_otp' => "VARCHAR(10) NULL",
    'reset_otp_expiry' => "DATETIME NULL"
] as $column => $definition) {
    if (!forgotPasswordColumnExists($conn, $column)) {
        $conn->query("ALTER TABLE users ADD COLUMN `$column` $definition");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $type = 'error';
    } else {
        $stmt = $conn->prepare("SELECT id, email FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            $message = 'No account found with this email.';
            $type = 'error';
        } else {
            $otp = (string) random_int(100000, 999999);
            $update = $conn->prepare("UPDATE users SET reset_otp = ?, reset_otp_expiry = DATE_ADD(NOW(), INTERVAL 1 MINUTE) WHERE id = ?");
            $update->bind_param("si", $otp, $user['id']);
            $update->execute();

            $mailError = '';
            if (sendPasswordResetOtp($user['email'], $otp, $mailError)) {
                $_SESSION['reset_email'] = $user['email'];
                $_SESSION['reset_user_id'] = (int) $user['id'];
                $_SESSION['reset_message'] = 'OTP sent to your registered email.';
                $_SESSION['reset_type'] = 'success';
                header("Location: verify-otp.php");
                exit;
            }

            error_log('User OTP mail error: ' . $mailError);
            $message = 'OTP could not be sent. Please try again later.';
            $type = 'error';
        }
    }
}

include("../backend/includes/header.php");
?>

<link rel="stylesheet" href="../assets/css/auth.css">

<main class="auth-page">
    <section class="auth-card">
        <div class="auth-brand">
            <span>Zafiro Casa</span>
            <h1>Forgot Password</h1>
            <p>Enter your registered email address.</p>
        </div>

        <?php if ($message): ?>
            <div class="auth-alert <?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form class="auth-form active" action="forgot-password.php" method="POST">
            <input type="email" name="email" placeholder="Email" required>
            <button type="submit">Send OTP</button>
            <a href="auth.php" class="auth-link">Back to Sign In</a>
        </form>
    </section>
</main>

<?php include("../backend/includes/footer.php"); ?>
