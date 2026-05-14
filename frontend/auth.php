<?php
session_start();
include_once("../backend/includes/user_auth.php");
if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit;
}
$authMessage = $_SESSION['auth_message'] ?? '';
$authType = $_SESSION['auth_type'] ?? '';
unset($_SESSION['auth_message'], $_SESSION['auth_type']);
include("../backend/includes/header.php");
?>

<link rel="stylesheet" href="../assets/css/auth.css">

<main class="auth-page">
    <section class="auth-card">
        <div class="auth-brand">
            <span>Zafiro Casa</span>
            <h1>Luxury Living Account</h1>
            <p>Sign in or create your account to manage orders, wishlist and profile.</p>
        </div>

        <?php if ($authMessage): ?>
            <div class="auth-alert <?php echo htmlspecialchars($authType); ?>">
                <?php echo htmlspecialchars($authMessage); ?>
            </div>
        <?php endif; ?>

        <div class="auth-tabs">
            <button type="button" class="auth-tab active" data-auth-tab="login">Sign In</button>
            <button type="button" class="auth-tab" data-auth-tab="register">Sign Up</button>
        </div>

        <form class="auth-form active" id="loginForm" action="process-login.php" method="POST">
            <input type="email" name="email" placeholder="Email" required>
            <div class="password-field">
                <input type="password" name="password" placeholder="Password" required>
                <button type="button" class="password-toggle" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
            </div>
            <label class="auth-remember">
                <input type="checkbox" name="remember_me" value="1">
                <span>Remember Me</span>
            </label>
            <button type="submit">Sign In</button>
            <a href="forgot-password.php" class="auth-link">Forgot Password?</a>
        </form>

        <form class="auth-form" id="registerForm" action="process-register.php" method="POST">
            <input type="text" name="full_name" placeholder="Full Name" required>
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="tel" name="phone" placeholder="Phone Number" required>
            <select name="gender" class="auth-select">
                <option value="">Gender (Optional)</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
                <option value="Prefer not to say">Prefer not to say</option>
            </select>
            <div class="password-field">
                <input type="password" name="password" placeholder="Password" required>
                <button type="button" class="password-toggle" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
            </div>
            <div class="password-field">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                <button type="button" class="password-toggle" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
            </div>
            <button type="submit">Sign Up</button>
        </form>
    </section>
</main>

<script src="../assets/js/auth.js"></script>

<?php include("../backend/includes/footer.php"); ?>
