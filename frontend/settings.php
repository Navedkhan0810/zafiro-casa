<?php
session_start();
include("../backend/config/db.php");
include_once("../backend/includes/user_auth.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];
$message = '';

$conn->query("CREATE TABLE IF NOT EXISTS user_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    notifications TINYINT(1) DEFAULT 1,
    privacy_options TINYINT(1) DEFAULT 0,
    account_preferences TINYINT(1) DEFAULT 1,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notifications = isset($_POST['notifications']) ? 1 : 0;
    $privacy = isset($_POST['privacy_options']) ? 1 : 0;
    $preferences = isset($_POST['account_preferences']) ? 1 : 0;
    $stmt = $conn->prepare("INSERT INTO user_settings (user_id, notifications, privacy_options, account_preferences) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE notifications=VALUES(notifications), privacy_options=VALUES(privacy_options), account_preferences=VALUES(account_preferences)");
    $stmt->bind_param("iiii", $userId, $notifications, $privacy, $preferences);
    $message = $stmt->execute() ? 'Settings saved successfully.' : 'Settings could not be saved.';
}

$stmt = $conn->prepare("SELECT notifications, privacy_options, account_preferences FROM user_settings WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc() ?: ['notifications' => 1, 'privacy_options' => 0, 'account_preferences' => 1];

include("../backend/includes/header.php");
?>
<main class="page-bg luxury-bg settings-bg">
    <div class="page-content account-simple-page">
        <?php include("../backend/includes/profile_back_button.php"); ?>
        <section class="account-card">
            <h1>Settings</h1>
            <?php if ($message): ?><div class="auth-alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <form method="POST" class="user-settings-form">
                <label><input type="checkbox" name="notifications" <?php echo !empty($settings['notifications']) ? 'checked' : ''; ?>> Notifications</label>
                <label><input type="checkbox" name="privacy_options" <?php echo !empty($settings['privacy_options']) ? 'checked' : ''; ?>> Privacy options</label>
                <label><input type="checkbox" name="account_preferences" <?php echo !empty($settings['account_preferences']) ? 'checked' : ''; ?>> Account preferences</label>
                <button type="submit" class="account-btn small">Save Settings</button>
            </form>
            <a href="delete-account.php" class="account-btn small danger-btn">Delete Account</a>
        </section>
    </div>
</main>
<?php include("../backend/includes/footer.php"); ?>
