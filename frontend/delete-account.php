<?php
session_start();
include("../backend/config/db.php");
include_once("../backend/includes/user_auth.php");
include_once("../backend/includes/csrf.php");

if (!isset($_SESSION['user_id'])) {
    $_SESSION['auth_message'] = 'Please sign in first.';
    $_SESSION['auth_type'] = 'error';
    header("Location: auth.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $userId = (int) $_SESSION['user_id'];
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) DEFAULT 0");
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL");
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active'");
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS remember_token_hash VARCHAR(255) NULL");
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS remember_token_expires DATETIME NULL");
    $stmt = $conn->prepare("UPDATE users SET is_deleted = 1, status = 'deleted', deleted_at = NOW(), remember_token_hash = NULL, remember_token_expires = NULL WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    session_unset();
    session_destroy();
    header("Location: index.php?message=Account deleted successfully.");
    exit;
}

include("../backend/includes/header.php");
?>

<main class="account-simple-page">
    <?php include("../backend/includes/profile_back_button.php"); ?>
    <section class="account-card delete-card">
        <h1>Delete Account</h1>
        <p>Are you sure you want to delete your account?</p>
        <form action="delete-account.php" method="POST" class="account-actions">
            <?php echo csrf_field(); ?>
            <a href="profile.php" class="account-btn outline">Cancel</a>
            <button type="submit" class="account-btn danger-btn">Delete Account</button>
        </form>
    </section>
</main>

<?php include("../backend/includes/footer.php"); ?>
