<?php
session_start();
include("../backend/config/db.php");
include_once("../backend/includes/csrf.php");

if (empty($_SESSION['reset_user_id']) || empty($_SESSION['reset_verified'])) {
    header("Location: forgot-password.php");
    exit;
}

$message = '';
$type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters.';
        $type = 'error';
    } elseif ($password !== $confirmPassword) {
        $message = 'Password and confirm password must match.';
        $type = 'error';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $userId = (int) $_SESSION['reset_user_id'];
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_otp = NULL, reset_otp_expiry = NULL, remember_token_hash = NULL, remember_token_expires = NULL WHERE id = ?");
        $stmt->bind_param("si", $hash, $userId);
        $stmt->execute();

        unset($_SESSION['reset_user_id'], $_SESSION['reset_email'], $_SESSION['reset_verified']);
        $_SESSION['auth_message'] = 'Password reset successfully. Please sign in.';
        $_SESSION['auth_type'] = 'success';
        header("Location: auth.php");
        exit;
    }
}

include("../backend/includes/header.php");
?>

<link rel="stylesheet" href="../assets/css/auth.css">

<main class="auth-page">
    <section class="auth-card">
        <div class="auth-brand">
            <span>Zafiro Casa</span>
            <h1>Reset Password</h1>
            <p>Create a new secure password for your account.</p>
        </div>

        <?php if ($message): ?>
            <div class="auth-alert <?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form class="auth-form active" action="reset-password.php" method="POST">
            <div class="password-field">
                <input type="password" name="password" placeholder="New Password" required>
                <button type="button" class="password-toggle" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
            </div>
            <div class="password-field">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                <button type="button" class="password-toggle" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
            </div>
            <button type="submit">Reset Password</button>
        </form>
    </section>
</main>

<script src="../assets/js/auth.js"></script>
<?php include("../backend/includes/footer.php"); ?>
