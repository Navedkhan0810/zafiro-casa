<?php
session_start();
include("../backend/config/db.php");
include_once("../backend/includes/csrf.php");

if (empty($_SESSION['reset_user_id']) || empty($_SESSION['reset_email'])) {
    header("Location: forgot-password.php");
    exit;
}

$message = $_SESSION['reset_message'] ?? '';
$type = $_SESSION['reset_type'] ?? '';
unset($_SESSION['reset_message'], $_SESSION['reset_type']);
$userId = (int) $_SESSION['reset_user_id'];
$expiryStmt = $conn->prepare("SELECT reset_otp_expiry FROM users WHERE id = ? LIMIT 1");
$expiryStmt->bind_param("i", $userId);
$expiryStmt->execute();
$expiryRow = $expiryStmt->get_result()->fetch_assoc();
$otpExpiry = $expiryRow['reset_otp_expiry'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $otp = trim($_POST['otp'] ?? '');

    if (!preg_match('/^\d{6}$/', $otp)) {
        $message = 'Invalid OTP.';
        $type = 'error';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND reset_otp = ? AND reset_otp_expiry >= NOW() LIMIT 1");
        $stmt->bind_param("is", $userId, $otp);
        $stmt->execute();
        $valid = $stmt->get_result()->fetch_assoc();

        if (!$valid) {
            $expired = $conn->prepare("SELECT id FROM users WHERE id = ? AND reset_otp = ? AND reset_otp_expiry < NOW() LIMIT 1");
            $expired->bind_param("is", $userId, $otp);
            $expired->execute();
            $message = $expired->get_result()->num_rows ? 'OTP expired. Please resend OTP.' : 'Invalid OTP.';
            $type = 'error';
        } else {
            $_SESSION['reset_verified'] = true;
            header("Location: reset-password.php");
            exit;
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
            <h1>Verify OTP</h1>
            <p>Enter the 6-digit OTP sent to <?php echo htmlspecialchars($_SESSION['reset_email']); ?>.</p>
        </div>

        <?php if ($message): ?>
            <div class="auth-alert <?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <p class="otp-countdown" data-otp-expiry="<?php echo htmlspecialchars($otpExpiry); ?>">OTP expires in <span id="otpTimer">01:00</span></p>

        <form class="auth-form active" action="verify-otp.php" method="POST">
            <input type="text" name="otp" placeholder="Enter OTP" maxlength="6" pattern="[0-9]{6}" required>
            <button type="submit" id="verifyOtpBtn">Verify OTP</button>
            <a href="resend-otp.php" id="resendOtpBtn" class="auth-link disabled">Resend OTP</a>
        </form>
    </section>
</main>

<?php include("../backend/includes/footer.php"); ?>
